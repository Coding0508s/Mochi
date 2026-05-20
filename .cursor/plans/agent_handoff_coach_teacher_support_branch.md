# Agent Handoff — Coach 교사 지원·브랜치 통합 작업 인수

> 다음 Agent가 이어받기 위한 인수 문서입니다.  
> **작성 시점:** 2026-05-20  
> **직전 대화 트랜스크립트:** [Coach 교사지원·ClassInOut 조사](2f8bd3bf-f519-41ed-b1a3-fa3cc191276b)  
> (`agent-transcripts/2f8bd3bf-f519-41ed-b1a3-fa3cc191276b.jsonl`)

---

## 1. Git / PR 상태 (먼저 읽을 것)

| 항목 | 값 |
|------|-----|
| **브랜치** | `feature/employee-password-reset-link` |
| **원격** | `origin/feature/employee-password-reset-link` (tracking 설정됨) |
| **base 대비** | `main` … 약 **283 files**, **+31k / -1.3k** lines |
| **최신 커밋** | `2277a89` — `feat: Coach 교사 지원 현황·잠재기관·기관리스트 및 개발 도구 설정` |
| **PR** | **아직 생성 안 됨** — `gh auth login` 후 생성 필요 |

### 브랜치에 묶인 커밋 (main 이후, 요약)

1. `e456159` — 비밀번호 재설정 메일 Password 상태 코드 반환
2. `78199dc` — 관리자 직원 목록 비밀번호 재설정 메일
3. `0a85d02` — PasswordResetTest 9 시나리오
4. `9ca29e9` — 지원 보고서 저장 알림·메일, 잠재기관 담당 기본값
5. `fe175a4` — 비밀번호 재설정 메일·플래시·잠재기관 미팅 알림
6. `a69d484` — 팀 업무 메뉴·담당자 알림
7. `2277a89` — **Coach 교사 지원 현황 전체 + 잠재·기관·Boost/스킬 대량 추가**

> 브랜치 이름은 `employee-password-reset-link`이지만 **실제 diff는 훨씬 넓음**. PR 제목·본문에 이 점을 명시할 것.

### PR 생성 (미완)

```bash
gh auth login   # 최초 1회
cd "/Users/boseokhur/Desktop/Mocchi 화면 Figma/mocchi-platform"
gh pr create --base main --head feature/employee-password-reset-link \
  --title "feat: 직원 비밀번호 재설정·Coach 교사 지원·잠재·기관 개선"
# PR 본문은 §1 표 + git-workflow 5단락 템플릿으로 작성 (이 handoff 파일을 그대로 PR 본문으로 쓰지 말 것)
```

웹: https://github.com/Coding0508s/Mochi/compare/main...feature/employee-password-reset-link

### 커밋에서 제외된 로컬 파일

- `.tmp-config/` (psysh 기록) — untracked, 커밋하지 말 것

---

## 2. 이번 작업 맥락 (대화에서 다룬 것)

### 2.1 Coach Team 「교사 지원 현황」 이관 (핵심)

레거시 Coach Team 「기관리스트」의 **교사 1행 단위 지원 그리드**를 MOCHI 전용 화면으로 이관.

| 항목 | 내용 |
|------|------|
| **경로** | `/coach/teacher-support` → `coach.teacher-support.index` |
| **Livewire** | `app/Livewire/CoachTeacherSupportList.php` (~2k lines, 기능 많음) |
| **뷰** | `resources/views/livewire/coach-teacher-support-list.blade.php` |
| **페이지** | `resources/views/pages/coach/teacher-support/index.blade.php` |
| **메뉴** | `resources/views/components/layouts/app.blade.php` Coach Team 하위 |

**클릭 UX (3진입점 분리)**

| 클릭 | 동작 |
|------|------|
| 행 전체 | `openEditModal` — 지원 **일정** 수정 (Plan/완료일·타입) |
| 기관명 | `openInstitutionModal` — TR 기관정보 + 기관 지원 내역 |
| 교사 이름 | `openTeacherModal` — TR 교사정보 + 지원 내역 |

**지원·KPI**

- `app/Support/CoachTeacherScope.php` — TR scope, hidden 기관 제외
- `app/Support/TeacherSupportKpiCalculator.php` — 1차/2차/완료/미지원 KPI
- `app/Support/ExcelSerialDate.php` — 계획 월 표시 (레거시 serial 대응)
- `app/Support/SkCodeNormalizer.php` — SK `*` prefix 정규화
- `config/coach_teacher_support.php` — 컬럼 매핑, KPI, `position_options`

**교사정보 모달 (TR 교사정보메인 스타일)**

- 조회/수정: 이름·연락처·직급·수업 O/X·비고·Essentials·U21/U31·GS Connect·Nexus·수료증·LS 팔로우 등
- `app/Actions/UpdateTeacherProfile.php`, `app/Actions/RetireTeacher.php`
- 퇴직: `ClassInOut=false`, `Status='퇴직'`

**지원 내역 (모달 하단 테이블)**

- `app/Support/TeacherSupportHistoryAggregator.php`
  - `coachSupportForTeacher()` — 레거시 + MOCHI coach 보고서
  - `coSupportForTeacher()` — `S_SupportInfo_Account` (CO 기관 지원, Status=완료)
  - `forTeacher()` — 둘 합침
- `detail_key`로 상세 모달: `x-coach.teacher-support-history-detail-modal`

**신규 지원 Pill → 타입별 모달 + Action + Migration**

| 타입 | Action | Migration | Blade modal |
|------|--------|-----------|-------------|
| 신규교사 시연수업 | `StoreTeacherDemoLessonSupportReport` | `teacher_demo_lesson_support_reports` | `demo-lesson-support-modal` |
| LVA+FR / FB | `StoreTeacherLvaFrSupportReport` 등 | 각각 | `lva-fr-support-modal` 등 |
| On-Site, Pro-Con, Open Class, Unit21/31, LS On-Site LVA, LittleSEED Con | 동일 패턴 | `2026_05_19_*` 10개 migration | `resources/views/components/coach/*` |

**테스트**

- `tests/Feature/CoachTeacherSupportListTest.php` — **1500+ lines**, KPI·필터·모달·퇴직·CO/Coach 분리·각 지원 타입 저장 등
- `tests/Feature/NormalizeTeacherPlanDatesTest.php`, `tests/Unit/ExcelSerialDateTest.php`

**원래 계획했던 PR 분할 (실제는 한 커밋에 통합됨)**

- PR-1: 조회 + KPI  
- PR-2: 지원 일정 수정 모달  
- PR-3: TR 교사정보 모달 + Pill + 지원 보고서  

---

### 2.2 `ClassInOut` vs 「퇴직」 — **미해결 제품 이슈**

사용자 질문·DB 조사로 확인된 **도메인/UX 불일치**. 코드 수정은 **아직 안 함**.

| DB 컬럼 | 의미 | UI에서 잘못 쓰이는 곳 |
|---------|------|---------------------|
| `Status` | 활성화 / 비활성화 (또는 퇴직 문자열) | 연락처 목록 배지 |
| `ClassInOut` | **수업 참여 O/X** (원장·부원장은 X가 정상인 경우 많음) | 연락처 **재직/퇴직** 탭, 상세 「상태」, Coach **「퇴직 포함」** 체크박스 |

**테스트 교사 (재현용)**

```
ID: 2594
Email: test@test.com
Status: 활성화
ClassInOut: false   ← 수업(X), Coach 목록 기본에서 숨김
Description: 신규 테스트
```

- 연락처 등록 기본값은 `active` + `in` (`ContactList.php`)이나, 위 row는 `ClassInOut=false`로 저장됨.
- 모달: **수업(X)** 표시 = `class_in_out` false와 일치.

**다음 Agent 권장 작업 (사용자와 합의 후)**

1. 교사지원 기본 목록: `ClassInOut=true`만이 아니라 **활성 교사(`Status`≠퇴직)** 기준 검토  
2. 체크박스 라벨 「퇴직 포함」→ 「수업 미참여 포함」 등으로 분리  
3. 연락처 상세 「상태」: 재직/퇴직과 수업 O/X **분리 표시**  
4. (선택) 직급 원장·부원장 등록 시 기본 **수업(X)** 이지만 목록에는 노출

관련 파일:

- `app/Livewire/ContactList.php` — `save()`, `render()` employmentFilter  
- `app/Livewire/CoachTeacherSupportList.php` — `buildBaseQuery()`, `showAllTeachers`  
- `app/Actions/RetireTeacher.php`

---

### 2.3 잠재기관·기관·기타 (같은 브랜치에 포함)

이전 handoff와 겹침 — 상세는 아래 문서 참고.

| 문서 | 내용 |
|------|------|
| `.cursor/plans/agent_handoff_team_schedule_potential_scope.md` | TeamSchedule `WORKDEPT` null, PotentialInstitutionList scope |
| `.cursor/plans/handoff-institution-sk-team.md` | SK 동기화, institution visibility |

**추가된/수정된 주요 항목 (이번 브랜치)**

- `PotentialInstitutionList` / `PotentialInstitutionView` — scope·UI·테스트 대량  
- `InstitutionList` — 상세·지원 모달 등  
- `UpdatePotentialInstitution`, `UpdatePotentialMeetingDetail`, `UpdateSupportRecord` Actions  
- `SupportCreateForm`, `TeamScheduleCalendar`, `InboundNotificationBell`  
- `docs/platform-user-guide.md` 개편

---

### 2.4 개발 환경·도구 (이번 커밋에 포함)

- `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `boost.json`
- `.github/workflows/harness.yml` — `composer run verify`
- `.agents/`, `.claude/`, `.cursor/skills/`, `.github/skills/` — Laravel/Livewire/Tailwind 스킬 복제
- `pint.json` — GSBrochure 제외
- `.cursor/rules/ai-development-workflow.mdc` — 4단계(계획→리뷰→코드→리뷰)

---

## 3. 아키텍처 스케치 (Coach 교사 지원)

```
CoachTeacherSupportList
├── buildBaseQuery() + CoachTeacherScope + KPI filter
├── openEditModal → UpdateTeacherSupport
├── openInstitutionModal → Institution + SupportRecord (완료)
├── openTeacherModal → teacherDetailInfo + TeacherSupportHistoryAggregator
│   ├── startTeacherEdit / saveTeacherProfile → UpdateTeacherProfile
│   ├── retireTeacher → RetireTeacher
│   └── Pill → StoreTeacher*SupportReport → teacher_*_support_reports
└── TeacherSupportHistoryDetailResolver + FormLoader (상세/수정)
```

**권한**

- Admin: `User::hasFullAccess()`
- Coach: `User::isCoachTeam()` + `CoachTeacherScope::applyTrScope()` (`S_Account_Information.TR` 매칭)
- 목록·KPI·필터 옵션 **같은 scope** 적용 필수 (버튼만 숨기면 안 됨)

---

## 4. 검증 명령

```bash
# 전체 (머지 전 권장)
composer run verify

# Coach만 빠르게
php artisan test --compact tests/Feature/CoachTeacherSupportListTest.php

# scope 회귀
php artisan test --compact tests/Feature/TeamScheduleCalendarTest.php
php artisan test --compact tests/Feature/PotentialInstitutionListTest.php

# 스타일 (PHP 변경 후)
vendor/bin/pint --dirty
```

**배포 시**

```bash
php artisan migrate   # teacher_*_support_reports 10 tables
```

---

## 5. 다음 Agent 체크리스트

- [ ] `gh auth login` 후 PR 생성 (본문 5단락: 목적/요약/영향/테스트/배포 — `.cursor/rules/git-workflow-pr-split.mdc`)
- [ ] `ClassInOut` / 「퇴직」 UX — 사용자와 정책 합의 후 구현 (§2.2)
- [ ] PR 리뷰용: diff가 매우 큼 → 기능별 리뷰 가이드 또는 후속 PR 분할 검토
- [ ] `CoachTeacherSupportList.php` 비대 — 리팩터는 별도 의도 PR로
- [ ] 목록 SK_Code 열 **퇴직/해지 배지** — 대화 중 추가했다가 grep 시 없을 수 있음 → `coach-teacher-support-list.blade.php` SK 열 확인
- [ ] `resources/views/partials/coach/teacher-detail-profile.blade.php` — partial 분리 여부 브랜치에서 확인 (있으면 모달 include 경로 확인)

---

## 6. 건드리지 말 것

- `.cursor/plans/비밀번호_재설정_메일_9d73e30e.plan.md` — 사용자가 수정 금지 요청한 계획 파일
- `GSBrochure/` — 명시 scope 없으면 수정 금지 (`AGENTS.md`)
- `.tmp-config/` — 로컬만, 커밋 금지
- production DB 직접 SQL / destructive migrate — 사용자 승인 없이 금지

---

## 7. 관련 파일 빠른 색인

```
app/Livewire/CoachTeacherSupportList.php
app/Livewire/ContactList.php
app/Support/CoachTeacherScope.php
app/Support/TeacherSupportHistoryAggregator.php
app/Support/TeacherSupportKpiCalculator.php
app/Actions/RetireTeacher.php
app/Actions/UpdateTeacherProfile.php
app/Actions/UpdateTeacherSupport.php
app/Actions/StoreTeacher*.php
app/Models/Teacher.php
config/coach_teacher_*.php
resources/views/livewire/coach-teacher-support-list.blade.php
resources/views/components/coach/
tests/Feature/CoachTeacherSupportListTest.php
routes/web.php  (coach.teacher-support.index)
```

---

## 8. 사용자 커뮤니케이션 메모

- UI 스타일: 주황 입력/amber 버튼 제거 → **기관·미팅 모달과 동일** (테이블·blue 버튼, `mochi-modal`)
- Figma 「TR 교사정보」필드·2열 레이아웃 반영 요청 있었음
- **원장·부원장**은 수업 미참여(X)가 정상 → 현재 `ClassInOut=false`를 퇴직과 동일 취급하는 것이 혼란의 핵심
- 교직원 연락처(`/contacts`)와 교사지원은 **동일 `Teachers` 테이블** — 필터/scope 때문에 한쪽에만 보일 수 있음

---

*문서 끝. 수정 시 상단 «작성 시점»과 §1 Git 해시를 갱신할 것.*
