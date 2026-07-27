# 전체 기관 보기 교사 최신 지원순 정렬 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 교사 지원 현황의 「전체 기관 보기」에서 기관명 정렬 대신, 「최신 지원 보기」와 동일한 교사 단위 최신 지원일 내림차순으로 정렬하고 미지원 교사는 뒤에 둔다.

**Architecture:** `CoachTeacherSupportList::applyTeacherListOrdering()`의 `showAllInstitutionsView` 전용 기관명 `orderByRaw` 분기를 제거하고, 항상 `TeacherSupportListActivity::applyLatestSupportOrdering()`만 호출한다. 신규 정렬 로직은 만들지 않는다.

**Tech Stack:** Laravel 13, Livewire 4, PHPUnit 12, `TeacherSupportListActivity`

**Spec:** `docs/superpowers/specs/2026-07-27-coach-teacher-support-all-institutions-sort-design.md`

## Global Constraints

- 방식 B만: 교사 단위 정렬 (기관 묶음 미보장)
- 미지원 교사는 목록에서 **제외하지 않음** (정렬만 후순위)
- UI 라벨·KPI·담당 코치 필터·최신 지원 보기 동작은 변경하지 않음
- 커밋은 사용자 요청 시에만 (이 플랜의 Commit 스텝은 사용자가 커밋을 요청한 경우에만 실행)

---

## File Structure

| File | Role |
|------|------|
| `app/Livewire/CoachTeacherSupportList.php` | `applyTeacherListOrdering()` — 전체 보기도 최신 지원 정렬 |
| `tests/Feature/CoachTeacherSupportListTest.php` | 전체 보기 정렬 기대값 교체 |
| `app/Support/TeacherSupportListActivity.php` | **읽기만** — 기존 `applyLatestSupportOrdering` 재사용 |

---

### Task 1: 전체 기관 보기 정렬을 최신 지원순으로 변경

**Files:**
- Modify: `tests/Feature/CoachTeacherSupportListTest.php` (`test_teachers_ordered_by_institution_name_in_all_institutions_view` 교체)
- Modify: `app/Livewire/CoachTeacherSupportList.php` (`applyTeacherListOrdering`, 약 2976–2994행)

**Interfaces:**
- Consumes: `TeacherSupportListActivity::applyLatestSupportOrdering(Builder $query, ?int $year): void`
- Consumes: 테스트 헬퍼 `createInstitution`, `createTeacher(..., forLatestView: false)` (미지원 교사용)
- Produces: 전체 기관 보기 목록 정렬 = 최신 지원 보기와 동일 SQL 경로

- [ ] **Step 1: 실패하는 테스트로 교체**

`test_teachers_ordered_by_institution_name_in_all_institutions_view` 를 아래 메서드로 **교체**(이름 변경 포함):

```php
public function test_all_institutions_view_orders_by_latest_support_date_with_unsupported_last(): void
{
    $admin = $this->createAdminUser();
    $year = now()->year;

    $this->createInstitution('SK001', '한국기관', 'Coach A');
    $this->createInstitution('SK002', '가나기관', 'Coach A');

    // 더 오래된 지원 — 기관명으로는 '가나'가 앞이지만, 최신 지원순이면 뒤
    $this->createTeacher('SK002', '이교사', [
        '_1st_Support_Date' => "{$year}-03-10",
    ]);
    // 더 최근 지원
    $this->createTeacher('SK001', '김교사', [
        '_1st_Support_Date' => "{$year}-05-21",
    ]);
    // 지원 없음 — 맨 뒤
    $this->createTeacher('SK001', '미지원교사', forLatestView: false);

    $names = collect(
        Livewire::actingAs($admin)
            ->test(CoachTeacherSupportList::class)
            ->set('showAllInstitutionsView', true)
            ->set('filterYear', $year)
            ->viewData('teachers')
            ->items()
    )->pluck('Name')->all();

    $this->assertSame(['김교사', '이교사', '미지원교사'], $names);
}
```

기존 `test_teachers_ordered_by_institution_name_in_all_institutions_view` 메서드 본문·메서드명은 삭제하고 위 메서드만 남긴다.

- [ ] **Step 2: 테스트가 실패하는지 확인**

Run:

```bash
php artisan test --compact --filter=test_all_institutions_view_orders_by_latest_support_date_with_unsupported_last
```

Expected: FAIL — 현재는 기관명순이라 `['이교사', '김교사', '미지원교사']` 또는 유사 순서가 나와 assertion 실패.

- [ ] **Step 3: 최소 구현**

`app/Livewire/CoachTeacherSupportList.php` 의 `applyTeacherListOrdering` 전체를 아래로 교체:

```php
/**
 * @param  Builder<Teacher>  $query
 */
private function applyTeacherListOrdering(Builder $query): void
{
    TeacherSupportListActivity::applyLatestSupportOrdering($query, $this->resolvedFilterYear());
}
```

참고:
- `showAllInstitutionsView` 분기와 `S_AccountName` 기관명 `orderByRaw` 블록은 **삭제**한다.
- `sqlNormalizedTeacherSkCodeExpression()` 가 다른 곳에서 쓰이지 않으면 그대로 둔다(이 태스크에서 정리하지 않음 — 범위 밖). 쓰이는지 확인 후, **이 메서드만** ordering에서 제거된 경우라도 다른 호출이 있으면 유지.

- [ ] **Step 4: 테스트 통과 확인**

Run:

```bash
php artisan test --compact --filter=test_all_institutions_view_orders_by_latest_support_date_with_unsupported_last
```

Expected: PASS

추가로 최신 지원 보기 회귀:

```bash
php artisan test --compact --filter=test_latest_support_view_orders_by_latest_support_date
```

Expected: PASS

전체 보기 관련 스모크:

```bash
php artisan test --compact --filter=test_latest_support_view_hides_teachers_without_support_history
```

Expected: PASS (전체 보기 전환 시 미지원이 여전히 보임)

- [ ] **Step 5: Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 6: Commit (사용자 요청 시에만)**

사용자가 커밋을 요청한 경우에만:

```bash
git add app/Livewire/CoachTeacherSupportList.php tests/Feature/CoachTeacherSupportListTest.php docs/superpowers/specs/2026-07-27-coach-teacher-support-all-institutions-sort-design.md docs/superpowers/plans/2026-07-27-coach-teacher-support-all-institutions-sort.md
git commit -m "$(cat <<'EOF'
fix: 전체 기관 보기 목록을 최신 지원순으로 정렬한다

기관명순이면 미지원 교사가 중간에 섞여 탐색이 어렵다.
최신 지원 보기와 동일한 정렬을 재사용한다.
EOF
)"
```

---

## Spec coverage (self-review)

| Spec 요구 | Task |
|-----------|------|
| 전체 보기 = `applyLatestSupportOrdering` | Task 1 Step 3 |
| 미지원 교사 유지 + 후순위 | Task 1 Step 1 테스트 |
| UI/KPI/필터 미변경 | 코드 변경 없음 |
| 최신 보기 회귀 없음 | Task 1 Step 4 |
| 기관명 테스트 교체 | Task 1 Step 1 |

Placeholder scan: 없음.  
`sqlNormalizedTeacherSkCodeExpression` 미사용 정리는 YAGNI로 이번 플랜 제외.
