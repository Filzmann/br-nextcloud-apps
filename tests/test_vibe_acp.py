import importlib.util
import asyncio
import json
import os
from pathlib import Path
import subprocess
import sys
import tempfile
import time
from types import SimpleNamespace
import unittest
from unittest.mock import patch


ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("vibe_acp", ROOT / "scripts/lib/vibe_acp.py")
acp = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(acp)
AGENT_SPEC = importlib.util.spec_from_file_location("vibe_acp_agent", ROOT / "scripts/lib/vibe_acp_agent.py")
agent_adapter = importlib.util.module_from_spec(AGENT_SPEC)
AGENT_SPEC.loader.exec_module(agent_adapter)


class DecisionTests(unittest.TestCase):
    def setUp(self):
        self.tool = {"toolCallId": "call", "rawInput": {"command": "node tests/run-js.mjs"}}
        self.params = {"sessionId": "session", "toolCall": {"toolCallId": "call"},
                       "options": [{"optionId": "once", "kind": "allow_once"}]}

    def test_concrete_permission_is_pending_and_only_once_is_offered(self):
        decisions = acp.Decisions()
        event = decisions.permission(8, {
            "sessionId": "session", "toolCall": {"toolCallId": "call"},
            "options": [
                {"optionId": "once", "kind": "allow_once"},
                {"optionId": "always", "kind": "allow_always"},
                {"optionId": "reject", "kind": "reject_once"},
            ],
        }, {"toolCallId": "call", "rawInput": {"command": "node tests/run-js.mjs"}})
        self.assertEqual("permission", event["event"])
        self.assertEqual(["approve_once", "deny"], event["decisions"])
        self.assertEqual({"command": "node tests/run-js.mjs"}, event["tool"]["rawInput"])
        self.assertEqual(1, len(decisions.pending))

    def test_missing_details_and_credentials_cannot_be_approved(self):
        for tool in (None, {"toolCallId": "call"},
                     {"toolCallId": "call", "rawInput": {"api_key": "synthetic"}}):
            with self.subTest(tool=tool), self.assertRaises(acp.ProtocolFailure):
                acp.Decisions().permission(8, self.params, tool)

    def test_once_is_bound_to_tool_snapshot_and_cannot_be_replayed(self):
        decisions = acp.Decisions()
        event = decisions.permission(8, self.params, self.tool)
        command = {"request": event["request"], "action": "approve_once"}
        with self.assertRaisesRegex(acp.ProtocolFailure, "changed"):
            decisions.resolve(command, {"call": {"rawInput": {"command": "other"}}})
        with self.assertRaisesRegex(acp.ProtocolFailure, "invalid_permission"):
            decisions.resolve(dict(command, action="approve_always"), {"call": self.tool})
        self.assertEqual((8, {"outcome": {"outcome": "selected", "optionId": "once"}}),
                         decisions.resolve(command, {"call": self.tool}))
        with self.assertRaisesRegex(acp.ProtocolFailure, "consumed"):
            decisions.resolve(command, {"call": self.tool})

    def test_deny_has_no_selected_permission(self):
        decisions = acp.Decisions()
        event = decisions.permission(8, self.params, self.tool)
        self.assertEqual((8, {"outcome": {"outcome": "cancelled"}}), decisions.resolve(
            {"request": event["request"], "action": "deny"}, {"call": self.tool}))

    def test_question_requires_typed_fields(self):
        decisions = acp.Decisions()
        event = decisions.question(9, {"mode": "form", "requestedSchema": {
            "properties": {"q0": {"type": "string"}}, "required": ["q0"],
        }})
        command = {"request": event["request"], "action": "answer", "content": {"q0": 3}}
        with self.assertRaises(acp.ProtocolFailure):
            decisions.resolve(command, {})
        command["content"] = {"q0": "synthetic answer"}
        self.assertEqual((9, {"action": "accept", "content": command["content"]}),
                         decisions.resolve(command, {}))


class AdapterContractTests(unittest.IsolatedAsyncioTestCase):
    async def test_permission_is_not_approvable_until_its_event_is_fully_delivered(self):
        event_started = asyncio.Event()
        release_output = asyncio.Event()
        emitted = []
        sent = []

        async def emit(message):
            emitted.append(message)
            if message["event"] == "permission":
                event_started.set()
                await release_output.wait()

        async def send(message):
            sent.append(message)

        bridge = acp.Bridge(emit, send)
        bridge.session = "session"
        receive = asyncio.create_task(bridge.receive({
            "jsonrpc": "2.0", "id": 71, "method": "session/request_permission",
            "params": {"sessionId": "session", "toolCall": {
                "toolCallId": "call", "rawInput": {"command": "printf synthetic"},
            }, "options": [{"optionId": "once", "kind": "allow_once"}]},
        }))
        await event_started.wait()
        command = {"request": emitted[0]["request"], "action": "approve_once"}
        try:
            with self.assertRaisesRegex(acp.ProtocolFailure, "decision_not_delivered"):
                await bridge.control(command)
            self.assertEqual([], sent)
            self.assertEqual(1, len(bridge.decisions.pending))
        finally:
            release_output.set()
            await receive
        await bridge.control(command)
        self.assertEqual({"outcome": "selected", "optionId": "once"},
                         sent[0]["result"]["outcome"])

    def test_unsupported_vibe_version_stops_before_importing_runtime(self):
        with patch.dict(sys.modules, {"vibe": SimpleNamespace(__version__="unsupported")}):
            with self.assertRaisesRegex(RuntimeError, "Unsupported Vibe version"):
                agent_adapter.install()

    async def test_current_permission_cannot_reuse_a_different_old_tool_snapshot(self):
        emitted = []
        sent = []

        async def send(message):
            sent.append(message)

        async def emit(message):
            emitted.append(message)

        bridge = acp.Bridge(emit, send)
        bridge.session = "session"
        bridge.tools["call"] = {"toolCallId": "call", "rawInput": {"command": "printf safe"}}
        with self.assertRaisesRegex(acp.ProtocolFailure, "context_conflict"):
            await bridge.receive({"jsonrpc": "2.0", "id": 70,
                "method": "session/request_permission", "params": {
                    "sessionId": "session", "toolCall": {
                        "toolCallId": "call", "rawInput": {"command": "touch unexpected-file"},
                    }, "options": [{"optionId": "once", "kind": "allow_once"}],
                }})
        self.assertEqual([], emitted)
        self.assertEqual([], sent)
        self.assertEqual({}, bridge.decisions.pending)

    async def test_actual_callback_context_precedes_unchanged_upstream_handling(self):
        events = []

        class Approval:
            related_entry_id = "child-call"
            effect = SimpleNamespace(tool_name="bash", command="printf synthetic")

        class Base:
            async def _answer_callback(self, session, callback):
                events.append(("upstream", session, callback))
                return "upstream-result"

        async def update(**fields):
            events.append(("context", fields))

        cls = agent_adapter.supervised_agent(
            Base, Approval, lambda **fields: fields, lambda effect: {"command": effect.command},
        )
        instance = cls()
        instance.client = SimpleNamespace(session_update=update)
        session = SimpleNamespace(id="root-session")
        callback = SimpleNamespace(detail=Approval(), title="Actual child command")
        result = await instance._answer_callback(session, callback)
        self.assertEqual("upstream-result", result)
        self.assertEqual(("context", {"session_id": "root-session", "update": {
            "tool_call_id": "child-call", "title": "Actual child command",
            "raw_input": {"command": "printf synthetic"},
        }}), events[0])
        self.assertEqual(("upstream", session, callback), events[1])
        events.clear()
        question = SimpleNamespace(detail=object())
        await instance._answer_callback(session, question)
        self.assertEqual([("upstream", session, question)], events)


FAKE_AGENT = '''#!/usr/bin/env python3
import json, sys, time, subprocess
from pathlib import Path
def send(message):
    print(json.dumps(dict(jsonrpc="2.0", **message)), flush=True)
def read():
    return json.loads(sys.stdin.readline())
while True:
    request = read()
    method = request.get("method")
    if method == "initialize":
        assert not request["params"]["clientCapabilities"].get("terminal")
        send({"id":request["id"],"result":{"protocolVersion":1}})
    elif method == "session/new":
        send({"method":"session/update","params":{"sessionId":"session",
            "update":{"sessionUpdate":"current_mode_update","currentModeId":"ask"}}})
        send({"id":request["id"],"result":{"sessionId":"session","modes":{
            "availableModes":[{"id":"ask","name":"Ask"}]}}})
    elif method == "session/set_mode":
        assert request["params"]["modeId"] == "ask"
        send({"id":request["id"],"result":{}})
    elif method == "session/prompt":
        assert "routine_worker" in request["params"]["prompt"][0]["text"]
        scenario = request["params"]["prompt"][0]["text"].split("\\n\\n")[-1].strip()
        if scenario == "timeout":
            time.sleep(10)
        if scenario == "disconnect":
            sys.exit(0)
        if scenario == "orphan":
            code = "import signal,time,os; from pathlib import Path; signal.signal(signal.SIGTERM,signal.SIG_IGN); Path('child.pid').write_text(str(os.getpid())); time.sleep(20)"
            subprocess.Popen([sys.executable, "-c", code], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            for _ in range(100):
                if Path("child.pid").exists():
                    break
                time.sleep(.01)
            sys.exit(0)
        if scenario == "malformed":
            print("not-json", flush=True)
            time.sleep(10)
        session = "other" if scenario == "foreign" else "session"
        tool_input = {"command":"printf synthetic"}
        if scenario == "large-permission":
            tool_input = {"filePath":"synthetic-test.txt", "content":"ä🙂" * 32768}
        send({"method":"session/update","params":{"sessionId":session,"update":{
            "sessionUpdate":"tool_call","toolCallId":"call","title":"Synthetic read",
            "kind":"execute","rawInput":tool_input,
            "rawOutput":"PRIVATE_OUTPUT_MUST_NOT_BE_MIRRORED"}}})
        send({"id":71,"method":"session/request_permission","params":{
            "sessionId":"session","toolCall":{"toolCallId":"call"},
            "options":[{"optionId":"once","kind":"allow_once"},
                       {"optionId":"forever","kind":"allow_always"}]}})
        if scenario in ("foreign", "malformed"):
            time.sleep(10)
        answer = read()
        if scenario == "deny":
            assert answer["result"]["outcome"] == {"outcome":"cancelled"}
        else:
            assert answer["id"] == 71
            assert answer["result"]["outcome"] == {"outcome":"selected","optionId":"once"}
            Path("approved.marker").write_text("approved")
        send({"id":72,"method":"elicitation/create","params":{
            "sessionId":"session","mode":"form","message":"Synthetic question",
            "requestedSchema":{"type":"object","required":["q0"],
                "properties":{"q0":{"type":"string"}}}}})
        answer = read()
        assert answer["id"] == 72
        assert answer["result"] == {"action":"accept","content":{"q0":"answer"}}
        send({"id":request["id"],"result":{"stopReason":"end_turn"}})
'''


class ProcessContractTests(unittest.TestCase):
    def setUp(self):
        self.directory = tempfile.TemporaryDirectory(prefix="vibe-acp-test-")
        self.addCleanup(self.directory.cleanup)
        self.base = Path(self.directory.name)
        self.agent = self.base / "fake-agent"
        self.agent.write_text(FAKE_AGENT)
        self.agent.chmod(0o700)

    def start(self, scenario="normal", timeout=5, nonblocking_output=False):
        task = self.base / "task.txt"
        task.write_text(scenario)
        python = ["python3"]
        if os.environ.get("VIBE_ACP_TRACE_FILE"):
            python.extend(["-c", "import os,sys,trace,runpy; target=sys.argv.pop(1); sys.argv[0]=target; t=trace.Trace(count=True,trace=False,infile=os.environ['VIBE_ACP_TRACE_FILE'],outfile=os.environ['VIBE_ACP_TRACE_FILE']);\ntry: t.runfunc(runpy.run_path,target,run_name='__main__')\nfinally: t.results().write_results(show_missing=True,coverdir='/tmp/vibe-acp-trace-child')"])
        process = subprocess.Popen(python + [str(ROOT / "scripts/lib/vibe_acp.py"),
            "--workdir", str(self.base), "--task-file", str(task),
            "--execution-class", "ROUTINE", "--timeout", str(timeout),
            "--agent-command", str(self.agent),
        ], stdin=subprocess.PIPE, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
            text=True, bufsize=1,
            preexec_fn=(lambda: os.set_blocking(1, False)) if nonblocking_output else None)
        self.addCleanup(self.close_process, process)
        return process

    def close_process(self, process):
        if process.poll() is None:
            process.kill()
            process.wait()
        for stream in (process.stdin, process.stdout, process.stderr):
            stream.close()

    def receive(self, process, expected):
        for _ in range(15):
            line = process.stdout.readline()
            self.assertTrue(line, "Bridge closed its output before the expected event")
            self.assertNotIn("PRIVATE_OUTPUT", line)
            event = json.loads(line)
            if event["event"] == expected:
                return event
        self.fail("Expected event absent: " + expected)

    def respond(self, process, request, action, **fields):
        process.stdin.write(json.dumps(dict(request=request, action=action, **fields)) + "\n")
        process.stdin.flush()

    def test_bidirectional_permission_question_and_completion(self):
        for scenario in ("normal", "deny"):
            with self.subTest(scenario=scenario):
                process = self.start(scenario)
                self.receive(process, "ready")
                request = self.receive(process, "permission")
                self.respond(process, "wrong-token", "approve_once")
                self.receive(process, "control_rejected")
                self.respond(process, request["request"], "deny" if scenario == "deny" else "approve_once")
                question = self.receive(process, "question")
                self.respond(process, question["request"], "answer", content={"q0": "answer"})
                self.assertEqual("end_turn", self.receive(process, "turn_finished")["stopReason"])
                self.assertEqual(0, process.wait(timeout=5))

    def test_large_permission_survives_nonblocking_output_without_implicit_approval(self):
        process = self.start("large-permission", timeout=10, nonblocking_output=True)
        self.receive(process, "ready")
        # A permission exceeds the pipe buffer; pause the controller so the
        # bridge must handle a short write followed by EAGAIN.
        time.sleep(0.15)
        self.assertFalse((self.base / "approved.marker").exists())
        request = self.receive(process, "permission")
        self.assertEqual("ä🙂" * 32768, request["tool"]["rawInput"]["content"])
        self.assertFalse((self.base / "approved.marker").exists())
        self.respond(process, request["request"], "approve_once")
        question = self.receive(process, "question")
        self.respond(process, question["request"], "answer", content={"q0": "answer"})
        self.assertEqual("end_turn", self.receive(process, "turn_finished")["stopReason"])
        self.assertEqual(0, process.wait(timeout=5))
        self.assertEqual("approved", (self.base / "approved.marker").read_text())

    def test_nonreading_controller_is_bounded_and_never_approves(self):
        process = self.start("large-permission", timeout=1, nonblocking_output=True)
        self.receive(process, "ready")
        # Leave the large permission unread. The global deadline must still
        # terminate the agent instead of blocking inside a synchronous write.
        self.assertEqual(3, process.wait(timeout=6))
        self.assertFalse((self.base / "approved.marker").exists())
        self.assertEqual("", process.stderr.read())

    def test_closed_controller_output_never_approves_or_prints_a_traceback(self):
        process = self.start("large-permission", nonblocking_output=True)
        self.receive(process, "ready")
        process.stdout.close()
        self.assertEqual(3, process.wait(timeout=6))
        self.assertFalse((self.base / "approved.marker").exists())
        self.assertEqual("", process.stderr.read())

    def test_cancellation_timeout_disconnect_and_foreign_session_stop(self):
        for scenario, reason in (("timeout", "time_limit"), ("disconnect", "agent_disconnected"),
                                 ("foreign", "foreign_session_update"), ("malformed", "invalid_agent_json")):
            with self.subTest(scenario=scenario):
                process = self.start(scenario, timeout=1)
                self.assertEqual(reason, self.receive(process, "stopped")["reason"])
                self.assertEqual(3, process.wait(timeout=5))
        process = self.start()
        self.receive(process, "permission")
        self.respond(process, None, "cancel")
        self.assertEqual("cancelled", self.receive(process, "stopped")["reason"])
        self.assertEqual(3, process.wait(timeout=5))

    def test_controller_eof_never_approves(self):
        process = self.start()
        self.receive(process, "permission")
        process.stdin.close()
        self.assertEqual("controller_disconnected", self.receive(process, "stopped")["reason"])
        self.assertEqual(3, process.wait(timeout=5))

    def test_external_termination_runs_cleanup(self):
        process = self.start()
        self.receive(process, "permission")
        process.terminate()
        self.assertEqual("terminated", self.receive(process, "stopped")["reason"])
        self.assertEqual(3, process.wait(timeout=5))

    def test_root_exit_does_not_leave_term_ignoring_tool_child_running(self):
        process = self.start("orphan")
        self.assertEqual("agent_disconnected", self.receive(process, "stopped")["reason"])
        self.assertEqual(3, process.wait(timeout=6))
        pid = int((self.base / "child.pid").read_text())
        stat = Path(f"/proc/{pid}/stat")
        if stat.exists():
            self.assertEqual("Z", stat.read_text().split()[2], "Orphan tool remains running")


if __name__ == "__main__":
    suite = unittest.TestSuite(
        unittest.defaultTestLoader.loadTestsFromTestCase(case)
        for case in (DecisionTests, AdapterContractTests, ProcessContractTests)
    )
    raise SystemExit(not unittest.TextTestRunner().run(suite).wasSuccessful())
