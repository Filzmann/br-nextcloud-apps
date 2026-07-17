---
name: evaluate-learning-candidate
description: Evaluate and classify a reusable project observation before proposing it as a durable rule, skill, script, hook, test, or documentation update. Use when a task yields a possible learning or the user asks to preserve one; do not use for session notes, ordinary task summaries, unverified guesses, or automatic AGENTS.md edits.
---

# Evaluate a Learning Candidate

## Classification

1. Gather reproducible evidence from code, tests, logs, documented behavior, or explicit confirmation by Simon.
2. Reject one-off status, speculation, temporary workarounds, task-specific to-dos, sensitive content, and facts already expressed better by code/tests.
3. Classify the candidate:
   - Root `AGENTS.md`: stable cross-app architecture, safety, quality, stop, or Definition-of-Done rule.
   - App `AGENTS.md`: stable app-specific fachliche, rights, architecture, or test rule.
   - Skill: repeatable, bounded workflow with clear trigger and non-trigger conditions.
   - Script/test/hook/ignore rule: unambiguous behavior that can be enforced mechanically without blocking legitimate work.
   - `docs/`: explanatory or operational knowledge for humans, not an agent invariant.
4. Prefer a technical check over prose when the behavior is deterministic. Do not add a hook unless hook installation/management is already reliable in the repository.
5. Check for duplication or contradiction at Root and app scope.

## Proposal format

Do not edit a durable rule before explicit approval. Return:

```text
Mögliches Learning:
- Ebene: Parent / App / beide
- Ziel: <AGENTS.md, Skill, Skript, Test, Hook, Ignore-Regel oder docs/...>
- Status: verifiziert / plausibel / unbestätigt / verworfen
- Evidenz: <kurzer reproduzierbarer Nachweis>
- Grund: <künftiger Nutzen und richtige Einordnung>
- Vorgeschlagener Inhalt:
  <kurzer konkreter Text oder mechanischer Vertrag>
```

If approved, implement only the classified target, update related tests/checks, and record any moved or replaced rule in the relevant migration or change report.
