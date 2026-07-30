# Architecture Decision Records (ADR)

MOCHI 플랫폼의 **구조적 설계 결정**을 짧게 남기는 디렉터리입니다.

## 상태 값

| 상태 | 의미 |
|------|------|
| 제안됨 | 초안, 팀 합의 전 |
| 수락됨 | 현재 코드베이스가 따르는 기준 |
| 폐기됨 | 더 이상 적용하지 않음 |
| 대체됨 | 새 ADR로 갱신됨 (번호 링크) |

## 목록

| 번호 | 제목 | 상태 |
|------|------|------|
| [0001](./0001-server-driven-ui-livewire.md) | Server-Driven UI — Livewire를 주 UI 계층으로 | 수락됨 |
| [0002](./0002-legacy-first-eloquent-strangler.md) | Legacy-First Eloquent + Strangler Fig | 수락됨 |
| [0003](./0003-gate-based-rbac-team-scope.md) | Gate 중심 RBAC + 팀 스코프 | 부분 대체됨 → 0007 |
| [0004](./0004-action-classes-write-operations.md) | Action 클래스로 쓰기 연산 분리 | 수락됨 |
| [0005](./0005-external-integration-adapters.md) | 외부 연동은 Service/Repository 어댑터 | 수락됨 |
| [0006](./ADR-001-fat-livewire-refactoring.md) | Fat Livewire 분할 — Parent/Child/Action 계층 | 제안됨 |
| [0007](./0007-job-title-permission-matrix.md) | 직책 권한 매트릭스 → users 플래그 동기화 | 수락됨 |

## 관련 가이드

- [Livewire → Action 분리 가이드](../guides/livewire-action-separation.md) — 리팩터 절차·체크리스트

## 새 ADR 작성 시

1. 다음 번호(`NNNN-short-title.md`)로 파일 추가
2. 이 README 표에 한 줄 등록
3. **대체** 시 기존 ADR 상태를 `대체됨`으로 바꾸고 새 번호 링크
