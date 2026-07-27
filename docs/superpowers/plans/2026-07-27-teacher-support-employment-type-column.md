# 교사 지원 현황 고용형태 컬럼 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 교사 지원 현황 데스크톱 테이블·모바일 카드에서 직급 다음에 고용형태를 표시한다.

**Architecture:** 기존 `Teachers.EmploymentType` + `TeacherEmploymentType::label()`을 뷰에서 직접 표시한다. sticky CSS에 employment 폭 변수를 추가하고 GS/LS Ess. `left` 오프셋에 합산한다. DB·필터 변경 없음.

**Tech Stack:** Laravel 13, Livewire 4, Blade, Tailwind CSS 4, PHPUnit 12

**Spec:** `docs/superpowers/specs/2026-07-27-teacher-support-employment-type-column-design.md`

## Global Constraints

- 헤더: `고용형태`
- 값: `Full Time` / `Part Time` / `미지정` (`TeacherEmploymentType::label()`)
- 컬럼 위치: 직급 다음, GS Ess. 이전
- sticky 컬럼으로 삽입
- 모바일: 직급 배지 옆
- DB migration 금지
- 필터·정렬·편집 폼 변경 금지

## File Map

| File | Role |
|------|------|
| `tests/Feature/CoachTeacherSupportListTest.php` | 목록·미지정 표시 assert |
| `resources/views/livewire/coach-teacher-support-list.blade.php` | 데스크톱 th/td/col |
| `resources/views/partials/coach/teacher-support-mobile-card.blade.php` | 모바일 배지 |
| `resources/css/app.css` | sticky width / left / hover |

---

### Task 1: Feature test for employment type column

**Files:**
- Modify: `tests/Feature/CoachTeacherSupportListTest.php`
- Test: same file

**Interfaces:**
- Consumes: `createTeacher()`, `createInstitution()`, `createAdminUser()`, `TeacherEmploymentType`
- Produces: `test_list_shows_employment_type_after_position()` covering Full Time + 미지정

- [ ] **Step 1: Write the failing test**

```php
use App\Enums\TeacherEmploymentType;

public function test_list_shows_employment_type_after_position(): void
{
    $admin = $this->createAdminUser();
    $year = now()->year;

    $this->createInstitution('SK100', '고용형태기관', 'Coach A');
    $this->createTeacher('SK100', '정규교사', [
        'Position' => '교사',
        'EmploymentType' => TeacherEmploymentType::FullTime->value,
        '_1st_Support_Date' => "{$year}-03-10",
    ]);
    $this->createTeacher('SK100', '미지정교사', [
        'Position' => '교사',
        'EmploymentType' => TeacherEmploymentType::Unspecified->value,
        '_1st_Support_Date' => "{$year}-03-11",
    ]);

    Livewire::actingAs($admin)
        ->test(CoachTeacherSupportList::class)
        ->assertSee('고용형태')
        ->assertSee('정규교사')
        ->assertSee('Full Time')
        ->assertSee('미지정교사')
        ->assertSee('미지정');
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=test_list_shows_employment_type_after_position`

Expected: FAIL (헤더/라벨 미표시 또는 assertSee 실패)

- [ ] **Step 3: Commit (optional if user requested)**

```bash
git add tests/Feature/CoachTeacherSupportListTest.php
git commit -m "$(cat <<'EOF'
test: 교사 지원 현황 고용형태 컬럼 표시를 검증한다

EOF
)"
```

---

### Task 2: Desktop table + sticky CSS + mobile badge

**Files:**
- Modify: `resources/views/livewire/coach-teacher-support-list.blade.php` (colgroup, thead, tbody after position)
- Modify: `resources/views/partials/coach/teacher-support-mobile-card.blade.php`
- Modify: `resources/css/app.css` (width var, sticky left, hover)

**Interfaces:**
- Consumes: `$teacher->EmploymentType`, `App\Enums\TeacherEmploymentType`
- Produces: sticky column `coach-support-sticky-employment`, mobile badge next to Position

- [ ] **Step 1: Blade — desktop column**

In `colgroup`, after `coach-support-col-position`:

```blade
<col class="coach-support-col-employment">
```

After position `<th>`:

```blade
<th class="coach-support-sticky-employment coach-support-sticky-employment--head px-2 py-1.5 text-left">고용형태</th>
```

After position `<td>`:

```blade
<td class="coach-support-sticky-employment px-3 py-2 align-middle">
    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium text-gray-700">
        {{ \App\Enums\TeacherEmploymentType::fromMixed($teacher->EmploymentType)->label() }}
    </span>
</td>
```

- [ ] **Step 2: Blade — mobile card**

After Position badge block in `teacher-support-mobile-card.blade.php`:

```blade
<span class="inline-flex shrink-0 items-center rounded px-2 py-0.5 text-[11px] font-medium bg-gray-100 text-gray-700">
    {{ \App\Enums\TeacherEmploymentType::fromMixed($teacher->EmploymentType)->label() }}
</span>
```

- [ ] **Step 3: CSS sticky**

Add `--coach-support-employment-width: 5rem;` and include it in `--coach-support-sticky-width`.

Add `.coach-support-col-employment` width rules (mirror position).

Add `.coach-support-sticky-employment` with:

```css
left: calc(
    var(--coach-support-sk-width) + var(--coach-support-inst-width) +
        var(--coach-support-name-width) + var(--coach-support-position-width)
);
```

Update GS Ess. sticky `left` to also add `var(--coach-support-employment-width)`.

Update LS Ess. sticky `left` similarly.

Add employment to hover sticky background rule (same as position).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=test_list_shows_employment_type_after_position`

Expected: PASS

- [ ] **Step 5: Pint + verify related suite**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact --filter=CoachTeacherSupportListTest
```

- [ ] **Step 6: Commit (when user asks)**

```bash
git add resources/views/livewire/coach-teacher-support-list.blade.php \
  resources/views/partials/coach/teacher-support-mobile-card.blade.php \
  resources/css/app.css \
  tests/Feature/CoachTeacherSupportListTest.php \
  docs/superpowers/specs/2026-07-27-teacher-support-employment-type-column-design.md \
  docs/superpowers/plans/2026-07-27-teacher-support-employment-type-column.md
git commit -m "$(cat <<'EOF'
feat: 교사 지원 현황에 고용형태 컬럼을 표시한다

직급 다음 sticky 컬럼과 모바일 배지로 EmploymentType을
바로 확인할 수 있게 한다.

EOF
)"
```

---

## Spec coverage (self-review)

| Spec requirement | Task |
|------------------|------|
| Desktop column after 직급 | Task 2 |
| Labels Full Time / Part Time / 미지정 | Task 1–2 |
| Sticky | Task 2 CSS |
| Mobile badge | Task 2 |
| Feature test | Task 1 |
| No DB / filter | Out of scope — not in tasks |
