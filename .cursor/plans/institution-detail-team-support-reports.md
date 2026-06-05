# 기관 상세 모달 — 팀별 지원 보고서 Implementation Plan

> **For agentic workers:** 구현 시 `ai-development-workflow` 3→4단계 + `composer run verify`  
> **Goal:** InstitutionList 기관 상세 모달에서 CO / Coach / CS 팀이 작성한 **기관 지원**·**교사 지원** 보고서를 팀별로 구분해 본다.

**Architecture:** 기존 `TeacherSupportHistoryAggregator`·`SupportRecord` 조회를 재사용하고, 작성자(TR_Name / coach_name) → `employee.WORKDEPT` 기반 팀 분류를 `SupportAuthorTeamResolver`로 중앙화한다. UI는 3탭(또는 세그먼트) × (기관 지원 표 + 교사 지원 표) 구조.

**Tech Stack:** Laravel 13, Livewire 4, Blade, PHPUnit

---

## 1. 개발 목표

### 해결하려는 문제
- 현재 **기관 리스트 → 기관 상세 모달**(`InstitutionList`)은 `S_SupportInfo_Account` **기관 지원 보고서만** 단일 테이블로 표시한다.
- **교사 지원 보고서**는 `CoachTeacherSupportList` 기관 모달에만 있고, **팀(CO / Coach / CS) 구분**도 없다.
- 사용자는 한 기관에 대해 **각 팀이 작성한 기관·교사 지원 이력**을 한 화면에서 팀별로 보고 싶다.

### 구현 결과 (수용 기준)
- [ ] 상세 모달 하단 이력 영역에 **CO Team / Coach Team / CS Team** 탭(또는 동등한 세그먼트)이 있다.
- [ ] 각 탭에 **기관 지원 보고서** 표 + **교사 지원 보고서** 표가 있다.
- [ ] 기관 지원 행 클릭 → 기존 `openSupportDetailModal` 상세 모달 유지.
- [ ] 교사 지원 행 클릭 → `detail_key` 기반 상세 모달( Coach 화면과 동일 resolver ) 오픈.
- [ ] 팀 분류는 **작성자 employee.WORKDEPT** 우선, 레거시 fallback 규칙 문서화.
- [ ] 최근 10년 범위·정렬(지원일 desc) 유지.
- [ ] Feature test로 팀별 분류·표시 검증.

### Out of scope (v1)
- `CoachTeacherSupportList` 기관 모달 UI 동기화 (후속 PR 가능).
- DB에 `dePart` / team 컬umn 신규 저장 (레거시 데이터 정확도 개선은 v2).
- 잠재기관(`PotentialInstitutionList`) 모달.

---

## 2. 현재 구조 분석

| 구분 | 파일 / 테이블 |
|------|----------------|
| Livewire | `app/Livewire/InstitutionList.php` — `openDetailModal()` 에서 `supportHistory` flat 로드 |
| Blade | `resources/views/livewire/institution-list.blade.php` — "최근 10년 지원/소통 이력" 단일 테이블 |
| 기관 지원 모델 | `app/Models/SupportRecord.php` — `S_SupportInfo_Account`, 작성자=`TR_Name`, **팀 컬럼 미저장** |
| 교사 지원 집계 | `app/Support/TeacherSupportHistoryAggregator.php` — `forInstitution()` 존재, InstitutionList 미사용 |
| 참고 UI | `CoachTeacherSupportList` + `coach-teacher-support-list.blade.php` — 기관/교사 이력 분리(팀 구분 없음) |
| 팀 코드 | `app/Support/TeamMenuContext.php` — A02=CO, A03=CS, A05=Coach |
| 담당자 정규화 | `app/Support/ManagerNameNormalizer.php` |
| 상세 모달(기관) | `resources/views/components/institution/support-detail-modal.blade.php` |
| 교사 상세 | `TeacherSupportHistoryDetailResolver`, `coach/teacher-support-history-detail-modal.blade.php` |
| 테스트 | `tests/Feature/InstitutionListTest.php` |

### 현재 `openDetailModal` 데이터 로드 (변경 대상)

```php
// InstitutionList.php L220-245 — 기관 지원만, 팀 필드 없음
$this->supportHistory = SupportRecord::query()
    ->where('SK_Code', $institution->SKcode)
    ->where(/* 10년 */)
    ->limit(300)
    ->get()
    ->map(/* flat array */)
    ->toArray();
```

---

## 3. 팀 분류 규칙 (핵심 설계)

### `SupportAuthorTeamResolver` (신규)

**입력:** 작성자 표시명 (`TR_Name` 또는 `coach_name`)  
**출력:** `'co' | 'coach' | 'cs' | 'unknown'`

**우선순위 (YAGNI — v1)**

1. **Employee 매칭**  
   - `ManagerNameNormalizer::normalize($name)` 으로 `employee.KOREANAME`, `employee.ENGLISHNAME` 일괄 조회 (1 query, in-memory map).  
   - `WORKDEPT` → `TeamMenuContext::inferUserTeamFromWorkDept()` → co / coach / cs.

2. **User 매칭** (employee 미매칭 시)  
   - `users.name` / `nameForCoReports()` 경로와 normalize 비교 → `users.team` 또는 연결 `employee.WORKDEPT` 재조회.

3. **JOB fallback** (employee 있으나 WORKDEPT 비어 있음)  
   - `TeamMenuContext::inferUserTeamFromJob($employee->JOB)` → co / coach / cs

4. **unknown**  
   - UI: **"미분류"** 섹션을 탭 하단 또는 별도 접이식으로 표시 (데이터 유실 방지).  
   - v1에서 unknown을 특정 팀 탭에 숨기지 않는다.

> **주의:** `SupportCreateForm` 저장 시 `dePart` 미기록 → 레거시·과거 데이터는 resolver 의존. v2에서 저장 시 team snapshot 추가 검토.

---

## 4. 데이터 집계 설계

### `InstitutionTeamSupportHistoryBuilder` (신규 Support 클래스)

**책임:** SK 코드(및 alias) 기준으로 두 소스를 팀별로 그룹핑.

```php
/**
 * @return array{
 *   co: array{institution: list<array>, teacher: list<array>},
 *   coach: array{institution: list<array>, teacher: list<array>},
 *   cs: array{institution: list<array>, teacher: list<array>},
 *   unknown: array{institution: list<array>, teacher: list<array>},
 *   totals: array{institution: int, teacher: int},
 * }
 */
public function build(string $skCode, Institution $institution, int $yearWindow = 10, int $limitPerSource = 300): array
```

**기관 지원:** 기존 `SupportRecord` 쿼리 + 각 row에 `team`, `detail_key: null`, `report_kind: institution` 추가.

**교사 지원:** `TeacherSupportHistoryAggregator::forInstitution()` — limit 상향 또는 builder 내부에서 동일 SK teacherIds로 재조회.  
- 각 row에 `team` (coach_name 기준 resolver), `detail_key`, `report_kind: teacher` 유지.

**정렬:** 팀·종류별 `sort_at` / `support_date` desc.

**성능:**  
- Employee name→WORKDEPT map은 request당 `once()` 캐시.  
- InstitutionList 모달 1회 오픈 = SupportRecord 1 + Aggregator 1 + Employee 1 (목표).

---

## 5. UI 설계

### 레이아웃 (institution-list.blade.php)

기존 "최근 10년 지원/소통 이력" 블록 교체:

```
[ CO Team | Coach Team | CS Team ]  ← wire:model live activeSupportTeamTab
총 N건 (기관 M + 교사 K)

── 기관 지원 보고서 ──
| 지원일 | 시간 | 담당자 | 지원방법 | ... | 상태 |
(행 클릭 → openSupportDetailModal)

── 교사 지원 보고서 ──
| 지원일 | 담당 코치 | 교사명 | 지원 타입 | 상태 |
(행 클릭 → openTeacherSupportHistoryDetail)

[미분류 N건] (unknown > 0 일 때만 접이식)
```

### Livewire 상태 추가 (`InstitutionList`)

```php
public string $activeSupportTeamTab = 'co'; // co|coach|cs
public array $teamSupportHistory = [];     // builder 출력
public bool $showTeacherSupportDetailModal = false;
public ?array $selectedTeacherSupportHistoryDetail = null;
```

- `openDetailModal`: `teamSupportHistory` 빌드, 탭 기본값 = **데이터가 있는 첫 팀** 또는 로그인 사용자 팀(`TeamMenuContext::activeMenu()`).
- `closeDetailModal`: 신규 상태 reset.
- `openTeacherSupportHistoryDetail`: `CoachTeacherSupportList` 로직 **trait 또는 공유 Action**으로 추출해 재사용 (중복 방지).

### "지원보고서 작성" CTA

- 현재: `route('supports.create', ['sk_code' => ..., 'return' => 'institutions'])`  
- 변경: **활성 탭**에 `team_menu` query 추가 (`co` / `coach` / `cs`).

### Blade partial 추출 (권장)

- `resources/views/partials/institution/team-support-history-section.blade.php`  
- 향후 CoachTeacherSupportList 재사용 가능.

---

## 6. 작업 범위

### 수정
- `app/Livewire/InstitutionList.php`
- `resources/views/livewire/institution-list.blade.php`
- `tests/Feature/InstitutionListTest.php`

### 신규
- `app/Support/SupportAuthorTeamResolver.php`
- `app/Support/InstitutionTeamSupportHistoryBuilder.php`
- `tests/Unit/SupportAuthorTeamResolverTest.php`
- `tests/Feature/InstitutionDetailTeamSupportHistoryTest.php` (또는 InstitutionListTest 확장)
- (선택) `app/Livewire/Concerns/OpensTeacherSupportHistoryDetail.php`
- (선택) partial blade

### 건드리지 않을 것
- `SupportCreateForm` 저장 로직 (v2)
- `CoachTeacherSupportList` (별도 PR)
- 마이그레이션 / schema 변경

---

## 7. 구현 순서 (Task breakdown)

### Task 1: SupportAuthorTeamResolver + Unit test

**Files:**
- Create: `app/Support/SupportAuthorTeamResolver.php`
- Create: `tests/Unit/SupportAuthorTeamResolverTest.php`

- [ ] WORKDEPT A02/A03/A05 → co/cs/coach 테스트
- [ ] employee 영문명 매칭 테스트
- [ ] 기관 CO/TR/CS fallback 테스트
- [ ] unknown 테스트

```bash
php artisan test --compact tests/Unit/SupportAuthorTeamResolverTest.php
```

### Task 2: InstitutionTeamSupportHistoryBuilder + Unit/Feature test

**Files:**
- Create: `app/Support/InstitutionTeamSupportHistoryBuilder.php`
- Modify: `tests/Feature/InstitutionListTest.php` (또는 전용 Feature test)

- [ ] 동일 SK에 CO/Coach/CS 작성 기관·교사 보고서 seed
- [ ] builder가 팀별 bucket에 올바르게 분류하는지 assert

### Task 3: InstitutionList 데이터 로드 교체

**Files:**
- Modify: `app/Livewire/InstitutionList.php`

- [ ] `supportHistory` flat 배열 → `teamSupportHistory` 구조로 교체
- [ ] `openDetailModal` / `closeDetailModal` / `reloadSupportDetailAfterUpdate` 연동
- [ ] `openTeacherSupportHistoryDetail` (+ close) — Concern 추출 또는 인라인 최소 구현

### Task 4: Blade UI — 팀 탭 + 이중 표

**Files:**
- Modify: `resources/views/livewire/institution-list.blade.php`
- Create (선택): `resources/views/partials/institution/team-support-history-section.blade.php`
- Include: `x-coach.teacher-support-history-detail-modal` 또는 institution 전용 wrapper

- [ ] 탭 전환 `wire:model.live="activeSupportTeamTab"`
- [ ] 빈 상태 copy ("해당 팀의 보고서가 없습니다")
- [ ] 총 건수 badge = institution + teacher 합

### Task 5: 교사 지원 상세 모odal 연동

**Files:**
- Modify: `institution-list.blade.php` — detail modal include
- Reuse: `TeacherSupportHistoryDetailResolver`, existing blade component

- [ ] SK scope 검증 (다른 기관 detail_key 차단)
- [ ] 해지 기관 read-only 정책 Coach와 동일 적용 여부 확인

### Task 6: 검증

```bash
vendor/bin/pint --dirty
php artisan test --compact tests/Unit/SupportAuthorTeamResolverTest.php tests/Feature/InstitutionListTest.php
composer run verify
```

---

## 8. 위험 요소

| 위험 | 대응 |
|------|------|
| 레거시 `TR_Name` ↔ employee 불일치 | fallback + unknown 섹션; v2 dePart 저장 |
| 교사 지원 legacy 테이블 test env 미존재 | Feature test는 mochi_report_tables + SupportRecord teacher-target row 위주 |
| InstitutionList Livewire 비대화 | Builder/Resolver 분리, Concern으로 detail modal만 추출 |
| 300건 limit × 3팀 UI 혼란 | 탭별 건수 badge; 전체 limit 유지 (기존과 동일) |
| N+1 employee lookup | name map 1회 preload |

---

## 9. 계획 리뷰

- **승인 가능 여부:** 예 — 범위가 InstitutionList 모달에 한정되고 기존 Aggregator 재사용 가능.
- **보완 필요:**
  - unknown 표시 방식(접이식 vs 4번째 탭) — **v1: 접이식 "미분류"** 권장.
  - CoachTeacherSupportList 동기화 시점 — v1 이후 별도 PR.
- **구현 전 확인:**
  - [ ] 사용자에게 **탭 UI vs 세로 3블록**(스크롤) 선호 확인 (기본: 탭).
  - [ ] unknown 보고서를 특정 팀에 강제 배정할 legacy 규칙이 있는지 PO 확인.

---

## 10. PR 가이드

- **브랜치:** `feature/institution-detail-team-support-history`
- **PR 제목:** `feat: 기관 상세 모달 팀별 지원·교사 보고서 이력`
- **한 PR = 이 기능만** (People/Employees 등 WIP 분리)

---

## Eng Review 보완 (2026-06-05, /plan-eng-review)

구현 전 계획에 반영할 **필수 수정**:

1. **SK 코드 조회:** `where('SK_Code', $sk)` 단일 조건 금지. `CoachTeacherSupportList` 와 동일하게 `SkCodeNormalizer::candidates($skCode)` + `whereIn('SK_Code', $candidates)` 사용.
2. **교사 지원 limit:** `forInstitution(..., limit: 10)` 그대로 쓰면 10년 이력과 불일치. 기관 지원과 동일 상한(예: 300) 또는 10년 window 내 전체 집계 후 팀별 slice.
3. **완료 필터:** Coach 기관 모달은 `->completed()` 만 쓰지만, InstitutionList 는 진행중/완료 모두 표시. Builder 에서 **completed-only 적용하지 말 것** (회귀 방지).
4. **Concern 필수화:** `openTeacherSupportHistoryDetail` 은 Coach 쪽 FormLoader/편집 분기 포함. **선택이 아니라** `OpensTeacherSupportHistoryDetail` Concern 추출 필수.
5. **reloadSupportDetailAfterUpdate:** `openDetailModal` 재호출 시 `teamSupportHistory` 재빌드 경로 명시 (기존 `supportHistory` 제거 시 회귀).
6. **Resolver:** 기관 담당자 이름 fallback **제외** (확정). employee + users + JOB 만.

---

## GSTACK REVIEW REPORT

| Review | Trigger | Why | Runs | Status | Findings |
|--------|---------|-----|------|--------|----------|
| CEO Review | `/plan-ceo-review` | Scope & strategy | 0 | — | — |
| Codex Review | `/plan-codex-review` | Independent 2nd opinion | 0 | — | — |
| Eng Review | `/plan-eng-review` | Architecture & tests (required) | 1 | issues_open | 6 issues, 2 critical gaps |
| Design Review | `/plan-design-review` | UI/UX gaps | 0 | — | — |

- **UNRESOLVED:** 0 (assignee fallback → B 확정)
- **VERDICT:** Eng Review **CLEARED (조건부)** — SK candidates·limit·Concern·completed 필터 보완 반영 후 구현
