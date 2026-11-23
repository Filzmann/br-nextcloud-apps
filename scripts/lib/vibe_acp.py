"""ACP v1 transport; authorization decisions belong to the supervising caller.

No transcript or credential is persisted. Agent messages are untrusted data.
The bridge does not provide filesystem/terminal client capabilities or widen
Vibe's own permissions. In particular it is not an OS sandbox.
"""

import argparse
import asyncio
import hashlib
import json
import os
from pathlib import Path
import signal
import shutil
import sys
import uuid


class ProtocolFailure(Exception):
    pass


def fingerprint(value):
    return hashlib.sha256(json.dumps(value, sort_keys=True).encode()).hexdigest()


def safe_value(value):
    """Avoid emitting obvious structured credentials, not a general DLP claim."""
    if isinstance(value, dict):
        return {
            key: "[REDACTED]" if any(part in key.lower() for part in (
                "password", "secret", "authorization", "api_key", "access_token",
            )) else safe_value(item)
            for key, item in value.items()
        }
    if isinstance(value, list):
        return [safe_value(item) for item in value]
    return value


class Decisions:
    def __init__(self):
        self.pending = {}

    def permission(self, request_id, params, tool):
        if not tool or not isinstance(tool.get("rawInput"), dict):
            raise ProtocolFailure("permission_missing_tool_details")
        if safe_value(tool) != tool:
            raise ProtocolFailure("permission_contains_credentials")
        token = uuid.uuid4().hex
        options = params.get("options", [])
        if not isinstance(options, list) or any(
            not isinstance(option, dict) or not isinstance(option.get("optionId"), str)
            for option in options
        ):
            raise ProtocolFailure("invalid_permission_options")
        once = [o["optionId"] for o in options if o.get("kind") == "allow_once"]
        if len(once) != 1:
            raise ProtocolFailure("permission_requires_allow_once")
        self.pending[token] = {
            "id": request_id, "kind": "permission", "params": params,
            "tool": tool.copy(), "fingerprint": fingerprint(tool), "once": once[0],
        }
        return {"event": "permission", "request": token,
                "decisions": ["approve_once", "deny"], "tool": tool}

    def question(self, request_id, params):
        schema = params.get("requestedSchema")
        if params.get("mode") != "form" or not isinstance(schema, dict):
            raise ProtocolFailure("unsupported_question")
        token = uuid.uuid4().hex
        self.pending[token] = {
            "id": request_id, "kind": "question", "params": params,
        }
        return {"event": "question", "request": token,
                "message": params.get("message"), "schema": schema}

    def resolve(self, command, tools):
        token = command.get("request")
        if token not in self.pending:
            raise ProtocolFailure("unknown_or_consumed_request")
        item = self.pending[token]
        action = command.get("action")
        if item["kind"] == "permission":
            if action not in ("approve_once", "deny"):
                raise ProtocolFailure("invalid_permission_decision")
            call_id = item["tool"]["toolCallId"]
            if fingerprint(tools.get(call_id)) != item["fingerprint"]:
                raise ProtocolFailure("tool_changed_since_request")
            outcome = {"outcome": "cancelled"}
            if action == "approve_once":
                outcome = {"outcome": "selected", "optionId": item["once"]}
            response = {"outcome": outcome}
        else:
            if action == "answer":
                content = command.get("content")
                validate_answer(item["params"]["requestedSchema"], content)
                response = {"action": "accept", "content": content}
            elif action == "decline":
                response = {"action": "decline"}
            else:
                raise ProtocolFailure("invalid_question_decision")
        del self.pending[token]
        return item["id"], response


def validate_answer(schema, content):
    if schema.get("type", "object") != "object" or not isinstance(content, dict):
        raise ProtocolFailure("answer_must_be_object")
    properties = schema.get("properties", {})
    if set(content) - set(properties) or set(schema.get("required", [])) - set(content):
        raise ProtocolFailure("answer_fields_invalid")
    for key, value in content.items():
        spec = properties[key]
        kind = spec.get("type")
        valid = {
            "string": isinstance(value, str), "boolean": isinstance(value, bool),
            "integer": type(value) is int,
            "number": type(value) in (int, float), "array": isinstance(value, list),
        }.get(kind, False)
        if not valid or ("enum" in spec and value not in spec["enum"]):
            raise ProtocolFailure("answer_type_invalid")
        if kind == "array" and any(not isinstance(v, str) for v in value):
            raise ProtocolFailure("answer_array_invalid")


class Bridge:
    def __init__(self, emit, send):
        self.emit = emit
        self.send = send
        self.decisions = Decisions()
        self.delivered_decisions = set()
        self.tools = {}
        self.session = None
        self.early_updates = []
        self.requests = {}
        self.server_requests = set()
        self.next_id = 0

    async def request(self, method, params):
        self.next_id += 1
        request_id = self.next_id
        future = asyncio.get_running_loop().create_future()
        self.requests[request_id] = future
        await self.send({"jsonrpc": "2.0", "id": request_id,
                         "method": method, "params": params})
        return await future

    async def receive(self, message):
        if message.get("jsonrpc") != "2.0":
            raise ProtocolFailure("invalid_jsonrpc")
        method = message.get("method")
        if method is None:
            future = self.requests.pop(message.get("id"), None)
            if future is None or future.done():
                raise ProtocolFailure("unexpected_response")
            if "error" in message:
                future.set_exception(ProtocolFailure("agent_request_failed"))
            elif "result" in message:
                future.set_result(message["result"])
            else:
                raise ProtocolFailure("invalid_response")
            return
        params = message.get("params", {})
        if method == "session/update":
            # Vibe may publish the session's initial mode before replying to
            # session/new. Defer validation until its authoritative ID arrives.
            if self.session is None:
                if len(self.early_updates) >= 128:
                    raise ProtocolFailure("too_many_initial_updates")
                self.early_updates.append(message)
                return
            if params.get("sessionId") != self.session:
                raise ProtocolFailure("foreign_session_update")
            update = params.get("update", {})
            kind = update.get("sessionUpdate")
            if kind in ("tool_call", "tool_call_update"):
                call_id = update.get("toolCallId")
                if not isinstance(call_id, str):
                    raise ProtocolFailure("invalid_tool_id")
                tool = self.tools.setdefault(call_id, {})
                tool.update({k: v for k, v in update.items() if v is not None and k in (
                    "toolCallId", "title", "kind", "rawInput", "locations",
                )})
                await self.emit({"event": "tool", "toolCallId": call_id,
                                 "title": update.get("title"), "status": update.get("status")})
            elif kind == "agent_message_chunk":
                await self.emit({"event": "message", "content": update.get("content")})
            elif kind == "usage_update":
                await self.emit({"event": "usage", "usage": update})
            return
        if "id" not in message:
            return
        request_id = message["id"]
        if type(request_id) not in (int, str) or request_id in self.server_requests:
            raise ProtocolFailure("invalid_or_replayed_agent_request")
        self.server_requests.add(request_id)
        if method in ("session/request_permission", "elicitation/create"):
            if params.get("sessionId") != self.session:
                raise ProtocolFailure("foreign_session_callback")
            if method == "session/request_permission":
                requested_tool = params.get("toolCall", {})
                call_id = requested_tool.get("toolCallId")
                tool = self.tools.get(call_id, {}).copy()
                for key in ("toolCallId", "title", "kind", "rawInput", "locations"):
                    if requested_tool.get(key) is not None:
                        if key in tool and tool[key] != requested_tool[key]:
                            raise ProtocolFailure("permission_tool_context_conflict")
                        tool[key] = requested_tool[key]
                self.tools[call_id] = tool
                event = self.decisions.permission(request_id, params, tool)
            else:
                event = self.decisions.question(request_id, params)
            await self.emit(event)
            self.delivered_decisions.add(event["request"])
        else:
            await self.send({"jsonrpc": "2.0", "id": request_id,
                             "error": {"code": -32601, "message": "Unsupported client method"}})

    async def control(self, command):
        if command.get("action") == "cancel":
            await self.send({"jsonrpc": "2.0", "method": "session/cancel",
                             "params": {"sessionId": self.session}})
            raise ProtocolFailure("cancelled")
        token = command.get("request")
        if token in self.decisions.pending and token not in self.delivered_decisions:
            raise ProtocolFailure("decision_not_delivered")
        request_id, response = self.decisions.resolve(command, self.tools)
        self.delivered_decisions.discard(command["request"])
        await self.send({"jsonrpc": "2.0", "id": request_id, "result": response})
        await self.emit({"event": "decision_sent", "request": command["request"]})


class JsonlOutput:
    def __init__(self, descriptor):
        self.descriptor = descriptor
        self.was_blocking = os.get_blocking(descriptor)
        os.set_blocking(descriptor, False)
        self.lock = asyncio.Lock()
        self.pending = b""
        self.offset = 0

    async def emit(self, event):
        record = (json.dumps(safe_value(event), ensure_ascii=False) + "\n").encode("utf-8")
        async with self.lock:
            # Cancellation can interrupt an event. Complete that same record
            # before a final stopped event so JSONL records never interleave.
            await self.flush()
            self.pending = record
            self.offset = 0
            await self.flush()

    async def flush(self):
        while self.offset < len(self.pending):
            try:
                written = os.write(self.descriptor, memoryview(self.pending)[self.offset:])
            except InterruptedError:
                continue
            except BlockingIOError:
                await self.wait_writable()
                continue
            if written <= 0:
                raise BrokenPipeError("controller_output_disconnected")
            self.offset += written
        self.pending = b""
        self.offset = 0

    async def wait_writable(self):
        loop = asyncio.get_running_loop()
        ready = loop.create_future()

        def writable():
            if not ready.done():
                ready.set_result(None)

        loop.add_writer(self.descriptor, writable)
        try:
            await ready
        finally:
            loop.remove_writer(self.descriptor)

    def close(self):
        os.set_blocking(self.descriptor, self.was_blocking)


async def supervise(args, emit):
    loop = asyncio.get_running_loop()
    loop.add_signal_handler(signal.SIGTERM, asyncio.current_task().cancel)
    command = [args.agent_command] if args.agent_command else agent_command()
    process = await asyncio.create_subprocess_exec(
        *command, cwd=args.workdir, stdin=asyncio.subprocess.PIPE,
        stdout=asyncio.subprocess.PIPE, stderr=asyncio.subprocess.DEVNULL,
        start_new_session=True, limit=2**20,
    )

    async def send(message):
        process.stdin.write((json.dumps(message) + "\n").encode())
        await process.stdin.drain()

    bridge = Bridge(emit, send)

    async def agent_messages():
        while line := await process.stdout.readline():
            try:
                message = json.loads(line)
            except (ValueError, UnicodeError) as exc:
                raise ProtocolFailure("invalid_agent_json") from exc
            if not isinstance(message, dict):
                raise ProtocolFailure("invalid_agent_message")
            await bridge.receive(message)
        raise ProtocolFailure("agent_disconnected")

    async def controls():
        reader = asyncio.StreamReader(limit=2**20)
        transport, _ = await asyncio.get_running_loop().connect_read_pipe(
            lambda: asyncio.StreamReaderProtocol(reader), sys.stdin,
        )
        try:
            while line := await reader.readline():
                try:
                    command = json.loads(line)
                    if not isinstance(command, dict):
                        raise ProtocolFailure("invalid_control")
                    await bridge.control(command)
                except (ValueError, TypeError, ProtocolFailure) as exc:
                    if isinstance(exc, ProtocolFailure) and str(exc) == "cancelled":
                        raise
                    await emit({"event": "control_rejected", "reason": str(exc)
                                if isinstance(exc, ProtocolFailure) else "invalid_control_json"})
            raise ProtocolFailure("controller_disconnected")
        finally:
            transport.close()

    async def run():
        init = await bridge.request("initialize", {
            "protocolVersion": 1,
            "clientInfo": {"name": "br-workspace-acp", "version": "1"},
            "clientCapabilities": {"elicitation": {"form": {}}},
        })
        if init.get("protocolVersion") != 1:
            raise ProtocolFailure("unsupported_protocol_version")
        session = await bridge.request("session/new", {"cwd": args.workdir, "mcpServers": []})
        bridge.session = session["sessionId"]
        for update in bridge.early_updates:
            await bridge.receive(update)
        bridge.early_updates.clear()
        modes = session.get("modes", {}).get("availableModes", [])
        if not any(m.get("id") == "ask" for m in modes):
            raise ProtocolFailure("ask_mode_unavailable")
        await bridge.request("session/set_mode", {"sessionId": bridge.session, "modeId": "ask"})
        await emit({"event": "ready", "sessionId": bridge.session,
                    "execution_class": args.execution_class, "worker": args.worker})
        prompt = (
            f"You are the coordinator. Delegate this task using agents.spawn to the configured "
            f"{args.worker} subagent. Do not perform classified worker work yourself. "
            "If that exact role cannot be started, report ROUTING_FAILED and stop. "
            "Respect the repository rules and authorized scope. Do not spawn substitute roles. "
            "Return the actual worker/model evidence and result.\n\n" + args.prompt
        )
        result = await bridge.request("session/prompt", {
            "sessionId": bridge.session, "prompt": [{"type": "text", "text": prompt}],
        })
        if bridge.decisions.pending:
            raise ProtocolFailure("turn_ended_with_pending_decisions")
        reason = result.get("stopReason")
        await emit({"event": "turn_finished", "stopReason": reason,
                    "note": "Coordinator must verify worker routing and task outcome."})
        return 0 if reason == "end_turn" else 3

    tasks = [asyncio.create_task(agent_messages()), asyncio.create_task(controls()),
             asyncio.create_task(run())]
    try:
        done, _ = await asyncio.wait(tasks, timeout=args.timeout, return_when=asyncio.FIRST_COMPLETED)
        if not done:
            raise ProtocolFailure("time_limit")
        # Prefer failures over a coincident final response.
        for task in done:
            if task.exception():
                raise task.exception()
        return tasks[2].result()
    finally:
        for task in tasks:
            task.cancel()
        await asyncio.gather(*tasks, return_exceptions=True)
        # The root may have exited while a tool child still owns this group.
        # Signal the group independently of the root's observed return code.
        try:
            os.killpg(process.pid, signal.SIGTERM)
        except ProcessLookupError:
            pass
        else:
            deadline = asyncio.get_running_loop().time() + 3
            while asyncio.get_running_loop().time() < deadline:
                await asyncio.sleep(0.05)
                try:
                    os.killpg(process.pid, 0)
                except ProcessLookupError:
                    break
            try:
                os.killpg(process.pid, signal.SIGKILL)
            except ProcessLookupError:
                pass
        await process.wait()


async def run(args):
    output = JsonlOutput(sys.stdout.fileno())
    try:
        try:
            return await supervise(args, output.emit)
        except asyncio.CancelledError:
            reason = "terminated"
        except (ProtocolFailure, OSError, KeyError, TypeError, ValueError) as exc:
            reason = str(exc) if isinstance(exc, ProtocolFailure) else "bridge_runtime_error"
        # A disconnected or non-reading controller cannot receive a final
        # status. Bound this last delivery after agent cleanup has completed.
        try:
            await asyncio.wait_for(output.emit({"event": "stopped", "reason": reason}), timeout=1)
        except (OSError, asyncio.TimeoutError):
            pass
        return 3
    finally:
        output.close()


def agent_command():
    executable = shutil.which("vibe-acp")
    if not executable:
        raise ProtocolFailure("vibe_acp_not_installed")
    # uv's Python console-script shebang locates the existing Vibe environment;
    # never interpret a shell command or install a second copy of the package.
    try:
        with Path(executable).open("rb") as script:
            first_line = script.readline(4096).decode("utf-8").strip()
    except UnicodeError as exc:
        raise ProtocolFailure("vibe_python_entrypoint_required") from exc
    python = first_line.removeprefix("#!")
    if not first_line.startswith("#!/") or "python" not in Path(python).name:
        raise ProtocolFailure("vibe_python_entrypoint_required")
    return [python, str(Path(__file__).with_name("vibe_acp_agent.py"))]


def main():
    parser = argparse.ArgumentParser(description="Supervise Vibe through ACP with explicit JSONL decisions")
    parser.add_argument("--workdir", required=True)
    parser.add_argument("--task-file", required=True)
    parser.add_argument("--execution-class", required=True,
                        choices=("ROUTINE", "STANDARD", "COMPLEX", "CRITICAL_REVIEW"))
    parser.add_argument("--timeout", type=int, default=900)
    parser.add_argument("--agent-command", help="Use an unmodified ACP executable instead of the pinned Vibe adapter")
    args = parser.parse_args()
    args.workdir = str(Path(args.workdir).resolve(strict=True))
    args.prompt = Path(args.task_file).read_text()
    args.worker = {"ROUTINE": "routine_worker", "STANDARD": "standard_worker",
                   "COMPLEX": "complex_worker", "CRITICAL_REVIEW": "critical_reviewer"}[args.execution_class]
    if not 1 <= args.timeout <= 7200 or not args.prompt.strip():
        parser.error("A nonempty task and timeout between 1 and 7200 seconds are required")
    try:
        return asyncio.run(run(args))
    except KeyboardInterrupt:
        return 3


if __name__ == "__main__":
    sys.exit(main())
