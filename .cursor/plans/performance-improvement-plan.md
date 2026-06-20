# 데이터 로딩 성능 개선 계획안

> 상태: 확정(구현 착수 대기) · 작성일: 2026-06-15
> 구현은 새 채팅 + handoff로 시작 권장 (컨텍스트 분리)

## 1. 개발 목표

- **문제**: 목록·대시보드·일정 화면의 데이터 로딩이 느림
- **원인**: 단일 버그가 아니라 전 화면에 반복되는 4가지 안티패턴 누적
  1. render마다 통계 COUNT 다중 실행
  2. 페이지네이션 없는 `->get()` 전량 로드
  3. 인덱스를 못 타는 검색(`REPLACE(...) LIKE '%..%'`)
  4. `whereDate()` + 인덱스 없는 날짜 컬럼
- **결과 목표**: 주요 화면 렌더 쿼리 수·시간 감소(측정 기반), 기능·권한·결과 동일 보장

## 2. 현재 구조 분석 (영향 파일)

| 영역 | 주요 파일 |
|------|-----------|
| 전역 | `app/Providers/AppServiceProvider.php` |
| 일정 | `app/Livewire/SharedSupplyManager.php`, `app/Livewire/TeamScheduleCalendar.php` |
| KPI | `app/Livewire/CoachTeamSupportKpiDashboard.php`, `app/Support/CoachTeamKpiAggregator.php`, `app/Support/TeacherSupportKpiCalculator.php` |
| 목록 | `ContactList.php`, `InstitutionList.php`, `PeopleEmployeesList.php`, `SupportList.php`, `CoachTeacherSupportList.php`, `VehicleUsageHistoryList.php` |
| 마이그레이션 | `shared_supplies`(`starts_at`), `vehicle_usage_logs`(`driven_on`) 등 인덱스 |

### 분석 근거 (조사에서 확인된 핵심 위치)
- `SharedSupplyManager.php:820` — 날짜 범위 데이터 전량 `get()`(화면은 40건만 사용), 동일 baseQuery로 count+limit get+전량 get 3회
- `TeamScheduleCalendar.php:360` — `visibleSchedules()`를 3회 호출해 각각 `->get()`; team 모드 이중 `whereHas`
- `CoachTeamSupportKpiDashboard.php:142` — render에서 `teamTotals()`+`byCoach()` 대형 집계 2회; 날짜 컬럼 함수 기반 조건(`YEAR()`/serial OR)
- `ContactList.php:620`, `PeopleEmployeesList.php:1055`, `InstitutionList.php:1043` — render마다 통계 COUNT 3~4회(현재 필터와 무관)
- `SupportList.php:632` — render마다 기관 전량 `get()`
- `VehicleUsageHistoryList.php:118` — `whereDate('driven_on', ...)` + `driven_on` 인덱스 없음
- 검색: 다수 목록에서 `REPLACE(col,' ','') LIKE '%..%'`(인덱스 미사용)
- 전역: `AppServiceProvider`에 `Model::preventLazyLoading()` 미적용

## 3. 작업 범위와 PR 분할

> 한 PR = 하나의 의도. 측정 → 저위험 → 인덱스 → 대량처리 순서. 마이그레이션은 별도 PR.

### PR 0 — 측정 기반 마련 (먼저, 필수)
- `preventLazyLoading()` 개발 환경 적용 (`AppServiceProvider`)
- 화면별 쿼리 수·시간 로깅 수단 도입(`DB::listen` 임시 로깅 또는 Telescope — 의존성 추가는 승인 후)
- 목적: 추측이 아니라 수치로 before/after 비교 근거 확보
- DB 변경 없음 / UI 변경 없음

### PR 1 — render 통계 COUNT 통합 (저위험, 효과 큼)
- 대상: `ContactList`, `PeopleEmployeesList`, `InstitutionList`
- 방법: 다중 COUNT → `selectRaw`의 `COUNT(CASE WHEN ...)` 단일 쿼리로 통합
- DB 변경 없음 / 결과 동일(테스트로 보장)

### PR 2 — 반복 드롭다운·옵션 캐시화 (저위험)
- 대상: 부서·직책·기관 목록·hidden SK·매니저 옵션 등 render마다 다시 읽는 데이터
- 방법: `once()`(요청 단위) 또는 `Cache::remember()`(짧은 TTL), 무효화 지점 명시

### PR 3 — 일정 페이지 중복 쿼리 제거 (중위험)
- 대상: `SharedSupplyManager`(동일 baseQuery 3회 → 1회, 탭별 lazy 조회), `TeamScheduleCalendar`(`visibleSchedules()` 3회 → memo 1회, displayMode 분기)
- 가장 체감 효과 큰 화면. 기능 동작 동일 유지가 핵심

### PR 4 — 날짜 인덱스 + whereDate 제거 (중위험, 마이그레이션 포함)
- `whereDate($col, ...)` → `whereBetween($col, [$start, $end])`
- `vehicle_usage_logs.driven_on` 등 인덱스 추가
- 마이그레이션 단독 커밋, 공유 환경 `migrate`는 사용자 승인 후

### PR 5 — KPI 집계 통합 (중위험)
- `teamTotals()`+`byCoach()` 중복 스캔 정리, 함수 기반 날짜 조건 최소화
- 데이터가 많을 때만 우선. 결과 수치 동일 보장

### PR 6 (선택) — 대량 export 비동기화
- 엑셀 `->get()` 전량 → `chunk()`/`lazy()` + 큐 다운로드

### 검색 인덱스(정규화 컬럼/Full-Text)
- 영향 범위가 크고 데이터 마이그레이션 동반 → 별도 ADR + 독립 PR로 분리 검토

## 4. 위험 요소

- 결과 변형 위험: COUNT 통합·쿼리 합치기에서 숫자/정렬/필터 결과가 달라질 수 있음 → 각 PR에 회귀 테스트 필수
- 권한 스코프: 비관리자 목록 스코프(행·필터·카운트 동시 적용 규칙)를 깨지 않도록 주의
- 마이그레이션: 운영 DB 인덱스 추가는 잠금/시간 고려, 승인 후 적용
- 캐시 일관성: 캐시한 옵션이 변경 즉시 반영 안 되는 문제 → 무효화 규칙 명시
- 의존성 추가: Telescope 등은 승인 후에만

## 5. 검증 계획

- 각 PR마다 관련 Feature 테스트 추가/갱신 후 `composer run verify`
- PR 0의 로깅으로 화면별 쿼리 수·시간 before/after 기록
- 핵심 화면 수동 확인: 목록 카운트·검색·필터·페이지네이션, 일정 캘린더/리스트, KPI 수치 일치

## 6. 계획 리뷰 (자체)

- 승인 가능 여부: PR 0~2는 저위험 즉시 진행 가능. PR 3~5는 측정 결과로 우선순위 조정 권장
- 보완 필요: "가장 느린 화면" 확정 시 순서 재배치(예: 일정 페이지면 PR 3을 앞으로)
- 더 단순한 대안: 전체를 한 번에 고치지 않고, 측정 후 상위 1~2개 병목만 우선 처리
- 구현 전 확인사항: ① 가장 느린 화면 ② Telescope 도입 허용 여부 ③ 운영 마이그레이션 적용 시점

## 7. 다음 한 가지 목표 (handoff용)

- **PR 0(측정 기반 마련)부터 시작**: `preventLazyLoading()` 적용 + 화면별 쿼리 로깅으로 before 수치 확보
- 건드리지 말 것: 운영 DB, 검색 인덱스 대공사(별도 ADR), GSBrochure/ 하위
- 관련 계획: `@.cursor/plans/performance-improvement-plan.md`
