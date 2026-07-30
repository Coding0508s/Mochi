# 직책 × 권한 매트릭스 Design

**상태:** 승인됨 (대화 합의 2026-07-30)  
**관련 ADR:** `docs/adr/0007-job-title-permission-matrix.md` (ADR 0003의 권한 **출처**를 보완·대체)  
**문제:** People에서 사용자마다 권한 체크박스를 수동 지정하면 실수·불일치가 나고, 직책별 표준 권한이 한눈에 보이지 않는다.

---

## 1. Problem / Goals

### Problem

- 권한 부여가 **사람 단위 체크박스**에 의존한다.
- 동일 직책인데 권한이 제각각일 수 있다.
- Coach 팀장만 JOB 기반 동기화 선례가 있고, 나머지 플래그는 수동이다.

### Goals

- Setup에서 **직책 × 권한** 표를 편집한다.
- 직원 `JOB` 변경 또는 표 저장 시, 연동 계정의 기능 플래그를 표와 **동기화**한다.
- 기존 Gate·팀 스코프·`users` 플래그 소비 방식은 유지한다 (방안 A: 표 → `users` 복사).

### Non-goals

- Spatie Permission 등 패키지 도입
- `is_admin`(Full Access)을 직책 표로 자동 부여
- 팀(CO/CS/Coach) 메뉴·데이터 스코프 재설계
- 요청마다 JOB을 읽어 권한을 실시간 계산 (방안 B)
- GS Brochure 레거시 앱 내부 권한 모델 개편
- 기존 `users` 플래그로 표를 **역시드**하는 자동 마이그레이션

---

## 2. Core rules

### Matrix columns → `users` columns

| UI 라벨 | `users` / 매트릭스 컬럼 |
|---------|-------------------------|
| Setup 조회 | `setup_view` |
| Setup 관리 | `setup_manage` |
| Store 재고 수정 | `can_manage_store_inventory` |
| GS Brochure 관리 | `is_gs_brochure_admin` |
| Coach 팀 KPI | `is_coach_team_lead` |
| 기관 전체 조회 | `can_view_all_institutions` |
| 부관리자 | `is_deputy_admin` |

### Never from matrix

- `is_admin` — People(또는 동등한 관리 경로)에서 **사람 단위만**
- `is_active` — 계정 활성은 권한 매트릭스와 무관
- 팀 메뉴/데이터 범위 — `WORKDEPT` / `users.team` / `TeamMenuContext` 유지

### Sync semantics

1. **대상:** `employee_empno`로 `employee`와 연결된 `users` 중 **`is_admin = false`**
2. **`is_admin = true`:** 매트릭스 동기화로 위 7개 플래그를 **절대 덮어쓰지 않음**
3. **직책이 표에 없거나 행이 없음:** 위 7개 플래그를 모두 `false`
4. **`setup_manage = true`이면 `setup_view = true`** (저장·동기화 시 강제)
5. JOB 매칭: `trim(employee.JOB)` === `job_title_permissions.job_code` (대소문자·공백 정규화는 trim만; 추가 fuzzy 매칭 없음).
6. **직책 키 통일:** Setup 공통코드 `job_title`의 **`code`** 를 매트릭스 PK이자 People/엑셀에 저장하는 JOB 값으로 쓴다. People 직책 셀렉트는 distinct `employee.JOB`이 아니라 **활성 공통코드**를 쓰고, option value=`code`, 표시=`label`(또는 label 없으면 code). 레거시로 `code`와 다른 문자열이 `JOB`에 남아 있으면 “표에 없음”과 동일하게 7개 off (데이터 정리 전까지).
7. 개인별 예외 권한 없음 — 매트릭스에 포함된 7개는 People에서 **수동 편집 불가**

### Coach KPI 기존 로직

- `CoachTeamLeadEligibility` + `users:sync-coach-team-lead-from-jobs`의 **권한 on/off 책임**은 매트릭스 `is_coach_team_lead`가 대체한다.
- 부서(WORKDEPT)로 **메뉴/데이터 스코프**를 나누는 로직은 유지한다.
- 기존 Artisan은 deprecate 하거나 매트릭스 동기화 명령으로 흡수한다 (구현 계획에서 택1, 기본: 흡수+안내).

---

## 3. Data model

### Table `job_title_permissions`

| Column | Type | Notes |
|--------|------|-------|
| `id` | PK | |
| `job_code` | string, unique | `setup_common_codes.code` where `category = job_title` |
| `setup_view` | boolean default false | |
| `setup_manage` | boolean default false | |
| `can_manage_store_inventory` | boolean default false | |
| `is_gs_brochure_admin` | boolean default false | |
| `is_coach_team_lead` | boolean default false | |
| `can_view_all_institutions` | boolean default false | |
| `is_deputy_admin` | boolean default false | |
| `created_at` / `updated_at` | timestamps | |

- FK를 `setup_common_codes`에 강제하지 않아도 된다 (공통코드 soft 비활성·삭제 UX와 충돌 가능).  
  앱 레벨에서 `category = job_title` 활성 코드 목록과 조인해 UI에 표시한다.
- 모델: `App\Models\JobTitlePermission`

### Job title source

- 기존 `SetupCommonCode` (`category = job_title`)가 직책 마스터다.
- 매트릭스 UI는 **활성** 직책 행을 보여주고, 아직 행이 없으면 체크 전부(all false)로 편집 가능하게 한다. 저장 시 upsert.

---

## 4. UI / IA

### Setup

- 라우트 예: `setup.job-title-permissions` → `pages/setup/job-title-permissions.blade.php`
- Livewire: `SetupJobTitlePermissionMatrix` (이름 구현 시 조정 가능)
- Setup 허브(`pages/setup/index`)에 **직책 권한** 카드 추가 (실제 링크)
- 권한:
  - 화면 접근: `accessSetup`
  - 저장: `manageTeamStructure` (`canManageSetup`)
- 표: 행=직책(label + code), 열=7 체크박스, 저장 버튼
- 저장 성공 시 해당 `job_code`(또는 저장된 전체 직책)에 대해 사용자 동기화 실행 + flash

### People

- `PeopleEmployeesList`에서 위 7개 권한의 **편집 체크박스 제거 또는 읽기 전용**
- `is_admin`, `is_active`(및 계정 생성 등 기존 계정 관리)는 유지
- 직책(`JOB`) 저장 시 연동 User가 있으면 동기화 호출
- 직책 옵션: `SetupCommonCode` (`job_title`, 활성) — value=`code`, 표시=`label`
- UI에 “권한은 Setup 직책 권한 표에서 관리” 안내 문구

### In scope 추가 (직책 옵션 정렬)

- People(및 필요 시 직원 생성) 직책 드롭다운을 공통코드 `code` 기준으로 맞추는 변경은 **본 기능에 포함** (매트릭스 매칭이 깨지지 않게 하기 위함)

---

## 5. Sync architecture

### Single writer

`App\Support\JobTitlePermissionSynchronizer` (또는 `App\Actions\SyncUserPermissionsFromJobTitle`)

입력 예:

- `syncUser(User $user): void` — 한 계정
- `syncUsersForJobCode(string $jobCode): int` — 해당 JOB 직원들의 계정
- `syncAll(): array` — Artisan용 전체

동작:

1. User가 admin이면 return (no-op)
2. Employee JOB resolve → matrix row (없으면 all false)
3. `setup_manage`면 `setup_view` 강제 true
4. 7 플래그 `forceFill` + save
5. 가능하면 기존 계정 감사 로그 패턴에 before/after 기록

### Triggers

| 시점 | 동작 |
|------|------|
| Setup 매트릭스 저장 | 저장된 job_code(들)에 대해 `syncUsersForJobCode` |
| People JOB 저장 | 해당 직원 연동 User `syncUser` |
| Excel import JOB 변경 | 연동 User `syncUser` (임포트 배치 안에서) |
| `php artisan users:sync-permissions-from-job-titles` | `syncAll` |

### Deployment safety

- 초기 시드: **역추정 시드 없음**. 표는 운영자가 Setup에서 채운다.
- **배포 직후 자동으로 전 사용자 sync를 돌리지 않는다.**  
  표가 비어 있으면 비관리자 7개 권한이 모두 꺼질 수 있다.
- 운영 절차: (1) migrate (2) Setup에서 표 설정 (3) Artisan 동기화 수동 실행
- 매트릭스 **저장** 시에만 해당 직책 사용자를 동기화하는 것은 허용 (운영자가 의도적으로 저장한 것이므로)

---

## 6. Gate / ADR relationship

- **Gate 정의·팀 스코프·목록 쿼리 스코프** (ADR 0003의 핵심)는 유지한다.
- 바뀌는 것: 7개 기능 플래그의 **부여 출처**가 People 수동 → **직책 매트릭스 + 동기화**
- `is_admin`만 예외적으로 사람 단위 유지
- ADR 0003 상태를 **대체됨(부분)** 으로 갱신하고 ADR 0007을 수락 문서로 둔다.

---

## 7. Testing

Feature (최소):

1. Setup manage 권한 사용자만 매트릭스 저장 가능; view-only는 저장 403/불가
2. 매트릭스 저장 → 동일 JOB 비관리자 User 플래그 일치
3. People JOB 변경 → 해당 User만 갱신
4. `is_admin` User는 동기화로 7 플래그 불변
5. 표에 없는 JOB → 7 플래그 false
6. `setup_manage` true → 동기화 후 `setup_view` true
7. People에서 7개 플래그를 요청으로 바꿔도 persist되지 않음 (또는 UI에 필드 없음)

Unit (선택): Synchronizer pure mapping / admin skip

---

## 8. Docs to update (implementation)

- `docs/platform-user-guide.md` §5 — 직책 표 설명, “JOB만으로 관리자 안 됨”은 **유지**하고, 나머지 플래그는 표에서 온다고 수정
- `docs/adr/0003-...` 상태 → 대체됨(권한 출처는 0007)
- `docs/adr/0007-job-title-permission-matrix.md` 신규

---

## 9. Acceptance criteria

- [ ] Setup에서 직책별 7개 권한을 저장할 수 있다
- [ ] JOB 변경·표 저장·Artisan으로 비관리자 권한이 표와 일치한다
- [ ] `is_admin`은 표/동기화로 부여·변경되지 않는다
- [ ] People에서 7개 권한을 개인별로 다르게 줄 수 없다
- [ ] 기존 Gate 기반 화면은 `users` 플래그로 계속 동작한다
- [ ] 배포 문서/가이드에 “표 설정 후 수동 sync”가 명시된다

---

## 10. Open decisions (resolved)

| 항목 | 결정 |
|------|------|
| 방안 | A — 표 → `users` 복사 |
| `is_admin` in matrix | 아니오 |
| 개인 예외 | 없음 |
| 초기 역시드 | 없음 |
| 직책 키 | SetupCommonCode `code` = `employee.JOB` = matrix `job_code` |
| People 직책 옵션 | distinct JOB → 활성 공통코드로 전환 (본 PR 포함) |
