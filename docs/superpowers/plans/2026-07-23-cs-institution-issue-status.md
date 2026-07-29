# CS 기관 이슈 — 교사 선택 + 현황형 조회 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** CS 기관 이슈 작성에 선택적 교사(`Target`)를 넣고, 기관→교사(기관 공통) 현황 화면을 추가하되 기존 평평한 목록은 유지한다.

**Architecture:** 기존 `SupportCreateForm` 이슈 모드에 Coach와 동일한 `formTeacherId` / `institutionTeachers` 패턴을 얇게 붙인다. 현황은 새 Livewire `InstitutionIssueStatus` + `InstitutionIssueStatusAggregator`로 `onlyIssues()` 결과를 메모리에서 기관·Target 그룹핑한다. Coach KPI/목록 코드는 건드리지 않는다.

**Tech Stack:** Laravel 13, Livewire 4, Blade, PHPUnit, Alpine.js(아코디언)

## Global Constraints

- DB 마이그레이션 없음 — `Target` 문자열만 사용 (`teacher_id` FK 없음)
- 기존 `/institution-issues` 평평한 목록 유지
- Coach KPI / `CoachTeacherSupportList` / `CoachTeamSupport*` 코드 수정 금지
- 권한: 기존 Gate `accessCsTeamFeatures` 재사용 (새 Gate 없음)
- 현황 1차: 상세 모달·수정·삭제 없음
- 브랜치: `feature/cs-institution-issue-status` (스펙 커밋 `52ab3e1` 기반)
- 스펙: `docs/superpowers/specs/2026-07-23-cs-institution-issue-status-design.md`

---

## File Structure

| 파일 | 역할 |
|------|------|
| `app/Livewire/SupportCreateForm.php` | 이슈 저장 시 `Target` 반영; 교사 선택은 기존 `formTeacherId` 재사용 |
| `resources/views/livewire/support-create-form.blade.php` | 이슈 모드 교사 드롭다운 UI |
| `app/Livewire/InstitutionIssueList.php` | (필요 시) 검색 placeholder용 — 쿼리는 기존 + Target 검색 |
| `resources/views/livewire/institution-issue-list.blade.php` | 관련 교사 컬럼 |
| `app/Models/SupportRecord.php` | `scopeKeyword`에 `Target` 포함 |
| `app/Support/InstitutionIssueStatusAggregator.php` | **신규** 기관·교사 그룹핑 |
| `app/Livewire/InstitutionIssueStatus.php` | **신규** 현황 Livewire |
| `resources/views/livewire/institution-issue-status.blade.php` | **신규** 아코디언 UI |
| `resources/views/pages/institution-issues/status.blade.php` | **신규** 페이지 |
| `routes/web.php` | `institution-issues.status` 라우트 |
| `resources/views/partials/sidebar-cs-team-block.blade.php` | 메뉴 2개 추가 |
| `tests/Feature/SupportCreateFormTest.php` | 이슈+교사 저장·초기화 테스트 |
| `tests/Feature/InstitutionIssueTest.php` | 목록 컬럼·사이드바·현황 라우트/그룹핑 |

---

### Task 1: 이슈 작성 — 선택적 교사 → `Target` 저장

**Files:**
- Modify: `app/Livewire/SupportCreateForm.php` (`saveInstitutionIssue`)
- Modify: `resources/views/livewire/support-create-form.blade.php` (issue 섹션)
- Modify: `tests/Feature/SupportCreateFormTest.php`

**Interfaces:**
- Consumes: 기존 `formTeacherId`, `formTarget`, `updatedFormTeacherId()`, `institutionTeachers()`, `selectInstitution()`(이미 `formTeacherId` 초기화)
- Produces: 이슈 저장 시 `Target` = 교사명 또는 null/빈값

- [ ] **Step 1: Write the failing tests**

`tests/Feature/SupportCreateFormTest.php`에 추가 (기존 `createSupportTables`에 Teacher 테이블이 없으면 테스트 내 생성):

```php
public function test_cs_issue_mode_saves_without_teacher_leaving_target_empty(): void
{
    Institution::query()->create([
        'SKcode' => 'SK-ISSUE-T0',
        'AccountName' => '교사없음기관',
    ]);

    $cs = User::factory()->create(['team' => 'CS']);

    Livewire::actingAs($cs)
        ->withQueryParams(['team_menu' => 'cs', 'report_mode' => 'issue'])
        ->test(SupportCreateForm::class)
        ->call('selectInstitution', 'SK-ISSUE-T0')
        ->set('formIssue', '기관 공통 이슈')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('S_SupportInfo_Account', [
        'SK_Code' => 'SK-ISSUE-T0',
        'Issue' => '기관 공통 이슈',
        'Target' => null,
        'record_kind' => 'issue',
    ]);
}

public function test_cs_issue_mode_saves_selected_teacher_name_to_target(): void
{
    Institution::query()->create([
        'SKcode' => 'SK-ISSUE-T1',
        'AccountName' => '교사선택기관',
    ]);

    $teacherId = Teacher::query()->create([
        'SK_Code' => 'SK-ISSUE-T1',
        'Name' => '김교사',
    ])->ID;

    $cs = User::factory()->create(['team' => 'CS']);

    Livewire::actingAs($cs)
        ->withQueryParams(['team_menu' => 'cs', 'report_mode' => 'issue'])
        ->test(SupportCreateForm::class)
        ->call('selectInstitution', 'SK-ISSUE-T1')
        ->set('formTeacherId', $teacherId)
        ->assertSet('formTarget', '김교사')
        ->set('formIssue', '교사 관련 이슈')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('S_SupportInfo_Account', [
        'SK_Code' => 'SK-ISSUE-T1',
        'Issue' => '교사 관련 이슈',
        'Target' => '김교사',
        'record_kind' => 'issue',
    ]);
}

public function test_cs_issue_mode_resets_teacher_when_institution_changes(): void
{
    Institution::query()->create(['SKcode' => 'SK-A', 'AccountName' => '기관A']);
    Institution::query()->create(['SKcode' => 'SK-B', 'AccountName' => '기관B']);

    $teacherId = Teacher::query()->create([
        'SK_Code' => 'SK-A',
        'Name' => '박교사',
    ])->ID;

    $cs = User::factory()->create(['team' => 'CS']);

    Livewire::actingAs($cs)
        ->withQueryParams(['team_menu' => 'cs', 'report_mode' => 'issue'])
        ->test(SupportCreateForm::class)
        ->call('selectInstitution', 'SK-A')
        ->set('formTeacherId', $teacherId)
        ->assertSet('formTarget', '박교사')
        ->call('selectInstitution', 'SK-B')
        ->assertSet('formTeacherId', null)
        ->assertSet('formTarget', '');
}
```

테스트에 `S_Teacher`(또는 프로젝트 Teacher 테이블명) 스키마가 없으면 `SupportCreateFormTest`의 기존 Teacher 생성 패턴을 따른다. (`ContactList*` / `StoreTeacherVisit*`의 `Teacher::query()->create` 컬럼 참고)

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact --filter=test_cs_issue_mode_saves_selected_teacher_name_to_target`

Expected: FAIL (`Target` not saved / property assert fail)

- [ ] **Step 3: Implement save + UI**

`saveInstitutionIssue()` create attributes에 추가:

```php
'Target' => filled($this->formTarget) ? (string) $this->formTarget : null,
```

`support-create-form.blade.php`의 `@elseif($reportMode === 'issue')` 블록 **맨 앞**(발생일 그리드 전)에:

```blade
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            관련 교사 <span class="text-xs font-normal text-gray-400">(선택)</span>
        </label>
        <select wire:model.live="formTeacherId"
                wire:key="issue-teacher-{{ $formSkCode }}"
                @disabled(!$institutionSelected)
                class="w-full py-1.5 px-3 text-sm border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500
                       {{ $institutionSelected ? 'border-gray-300' : 'border-gray-200 bg-gray-50 text-gray-400 cursor-not-allowed' }}">
            <option value="">선택 안 함 (기관 공통)</option>
            @foreach($institutionTeachers as $teacher)
                <option value="{{ $teacher->ID }}">{{ $teacher->Name }}</option>
            @endforeach
        </select>
        @if($institutionSelected && $institutionTeachers->isEmpty())
            <p class="mt-1 text-xs text-amber-600">등록된 교사가 없어도 기관 공통으로 저장할 수 있습니다.</p>
        @endif
    </div>
</div>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact --filter='test_cs_issue_mode_saves_without_teacher|test_cs_issue_mode_saves_selected_teacher|test_cs_issue_mode_resets_teacher'`

Expected: PASS

- [ ] **Step 5: Commit** (사용자 요청 시에만)

```bash
git add app/Livewire/SupportCreateForm.php resources/views/livewire/support-create-form.blade.php tests/Feature/SupportCreateFormTest.php
git commit -m "$(cat <<'EOF'
feat: 기관 이슈 작성 시 선택적 교사(Target) 저장

EOF
)"
```

---

### Task 2: 평평한 목록 — 관련 교사 컬럼 + Target 검색

**Files:**
- Modify: `app/Models/SupportRecord.php` (`scopeKeyword`)
- Modify: `resources/views/livewire/institution-issue-list.blade.php`
- Modify: `tests/Feature/InstitutionIssueTest.php`

**Interfaces:**
- Consumes: `SupportRecord::$Target`, `scopeKeyword`
- Produces: 목록에 「관련 교사」열; 검색에 교사명 포함

- [ ] **Step 1: Write the failing test**

```php
public function test_issue_list_shows_teacher_or_institution_common_label(): void
{
    Institution::query()->create(['SKcode' => 'SK-L1', 'AccountName' => '목록기관']);

    SupportRecord::query()->create([
        'Year' => (int) now()->format('Y'),
        'SK_Code' => 'SK-L1',
        'Account_Name' => '목록기관',
        'TR_Name' => 'CS담당',
        'Support_Date' => now()->format('Y-m-d'),
        'Meet_Time' => '10:00:00',
        'Support_Type' => '기관이슈',
        'Target' => '이교사',
        'Issue' => '교사 이슈',
        'record_kind' => SupportRecord::KIND_ISSUE,
        'CreatedDate' => now(),
    ]);

    SupportRecord::query()->create([
        'Year' => (int) now()->format('Y'),
        'SK_Code' => 'SK-L1',
        'Account_Name' => '목록기관',
        'TR_Name' => 'CS담당',
        'Support_Date' => now()->format('Y-m-d'),
        'Meet_Time' => '11:00:00',
        'Support_Type' => '기관이슈',
        'Target' => null,
        'Issue' => '공통 이슈',
        'record_kind' => SupportRecord::KIND_ISSUE,
        'CreatedDate' => now(),
    ]);

    $user = User::factory()->create(['team' => 'CS']);

    Livewire::actingAs($user)
        ->test(InstitutionIssueList::class)
        ->assertSee('이교사')
        ->assertSee('기관 공통')
        ->assertSee('교사 이슈')
        ->assertSee('공통 이슈');
}
```

- [ ] **Step 2: Run test — expect FAIL**

Run: `php artisan test --compact --filter=test_issue_list_shows_teacher_or_institution_common_label`

- [ ] **Step 3: Implement**

`scopeKeyword` searchable columns에 `'Target'` 추가:

```php
['Account_Name', 'Issue', 'TO_Account', 'SK_Code', 'Target'],
```

목록 테이블 헤더에 「관련 교사」 컬럼 (기관명 다음 권장):

```blade
<th ...>관련 교사</th>
...
<td class="px-3 py-2.5 text-gray-600 text-xs">
    {{ filled($record->Target) ? $record->Target : '기관 공통' }}
</td>
```

empty colspan +1, placeholder를 `기관명, SK코드, 교사명, 이슈 내용 검색...`으로 갱신.

기존 테스트 `test_cs_sidebar_does_not_show_issue_menu`는 Task 4에서 교체.

- [ ] **Step 4: Run test — expect PASS**

- [ ] **Step 5: Commit** (요청 시)

---

### Task 3: 현황 Aggregator + Livewire + 페이지

**Files:**
- Create: `app/Support/InstitutionIssueStatusAggregator.php`
- Create: `app/Livewire/InstitutionIssueStatus.php`
- Create: `resources/views/livewire/institution-issue-status.blade.php`
- Create: `resources/views/pages/institution-issues/status.blade.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/InstitutionIssueTest.php`

**Interfaces:**
- Consumes: `SupportRecord::onlyIssues()`, `ofYear()`, `keyword()`, `urgent()`, `orderedForList()`
- Produces:

```php
/**
 * @return list<array{
 *   sk_code: string,
 *   account_name: string,
 *   issue_count: int,
 *   urgent_count: int,
 *   groups: list<array{
 *     label: string,
 *     is_institution_common: bool,
 *     issues: list<SupportRecord>
 *   }>
 * }>
 */
public function groupByInstitutionAndTarget(Collection $records): array
```

그룹 규칙:
- 기관 키: `SK_Code` (빈값이면 `Account_Name` fallback)
- Target blank → label `기관 공통`, `is_institution_common: true`, 그룹 맨 앞
- Target 있으면 교사명 그룹, 이름 정렬
- 이슈 행 정렬: 발생일·시간 desc (쿼리 `orderedForList` 유지 후 그룹 내 유지)

- [ ] **Step 1: Write failing tests**

```php
public function test_issue_status_route_requires_cs_gate(): void
{
    $cs = User::factory()->create(['team' => 'CS']);
    $coach = User::factory()->create(['team' => 'Coach']);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($cs)->get('/institution-issues/status')->assertOk();
    $this->actingAs($admin)->get('/institution-issues/status')->assertOk();
    $this->actingAs($coach)->get('/institution-issues/status')->assertForbidden();
}

public function test_issue_status_groups_by_institution_and_teacher(): void
{
    Institution::query()->create(['SKcode' => 'SK-S1', 'AccountName' => '현황기관']);

    SupportRecord::query()->create([/* Target null, Issue '공통A', ... KIND_ISSUE */]);
    SupportRecord::query()->create([/* Target '최교사', Issue '교사B', ... */]);

    $cs = User::factory()->create(['team' => 'CS']);

    Livewire::actingAs($cs)
        ->test(\App\Livewire\InstitutionIssueStatus::class)
        ->assertSee('현황기관')
        ->assertSee('기관 공통')
        ->assertSee('최교사')
        ->assertSee('공통A')
        ->assertSee('교사B');
}
```

- [ ] **Step 2: Run — expect FAIL (route/component missing)**

- [ ] **Step 3: Implement aggregator**

```php
<?php

namespace App\Support;

use App\Models\SupportRecord;
use Illuminate\Support\Collection;

class InstitutionIssueStatusAggregator
{
    /**
     * @param  Collection<int, SupportRecord>  $records
     * @return list<array{sk_code: string, account_name: string, issue_count: int, urgent_count: int, groups: list<array{label: string, is_institution_common: bool, issues: list<SupportRecord>}>}>
     */
    public function groupByInstitutionAndTarget(Collection $records): array
    {
        $byInstitution = $records->groupBy(function (SupportRecord $record): string {
            $sk = trim((string) ($record->SK_Code ?? ''));

            return $sk !== '' ? $sk : 'name:'.trim((string) ($record->Account_Name ?? ''));
        });

        $rows = [];

        foreach ($byInstitution as $institutionKey => $institutionRecords) {
            /** @var Collection<int, SupportRecord> $institutionRecords */
            $first = $institutionRecords->first();
            $groupsMap = [];

            foreach ($institutionRecords as $record) {
                $target = trim((string) ($record->Target ?? ''));
                $isCommon = $target === '';
                $label = $isCommon ? '기관 공통' : $target;
                $groupKey = $isCommon ? '__common__' : 't:'.$target;

                if (! isset($groupsMap[$groupKey])) {
                    $groupsMap[$groupKey] = [
                        'label' => $label,
                        'is_institution_common' => $isCommon,
                        'issues' => [],
                    ];
                }

                $groupsMap[$groupKey]['issues'][] = $record;
            }

            uasort($groupsMap, function (array $a, array $b): int {
                if ($a['is_institution_common'] !== $b['is_institution_common']) {
                    return $a['is_institution_common'] ? -1 : 1;
                }

                return strcmp($a['label'], $b['label']);
            });

            $rows[] = [
                'sk_code' => (string) ($first?->SK_Code ?? ''),
                'account_name' => (string) ($first?->Account_Name ?? ''),
                'issue_count' => $institutionRecords->count(),
                'urgent_count' => $institutionRecords->where('is_urgent', true)->count(),
                'groups' => array_values($groupsMap),
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp($a['account_name'], $b['account_name']));

        return $rows;
    }
}
```

- [ ] **Step 4: Implement Livewire + views + route**

`InstitutionIssueStatus`:
- props: `filterYear`, `search`, `filterUrgentOnly`, `expandedSkCodes` (array)
- `mount`: `Gate::authorize('accessCsTeamFeatures')`
- `render`: 이슈 전부 조회(페이지네이션 없이 기관 단위 아코디언; 과도하면 기관 단위만 페이지 — 1차는 필터 후 전량 메모리 그룹핑, N+1 없음)
- toggle expand: Alpine 또는 `$expandedSkCodes`

Route (`routes/web.php`, index 바로 아래):

```php
Route::get('/institution-issues/status', function () {
    return view('pages.institution-issues.status');
})->middleware('can:accessCsTeamFeatures')->name('institution-issues.status');
```

Page:

```blade
<x-layouts.app title="기관 이슈 현황">
    <livewire:institution-issue-status />
</x-layouts.app>
```

Blade UX: 필터 카드(년도/검색/긴급/작성 링크) + 기관 아코디언 행 + 펼침 시 기관 공통/교사 그룹 + 이슈 행(발생일·시간·긴급·담당 CS·요약·상태). 빈 상태 문구 분리.

작성 링크: 기존과 동일  
`TeamMenuContext::route('supports.create', ['report_mode' => 'issue'], null, 'cs')`

- [ ] **Step 5: Run tests — expect PASS**

Run: `php artisan test --compact --filter='test_issue_status_'`

- [ ] **Step 6: Commit** (요청 시)

---

### Task 4: CS 사이드바 메뉴 + 기존 테스트 갱신

**Files:**
- Modify: `resources/views/partials/sidebar-cs-team-block.blade.php`
- Modify: `tests/Feature/InstitutionIssueTest.php`

- [ ] **Step 1: Update sidebar test expectation**

`test_cs_sidebar_does_not_show_issue_menu` → `test_cs_sidebar_shows_issue_and_status_menus`:

```php
public function test_cs_sidebar_shows_issue_and_status_menus(): void
{
    $cs = User::factory()->create(['team' => 'CS']);

    $this->actingAs($cs)
        ->get('/institutions')
        ->assertOk()
        ->assertSee('기관 이슈')
        ->assertSee('기관 이슈 현황')
        ->assertSee('/institution-issues', false)
        ->assertSee('/institution-issues/status', false);
}
```

- [ ] **Step 2: Add menus to CS sidebar**

`sharedTeamMenus` 배열에 (기관지원보고서 다음):

```php
['label' => '기관 이슈', 'path' => '/institution-issues', 'route' => 'institution-issues.index', 'icon' => 'document'],
['label' => '기관 이슈 현황', 'path' => '/institution-issues/status', 'route' => 'institution-issues.status', 'icon' => 'clipboard'],
```

`sidebar-shared-team-menus`가 `route` 이름으로 active 처리하는지 확인하고, 안 되면 기존 store 메뉴처럼 수동 링크 패턴 사용.

- [ ] **Step 3: Run sidebar + full related suite**

Run:

```bash
php artisan test --compact tests/Feature/InstitutionIssueTest.php --filter='issue'
php artisan test --compact --filter='test_cs_issue_mode_'
```

Expected: PASS

- [ ] **Step 4: Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 5: Commit** (요청 시)

---

### Task 5: 검증 마무리

- [ ] **Step 1: 관련 테스트 일괄 실행**

```bash
php artisan test --compact tests/Feature/InstitutionIssueTest.php tests/Feature/SupportCreateFormTest.php
```

- [ ] **Step 2: (가능하면) `composer run verify`**

- [ ] **Step 3: 자체 코드 리뷰**
  - Coach KPI 파일 diff에 없는지
  - migration 파일 추가 없는지
  - 평평한 목록 라우트/컴포넌트 삭제 없는지
  - 수용 기준 9항 체크

---

## Spec coverage (self-review)

| 스펙 항목 | Task |
|-----------|------|
| 이슈 작성 선택적 교사 / Target | Task 1 |
| 기관 변경 시 교사 초기화 | Task 1 (기존 selectInstitution + 테스트) |
| 평평한 목록 유지 + 교사 컬럼 | Task 2 |
| Target 검색(교사명) | Task 2 keyword |
| 현황 화면 그룹핑 | Task 3 |
| 사이드바 메뉴 | Task 4 |
| CS Gate / Coach 현황 차단 | Task 3 route middleware |
| 마이그레이션 없음 | Global + Task 5 |
| Coach KPI 미수정 | Global |

## Placeholder scan

없음 (코드·명령·기대 결과 명시).

## Type consistency

- `formTeacherId` / `formTarget` / `Target` 컬럼명 일관
- Aggregator 반환 shape를 Livewire·Blade가 동일 키로 사용
- 라우트명 `institution-issues.status`
