# ADR 0004: Action 클래스로 쓰기 연산 분리

- **상태:** 수락됨
- **날짜:** 2026-06-16

## 맥락

Livewire는 목록·모달·필터에 적합하지만, **저장·삭제·트랜잭션·권한·검증**까지 컴포넌트에 두면 파일이 수천 라인이 되고 테스트가 어렵다. Coach 교사 지원 보고서는 이미 `StoreTeacher*SupportReport` Action으로 분리된 선례가 있다.

## 결정

- **쓰기 연산**(create/update/delete, upsert, 상태 전환)은 `app/Actions/` 의 **단일 목적 클래스**로 추출
- **호출 방식** (둘 다 허용, 팀 내 일관성 우선):
  - `public function execute(...): Model` — 복수 인자·명시적 시그니처
  - `public function __invoke(...): Model` — 단순 파이프라인
- Action 책임:
  1. 권한 (`Gate`, `AuthorizationException`, 도메인 authorize)
  2. 입력 검증 (`Validator` 또는 FormRequest에서 넘긴 validated 배열)
  3. `DB::transaction` 등 **원자적 저장**
  4. 부수 효과(연관 레코드, 슬롯 동기화) — 가능하면 동일 Action 또는 dedicated Support
- Livewire는 **폼 상태·UI 이벤트·flash·모달 닫기**만 담당

## 대안

| 대안 | 기각 이유 |
|------|-----------|
| Service God Class | 화면별 메서드가 한 클래스에 누적 |
| Livewire에 전부 유지 | `CoachTeacherSupportList` 2800+ 라인이 반증 |
| Command Bus (전역) | 현 규모에서 추상화 과다 |

## 결과

### 긍정

- Feature/Unit 테스트가 Action 단위로 가능
- 동일 저장 로직을 Livewire·Job·API에서 재사용
- 권한 실패를 `AuthorizationException`으로 통일

### 부정

- Action vs Support 경계가 모호할 수 있음 (집계·빌더는 `Support/` 유지)
- `new StoreTeacherX` 직접 생성 vs 컨테이너 주입이 혼재

### 후속 조치

- 신규 저장: **반드시** Action 추가 후 Livewire에서 1줄 위임
- 기존 대형 Livewire: [분리 가이드](../guides/livewire-action-separation.md) 우선순위 표 참고
- `execute` / `__invoke` 신규 Action은 **한 화면 도메인 안에서 하나의 스타일**로 통일
