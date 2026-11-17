"""Version-bound context repair for Vibe's forwarded subagent approvals.

Vibe 3.1.2 forwards subagent permission callbacks without the corresponding
tool notification. Publish the callback's actual effect before delegating
the unchanged approval handling to Vibe. Never reconstruct it from a model.
"""

import inspect
import sys


SUPPORTED_VERSION = "3.1.2"


def supervised_agent(base, approval_type, make_update, effect_input):
    class SupervisedAgent(base):
        async def _answer_callback(self, session, callback):
            detail = callback.detail
            if isinstance(detail, approval_type):
                await self.client.session_update(
                    session_id=session.id,
                    update=make_update(
                        tool_call_id=detail.related_entry_id or detail.effect.tool_name,
                        title=callback.title,
                        raw_input=effect_input(detail.effect),
                    ),
                )
            # Vibe retains all deny/allow-once/session/policy semantics.
            return await super()._answer_callback(session, callback)

    return SupervisedAgent


def install():
    import vibe
    if vibe.__version__ != SUPPORTED_VERSION:
        raise RuntimeError("Unsupported Vibe version; review ACP context compatibility first")
    from acp.schema import SessionNotification, ToolCallProgress
    from vibe.acp import agent
    from vibe.app_server.models import ApprovalCallbackDetail, effect_input_json

    parameters = inspect.signature(agent.VibeAcpAgent._answer_callback).parameters
    if tuple(parameters) != ("self", "session", "callback"):
        raise RuntimeError("Unsupported ACP callback signature")
    def progress(**fields):
        return ToolCallProgress(session_update="tool_call_update", **fields)

    # Exercise the actual installed SDK's discriminated notification contract.
    SessionNotification(session_id="compatibility-check", update=progress(tool_call_id="check"))
    agent.VibeAcpAgent = supervised_agent(
        agent.VibeAcpAgent, ApprovalCallbackDetail, progress, effect_input_json,
    )


if __name__ == "__main__":
    install()
    if sys.argv[1:] == ["--verify"]:
        print("Vibe ACP context adapter: " + SUPPORTED_VERSION)
    else:
        from vibe.acp.entrypoint import main
        main()
