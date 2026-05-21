---
name: session-handoff
description: >-
  Use when the session is long, slow, or mixing unrelated topics; when the user
  asks for a new chat, handoff, 인수, or context cleanup; after finishing
  implementation/commit/PRD and starting the next task; or when switching from
  Ask-mode analysis to implementation. Produces a short handoff block and optional
  .cursor/plans/agent_handoff_*.md file.
---

# Session Handoff

긴 대화·여러 주제·넓은 코드 조사가 겹치면 응답이 느려진다. **새 채팅 + 짧은 handoff**로 컨텍스트를 이식한다.

## When to activate

- Rule `session-handoff.mdc` triggers (topic change, post-commit, slowness, 2+ unrelated topics)
- User says: 새 챗, handoff, 인수, 이어서, 컨텍스트 정리, 세션 분리
- Plan/PRD discussion ended → user wants implementation
- Git commit/push completed → new feature request in same thread

## Workflow

1. **Stop** widening scope (no large exploratory grep/read unless required for handoff).
2. **Write handoff** using the template below (≤15 lines in chat; file may be longer).
3. **Propose new chat** with copy-paste first message.
4. **Optional file** (only if user asks to save, or branch work is large):
   - Path: `.cursor/plans/agent_handoff_<short-topic>.md`
   - Follow existing examples: `agent_handoff_coach_teacher_support_branch.md`

## Handoff template (chat)

```markdown
## Handoff

| 항목 | 내용 |
|------|------|
| 브랜치 | `feature/...` |
| 마지막 커밋 | `abc1234` — 한 줄 요약 |
| 완료 | • ... (max 3) |
| 미완료 / 열린 결정 | • ... |
| 건드리지 말 것 | • ... |
| 다음 목표 (하나) | ... |

### 새 챗 첫 메시지 (복붙)

[Handoff] 브랜치: ... | 커밋: ...
완료: ...
다음 목표: ... (한 가지)
제약: ...
```

## New chat first-message template

```text
[Handoff] 브랜치: {branch} | 커밋: {sha} — {summary}
완료: {1-3 bullets}
다음 목표: {single goal}
제약: {scope / do-not-touch}
```

Attach plan file if exists: `@.cursor/plans/{plan}.md`

## Topic → new chat title (MOCHI examples)

| Topic | Suggested chat focus |
|-------|----------------------|
| UI/CSS only | `fix: {screen} {element}` |
| Feature PR | `feat: {feature name}` |
| Architecture / PRD | `PRD: {topic}` (Ask, no code) |
| Git only | `chore: commit {branch}` |
| Tests / CI | `test: {suite or filter}` |

## Integration with AI development workflow

| Phase ends | Action |
|------------|--------|
| Plan + plan review approved | New chat with `@plan.md` + handoff + "바로 구현해" or implementation scope |
| Code + review + verify done | Handoff before next feature |
| User says "추후 개발" | Handoff as **backlog note** only; no implementation |

## Do not

- Repeat full prior PRD/architecture in the same session
- Commit `.tmp-config/`, `.env`, secrets in handoff commits unless user explicitly asks
- Start implementation in the same turn as handoff unless user says to continue here

## Collecting git context (readonly)

When handoff needs branch/commit:

```bash
git status -sb
git log -1 --oneline
```

Use actual values in the table; do not invent SHAs.
