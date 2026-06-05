<!-- /autoplan restore point: ~/.gstack/projects/Coding0508s-Mochi/backup-pre-restore-20260604-1500-autoplan-restore-20260604-schedule-green.md -->

# 구현 계획: 팀 일정 — 내가 등록한 일정 초록 테두리

**상태:** 계획 확정 (A안) · 구현 대기  
**브랜치 권장:** `feature/schedule-owned-by-me-highlight`  
**예상 규모:** ~4 files, +80 lines, DB 변경 없음

---

## 1. 개발 목표

팀 일정 뷰에서 **로그인 사용자가 등록(`created_by`)한 일정**을 유형 색상은 유지한 채 **진한 초록 왼쪽 테두리**로 강조한다.

**성공 기준**

- [ ] 팀 일정 + `created_by === auth()->id()` → `mochi-calendar-event--owned-by-me` 클래스
- [ ] 내 일정 탭 → owned 클래스 **없음**
- [ ] 캘린더 / 리스트 / day 모달 동일 규칙
- [ ] 팀 뷰에 범례 1줄 표시
- [ ] `TeamScheduleCalendarTest` 신규 케이스 통과
- [ ] `composer run verify` 또는 최소 해당 테스트 + pint

---

## 2. 확정 전제 (autoplan 승인됨)

| # | 내용 |
|---|------|
| P1 | 「등록한」= `created_by === auth()->id()` |
| P2 | **팀 일정** 뷰에서만 적용 |
| P3 | type/status 배경색 유지, owned는 **border-left 강조** |
| P4 | `done` 상태 초록 배경과 충돌하지 않도록 배경 override 금지 |

---

## 3. 현재 구조

| 구분 | 경로 |
|------|------|
| 라우트 | `routes/web.php` → `/schedules` |
| Livewire | `app/Livewire/TeamScheduleCalendar.php` |
| Blade | `resources/views/livewire/team-schedule-calendar.blade.php` |
| CSS | `resources/css/app.css` (`.mochi-calendar-event--*` ~L1147) |
| 모델 | `app/Models/TeamSchedule.php` (`created_by`, `user_id`) |
| 테스트 | `tests/Feature/TeamScheduleCalendarTest.php` |

**현재 색상 로직** (`blade` L8–18):

```php
$eventClassFor = function ($schedule): string {
    // type → cancelled → done 순 class 조합
};
```

**적용 위치** (모두 `$eventClassFor` 사용):

- 캘린더 셀 이벤트 pill (L117–128)
- 리스트 뷰 badge (L174)
- day 모달 row (L201+)

---

## 4. 작업 범위

### 수정

| 파일 | 변경 |
|------|------|
| `team-schedule-calendar.blade.php` | `$eventClassFor`에 team + created_by 분기; 팀 뷰 범례 |
| `app.css` | `.mochi-calendar-event--owned-by-me` |
| `TeamScheduleCalendarTest.php` | owned class assertion 3~4 tests |

### 선택 (권장하지 않음 — 이번 PR 최소 diff)

- `TeamScheduleCalendar.php`로 helper 이전 → Blade만 수정으로 충분

### 건드리지 않음

- `TeamSchedule` 모델 / migration
- `SharedSupplyCalendarSync`
- Policy / 라우트
- `pages/schedules/index.blade.php`

---

## 5. 구현 전략 (순서)

### Step 1 — CSS modifier 추가

`resources/css/app.css`, `.mochi-calendar-event--done` 바로 아래:

```css
.mochi-calendar-event--owned-by-me {
    border-left-width: 3px;
    border-left-color: #16a34a; /* green-600, done border(#22c55e)보다 진하게 */
}
```

- 기존 type class의 `border-left: 2.5px`를 **덮어씀**
- `done`과 동시 적용 시: 배경=done, border=owned (의도된 UX)

### Step 2 — `$eventClassFor` 확장

조건:

```php
$isOwnedByMe = $viewMode === 'team'
    && auth()->id() !== null
    && (int) $schedule->created_by === (int) auth()->id();
```

class 배열에 `$isOwnedByMe ? 'mochi-calendar-event--owned-by-me' : ''` 추가.

**주의:** `$viewMode`는 Livewire public property — blade에서 직접 참조 가능.

### Step 3 — 범례 (팀 뷰 only)

캘린더 카드 / 리스트 카드 **위**, `@if($displayMode === 'calendar')` 블록 직전:

```blade
@if($viewMode === 'team')
    <p class="mb-2 text-xs text-gray-500 flex items-center gap-2">
        <span class="inline-block w-3 h-3 rounded-sm border-l-[3px] border-l-green-600 bg-gray-100"></span>
        초록 테두리 = 내가 등록한 일정
    </p>
@endif
```

### Step 4 — 테스트

`TeamScheduleCalendarTest`에 helper 또는 inline setup:

1. **`test_team_view_marks_schedules_created_by_viewer_with_owned_class`**
   - viewer + teammate, same WORKDEPT
   - viewer가 `created_by=viewer`인 team 일정 → HTML에 `owned-by-me`
   - teammate가 `created_by=teammate`인 team 일정 → `owned-by-me` 없음

2. **`test_mine_view_does_not_mark_owned_class`**
   - viewer, `viewMode=mine`, `created_by=viewer` → owned 클래스 없음

3. **`test_team_view_done_schedule_keeps_owned_border_class`** (optional)
   - `status=done` + `created_by=viewer` → `--done`과 `--owned-by-me` 둘 다

테스트 데이터 생성 시 **`created_by` 명시** (factory/default null 가능성 대비).

### Step 5 — 검증

```bash
vendor/bin/pint --dirty
php artisan test --compact tests/Feature/TeamScheduleCalendarTest.php
# 또는 composer run verify
npm run build   # CSS 변경 반영 (dev 중이면 npm run dev)
```

---

## 6. 수용 기준 (Acceptance Criteria)

| # | Given | When | Then |
|---|-------|------|------|
| AC1 | 팀 일정 탭, A가 등록한 team 일정 | A로 로그인 | 해당 pill에 `owned-by-me` |
| AC2 | 팀 일정 탭, B가 등록한 team 일정 | A로 로그인 | `owned-by-me` 없음 |
| AC3 | 내 일정 탭 | A로 로그인 | 어떤 pill에도 `owned-by-me` 없음 |
| AC4 | 리스트·day 모달 | 팀 탭 | 캘린더와 동일 class |
| AC5 | 완료(done) + 내 등록 | 팀 탭 | done 배경 + owned border |
| AC6 | 팀 탭 | 페이지 로드 | 범례 문구 표시 |

---

## 7. 위험 요소

| 위험 | 영향 | 대응 |
|------|------|------|
| `created_by` null legacy row | 강조 안 됨 | null → non-owned; backfill은 후속 |
| CSS 빌드 미실행 | UI 변경 안 보임 | dev/build 안내 |
| 테스트에서 `created_by` 누락 | false negative | create 시 항상 set |

---

## 8. 계획 리뷰

| 항목 | 결과 |
|------|------|
| 요구사항 일치 | ✅ autoplan A안과 동일 |
| 범위 | ✅ 3~4 files, 1 PR 적합 |
| Laravel/Livewire 관례 | ✅ Blade closure 패턴 유지 |
| DB 변경 | ✅ 없음 |
| 테스트 | ✅ Feature test 명확 |
| 더 단순한 방법 | Blade-only가 최소; PHP helper 이전 불필요 |

**승인 가능 여부:** ✅ 구현 진행 가능

---

## 9. PR 본문 초안

**목적:** 팀 캘린더에서 내가 등록한 일정을 빠르게 구분

**변경:** 팀 뷰 + `created_by` 일치 시 초록 border modifier, 범례, 테스트

**영향:** `/schedules` UI만, API/DB 없음

**테스트:** `TeamScheduleCalendarTest` 추가 케이스

**배포:** `npm run build` (CSS), migration 없음

---

## GSTACK REVIEW REPORT

| Review | Status | Findings |
|--------|--------|----------|
| CEO autoplan | clean | 팀 뷰 only |
| Design autoplan | resolved | border accent |
| Eng autoplan | clean | 3 files |

**VERDICT:** 구현 계획 확정 — 승인 시 Step 1부터 진행
