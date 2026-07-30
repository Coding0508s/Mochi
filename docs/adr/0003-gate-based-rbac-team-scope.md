# ADR 0003: Gate 중심 RBAC + 팀 스코프

- **상태:** 부분 대체됨 — Gate·팀·목록 스코프는 유효. 기능 플래그 **부여 출처**는 [ADR 0007](0007-job-title-permission-matrix.md)
- **날짜:** 2026-06-16

## 맥락

권한은 (1) **관리자 Full Access**, (2) **기능 플래그**(Store 재고, GS Brochure 관리), (3) **팀(CO/CS/Coach)별 메뉴·데이터 범위**, (4) **작성자 본인만 수정** 등이 섞여 있다. `SETUP_MVP_PRD.md`는 UI 숨김만으로 끝내지 말고 서버에서도 검증하라고 명시한다.

## 결정

- **기능·역할:** `AppServiceProvider`의 `Gate::define(...)` 를 **1차 기준**
  - 예: `manageStoreInventory`, `accessCoTeamFeatures`, `updateSupportRecord`
- **팀 컨텍스트:** `TeamMenuContext`, `SetupRolePermissions`로 CO/CS/Coach·Setup 권한 해석
- **모델 단위 정책:** 필요한 곳만 `Policy` (현재 `SharedSupply`, `TeamSchedule`)
- **목록 스코프:** 버튼 숨김과 별도로 **Eloquent 쿼리**에서 행·필터·집계를 동일 범위로 제한
- **팀 일정:** `WORKDEPT` 없으면 타인 일정 확장하지 않음 — 본인만

## 대안

| 대안 | 기각 이유 |
|------|-----------|
| 모든 모델에 Policy | 레거시 테이블·Gate 조합 규칙이 Policy만으로 표현하기 어려움 |
| Spatie Permission 등 패키지 도입 | 기존 `is_admin`·팀·Setup 매트릭스와 이중화 |
| 프론트만 권한 처리 | 보안·데이터 유출 위험 (PRD 위반) |

## 결과

### 긍정

- Gate 이름만으로 기능 on/off 파악 가능
- `Gate::authorize` / `Gate::define` 클로저에 **도메인 규칙** 집중
- Feature 테스트에서 `actingAs` + `assertForbidden` 패턴 명확

### 부정

- 권한 로직이 Provider·Livewire·Action에 **분산**
- 신규 화면에서 Gate 이름·스코프 helper 누락 가능

### 후속 조치

- 새 기능: Gate 정의 + Livewire/Action **양쪽** 검증 + Feature 테스트 필수
- 반복되는 “본인 작성분만” 규칙은 Action·Gate 클로저로 **한곳에 모으기**
- Policy 후보: 삭제·공유 범위가 모델 단위로 고정된 CRUD (일정·공용품 패턴 확장)
