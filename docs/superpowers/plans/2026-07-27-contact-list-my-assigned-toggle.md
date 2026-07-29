# ContactList 「내 담당만」 토글 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** `/contacts`(`ContactList`)에 「내 담당만」 토글을 추가해, ON일 때 로그인 사용자 팀의 담당 컬럼(CO/TR/CS)에 매칭되는 기관의 교직원만 목록·통계·엑셀에 보이게 한다.

**Architecture:** Livewire 상태 `myAssignedOnly`(기본 `false`)를 두고, ON일 때 Teacher 쿼리에 `whereHas('institution', …)`로 기존 `InstitutionAccountListQuery::applyCurrentUserManagerScope()`를 적용한다. 필터는 `filteredTeachersQuery`와 통계 쿼리·엑셀이 같은 경로를 쓰도록 `applyMyAssignedFilter` 헬퍼로 한곳에 모은다.

**Tech Stack:** Laravel 13, Livewire 4, Blade, PHPUnit, `InstitutionAccountListQuery`

**스펙:** `docs/superpowers/specs/2026-07-27-contact-list-my-assigned-toggle-design.md`

## Global Constraints

- DB 마이그레이션 없음
- 담당자 드롭다운 추가 금지 — 「내 담당만」 토글만
- 기본값 OFF — 기존 전체 목록 동작 유지
- 매칭은 **로그인 사용자 팀** 기준 (`team_menu` 쿼리로 컬럼 바꾸지 않음)
- Coach→`TR`, CS→`CS`, CO→`CO` (기존 `currentUserManagerColumn()`)
- 이름 별칭/매칭 로직을 ContactList에 새로 쓰지 말고 `InstitutionAccountListQuery` 재사용
- 진행 중인 `employment_type` 등 무관한 diff와 섞지 말 것 — 이 기능만 커밋
- 브랜치 권장: `feature/contact-list-my-assigned-toggle` (현재 dirty 작업과 분리 시)

## File Structure

| 파일 | 역할 |
|------|------|
| `app/Livewire/ContactList.php` | `myAssignedOnly` 상태, `updatingMyAssignedOnly`, `applyMyAssignedFilter`, 목록·통계 적용 |
| `resources/views/livewire/contact-list.blade.php` | 필터 카드에 「내 담당만」 UI |
| `tests/Feature/ContactListMyAssignedFilterTest.php` | OFF 회귀 + 팀별 ON + 통계/엑셀 범위 |

참고(읽기만): `app/Support/InstitutionAccountListQuery.php` (`applyCurrentUserManagerScope`, `currentUserManagerColumn`), `tests/Feature/ContactListTeamScopeTest.php` (시드 헬퍼 패턴)

---

### Task 1: 실패하는 Feature 테스트 작성

**Files:**
- Create: `tests/Feature/ContactListMyAssignedFilterTest.php`

**Interfaces:**
- Produces (예상 Livewire API — Task 2에서 구현):
  - `public bool $myAssignedOnly = false`
  - `updatingMyAssignedOnly(): void` — `resetPage()`
  - `applyMyAssignedFilter(Builder $query): void` — ON일 때만 manager scope

- [ ] **Step 1: 테스트 파일 생성**

`tests/Feature/ContactListTeamScopeTest.php`의 `createContactTables` / `seedTeacherWithAssignments` 패턴을 복사해 새 파일을 만든다.

```php
<?php

namespace Tests\Feature;

use App\Livewire\ContactList;
use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ContactListMyAssignedFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createContactTables();
    }

    public function test_my_assigned_filter_defaults_off_and_shows_all_teachers(): void
    {
        $coachUser = User::factory()->create([
            'team' => 'COACH',
            'name' => 'Current Coach',
            'email' => 'current.coach@example.com',
        ]);

        $this->seedTeacherWithAssignments(
            'SK-MINE',
            '내 담당 교사',
            ['TR' => 'Current Coach', 'CS' => null, 'CO' => null],
        );
        $this->seedTeacherWithAssignments(
            'SK-OTHER',
            '다른 담당 교사',
            ['TR' => 'Other Coach', 'CS' => null, 'CO' => null],
        );

        Livewire::actingAs($coachUser)
            ->test(ContactList::class)
            ->assertSet('myAssignedOnly', false)
            ->assertSee('내 담당 교사')
            ->assertSee('다른 담당 교사');
    }

    public function test_my_assigned_filter_on_for_coach_matches_tr_only(): void
    {
        $coachUser = User::factory()->create([
            'team' => 'COACH',
            'name' => 'Current Coach',
            'email' => 'current.coach@example.com',
        ]);

        $this->seedTeacherWithAssignments(
            'SK-MINE',
            '내 담당 교사',
            ['TR' => 'Current Coach', 'CS' => null, 'CO' => null],
        );
        $this->seedTeacherWithAssignments(
            'SK-OTHER',
            '다른 담당 교사',
            ['TR' => 'Other Coach', 'CS' => null, 'CO' => null],
        );
        // CS에만 본인 이름이 있어도 Coach 팀은 TR만 본다
        $this->seedTeacherWithAssignments(
            'SK-CS-NAME',
            'CS에만 매칭 교사',
            ['TR' => 'Other Coach', 'CS' => 'Current Coach', 'CO' => null],
        );

        Livewire::actingAs($coachUser)
            ->test(ContactList::class)
            ->set('myAssignedOnly', true)
            ->assertSee('내 담당 교사')
            ->assertDontSee('다른 담당 교사')
            ->assertDontSee('CS에만 매칭 교사');
    }

    public function test_my_assigned_filter_on_for_cs_matches_cs_only(): void
    {
        $csUser = User::factory()->create([
            'team' => 'CS',
            'name' => 'Current CS',
            'email' => 'current.cs@example.com',
        ]);

        $this->seedTeacherWithAssignments(
            'SK-CS-MINE',
            '내 CS 교사',
            ['TR' => null, 'CS' => 'Current CS', 'CO' => null],
        );
        $this->seedTeacherWithAssignments(
            'SK-CS-OTHER',
            '다른 CS 교사',
            ['TR' => null, 'CS' => 'Other CS', 'CO' => null],
        );
        $this->seedTeacherWithAssignments(
            'SK-TR-NAME',
            'TR에만 매칭 교사',
            ['TR' => 'Current CS', 'CS' => 'Other CS', 'CO' => null],
        );

        Livewire::actingAs($csUser)
            ->test(ContactList::class)
            ->set('myAssignedOnly', true)
            ->assertSee('내 CS 교사')
            ->assertDontSee('다른 CS 교사')
            ->assertDontSee('TR에만 매칭 교사');
    }

    public function test_my_assigned_filter_on_for_co_matches_co_only(): void
    {
        $coUser = User::factory()->create([
            'team' => 'CO',
            'name' => 'Current CO',
            'email' => 'current.co@example.com',
        ]);

        $this->seedTeacherWithAssignments(
            'SK-CO-MINE',
            '내 CO 교사',
            ['TR' => null, 'CS' => null, 'CO' => 'Current CO'],
        );
        $this->seedTeacherWithAssignments(
            'SK-CO-OTHER',
            '다른 CO 교사',
            ['TR' => null, 'CS' => null, 'CO' => 'Other CO'],
        );

        Livewire::actingAs($coUser)
            ->test(ContactList::class)
            ->set('myAssignedOnly', true)
            ->assertSee('내 CO 교사')
            ->assertDontSee('다른 CO 교사');
    }

    public function test_my_assigned_filter_applies_to_stats_counts(): void
    {
        $coachUser = User::factory()->create([
            'team' => 'COACH',
            'name' => 'Current Coach',
            'email' => 'current.coach.stats@example.com',
        ]);

        $this->seedTeacherWithAssignments(
            'SK-STAT-MINE',
            '통계 내 담당',
            ['TR' => 'Current Coach', 'CS' => null, 'CO' => null],
        );
        $this->seedTeacherWithAssignments(
            'SK-STAT-OTHER',
            '통계 다른 담당',
            ['TR' => 'Other Coach', 'CS' => null, 'CO' => null],
        );

        Livewire::actingAs($coachUser)
            ->test(ContactList::class)
            ->set('teacherStatusFilter', 'all')
            ->set('myAssignedOnly', true)
            ->assertViewHas('totalCount', 1)
            ->assertViewHas('activeCount', 1)
            ->assertViewHas('inactiveCount', 0);
    }

    public function test_my_assigned_filter_applies_to_excel_export(): void
    {
        $coachUser = User::factory()->create([
            'team' => 'COACH',
            'name' => 'Current Coach',
            'email' => 'current.coach.excel@example.com',
        ]);

        $this->seedTeacherWithAssignments(
            'SK-XLS-MINE',
            '엑셀 내 담당',
            ['TR' => 'Current Coach', 'CS' => null, 'CO' => null],
        );
        $this->seedTeacherWithAssignments(
            'SK-XLS-OTHER',
            '엑셀 다른 담당',
            ['TR' => 'Other Coach', 'CS' => null, 'CO' => null],
        );

        $now = now();
        \Carbon\Carbon::setTestNow($now);

        $component = Livewire::actingAs($coachUser)
            ->test(ContactList::class)
            ->set('teacherStatusFilter', 'all')
            ->set('myAssignedOnly', true)
            ->call('exportContactsExcel')
            ->assertFileDownloaded('교직원_연락처_'.$now->format('Ymd_His').'.xlsx');

        $xlsxBinary = base64_decode((string) data_get($component->effects, 'download.content'), true);
        $this->assertNotFalse($xlsxBinary);
        $this->assertNotSame('', $xlsxBinary);

        $tempPath = tempnam(sys_get_temp_dir(), 'contact-export-').'.xlsx';
        file_put_contents($tempPath, $xlsxBinary);

        try {
            $sheet = IOFactory::load($tempPath)->getActiveSheet();
            $exported = '';
            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $exported .= (string) $cell->getValue().' ';
                }
            }

            $this->assertStringContainsString('엑셀 내 담당', $exported);
            $this->assertStringNotContainsString('엑셀 다른 담당', $exported);
        } finally {
            @unlink($tempPath);
            \Carbon\Carbon::setTestNow();
        }
    }

    /**
     * @param  array{TR: string|null, CS: string|null, CO: string|null}  $assignments
     */
    private function seedTeacherWithAssignments(string $skCode, string $teacherName, array $assignments): void
    {
        Institution::query()->create([
            'SKcode' => $skCode,
            'AccountName' => $teacherName.' 기관',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => $skCode,
            'Account_Name' => $teacherName.' 기관',
            'TR' => $assignments['TR'],
            'CS' => $assignments['CS'],
            'CO' => $assignments['CO'],
        ]);

        Teacher::query()->create([
            'SK_Code' => $skCode,
            'Name' => $teacherName,
            'Email' => $skCode.'@example.com',
            'School_Name' => $teacherName.' 기관',
            'ClassInOut' => true,
            'Status' => '활성화',
        ]);
    }

    private function createContactTables(): void
    {
        Schema::dropIfExists('Teachers');
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('Customer_Type', 255)->nullable();
            $table->string('Affiliate', 255)->nullable();
            $table->string('Address', 255)->nullable();
        });

        Schema::create('Teachers', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100)->nullable();
            $table->string('Name', 190)->nullable();
            $table->string('Email', 190)->nullable();
            $table->string('Phone', 190)->nullable();
            $table->string('Position', 100)->nullable();
            $table->string('School_Name', 255)->nullable();
            $table->text('Description')->nullable();
            $table->string('Status', 50)->nullable();
            $table->string('EmploymentType', 32)->default('unspecified');
            $table->boolean('ClassInOut')->nullable();
            $table->date('GrapeSEEDEssentials')->nullable();
            $table->date('LittleSEEDEssentials')->nullable();
            $table->dateTime('Created_Date')->nullable();
        });
    }
}
```

- [ ] **Step 2: 실패 확인**

```bash
php artisan test --compact tests/Feature/ContactListMyAssignedFilterTest.php
```

Expected: FAIL — `myAssignedOnly` 프로퍼티 없음 또는 필터 미적용으로 `assertDontSee` / `assertViewHas` 실패.

- [ ] **Step 3: 커밋** (사용자에게 커밋 요청이 있을 때만)

```bash
git add tests/Feature/ContactListMyAssignedFilterTest.php
git commit -m "$(cat <<'EOF'
test: 교직원 연락처 내 담당만 필터 실패 테스트 추가

EOF
)"
```

---

### Task 2: ContactList 필터 로직 구현

**Files:**
- Modify: `app/Livewire/ContactList.php`
- Test: `tests/Feature/ContactListMyAssignedFilterTest.php`

**Interfaces:**
- Consumes: `InstitutionAccountListQuery::applyCurrentUserManagerScope(Builder $query): void`
- Produces:
  - `public bool $myAssignedOnly = false`
  - `public function updatingMyAssignedOnly(): void`
  - `private function applyMyAssignedFilter(Builder $query): void`

- [ ] **Step 1: import 추가**

파일 상단 use에 추가:

```php
use App\Support\InstitutionAccountListQuery;
```

(`CoachTeacherScope` use 근처)

- [ ] **Step 2: 상태·훅 추가**

`$teacherStatusFilter` 선언 바로 아래에:

```php
public bool $myAssignedOnly = false;
// 내 담당만: false=전체, true=팀 담당 컬럼 매칭 기관만
```

`updatingTeacherStatusFilter` 바로 아래에:

```php
public function updatingMyAssignedOnly(): void
{
    $this->resetPage();
}
```

- [ ] **Step 3: `applyMyAssignedFilter` 추가**

`applyContactVisibilityScope` 근처(또는 바로 아래)에:

```php
/**
 * @param  Builder<Teacher>  $query
 */
private function applyMyAssignedFilter(Builder $query): void
{
    if (! $this->myAssignedOnly) {
        return;
    }

    $query->whereHas('institution', function (Builder $institutionQuery): void {
        app(InstitutionAccountListQuery::class)
            ->applyCurrentUserManagerScope($institutionQuery);
    });
}
```

- [ ] **Step 4: 목록·통계에 연결**

`filteredTeachersQuery()`에서 `applyContactVisibilityScope` 호출 다음에:

```php
$this->applyMyAssignedFilter($teachersQuery);
```

`render()`의 stats 블록에서 `applyContactVisibilityScope($statsQuery)` 다음에:

```php
$this->applyMyAssignedFilter($statsQuery);
```

엑셀은 이미 `filteredTeachersQuery()`를 쓰므로 **추가 수정 없음**.

- [ ] **Step 5: 테스트 재실행**

```bash
php artisan test --compact tests/Feature/ContactListMyAssignedFilterTest.php
```

Expected: PASS (UI는 아직 없어도 `set('myAssignedOnly', true)`로 로직 검증 가능)

- [ ] **Step 6: Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: 커밋** (요청 시에만)

```bash
git add app/Livewire/ContactList.php tests/Feature/ContactListMyAssignedFilterTest.php
git commit -m "$(cat <<'EOF'
feat: 교직원 연락처에 내 담당만 필터 로직 추가

팀별 CO/TR/CS 매칭은 InstitutionAccountListQuery를 재사용해
기관리스트와 동일한 담당 판정을 쓴다.
EOF
)"
```

---

### Task 3: 필터 UI 추가

**Files:**
- Modify: `resources/views/livewire/contact-list.blade.php` (필터 카드, 대략 검색 입력과 「상태」 사이)
- Test: 기존 Feature 테스트 + 필요 시 UI assert

**Interfaces:**
- Consumes: `$myAssignedOnly` (bool)

- [ ] **Step 1: 「상태」 토글 앞에 UI 삽입**

`resources/views/livewire/contact-list.blade.php`에서 「상태」 `mochi-toggle-group` 바로 **앞**에 추가:

```blade
<div class="flex items-center gap-2 text-sm">
    <span class="text-gray-500">담당</span>
    <div class="mochi-toggle-group">
        <button type="button"
                wire:click="$set('myAssignedOnly', false)"
                aria-pressed="{{ $myAssignedOnly ? 'false' : 'true' }}"
                class="mochi-toggle-btn {{ ! $myAssignedOnly ? 'mochi-toggle-btn--active' : '' }}">
            전체
        </button>
        <button type="button"
                wire:click="$set('myAssignedOnly', true)"
                aria-pressed="{{ $myAssignedOnly ? 'true' : 'false' }}"
                class="mochi-toggle-btn {{ $myAssignedOnly ? 'mochi-toggle-btn--active' : '' }}">
            내 담당만
        </button>
    </div>
</div>
```

「상태」와 같은 토글 그룹 패턴을 유지해 레이아웃이 깨지지 않게 한다.

- [ ] **Step 2: UI 문자열 스모크 (선택)**

Task 1 테스트 중 하나에 `->assertSee('내 담당만')`를 기본 OFF 테스트에 추가해도 된다.

```php
->assertSee('내 담당만')
```

- [ ] **Step 3: 관련 테스트 실행**

```bash
php artisan test --compact tests/Feature/ContactListMyAssignedFilterTest.php tests/Feature/ContactListTeamScopeTest.php
```

Expected: PASS

- [ ] **Step 4: 커밋** (요청 시에만)

```bash
git add resources/views/livewire/contact-list.blade.php tests/Feature/ContactListMyAssignedFilterTest.php
git commit -m "$(cat <<'EOF'
feat: 교직원 연락처 필터에 내 담당만 토글 UI 추가

EOF
)"
```

---

### Task 4: 검증·마무리

**Files:**
- 변경분 전체

- [ ] **Step 1: 영향 테스트 + Pint**

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact \
  tests/Feature/ContactListMyAssignedFilterTest.php \
  tests/Feature/ContactListTeamScopeTest.php \
  tests/Feature/ContactListInstitutionNameTest.php \
  tests/Feature/ContactListClassParticipationTest.php
```

Expected: 모두 PASS

- [ ] **Step 2: (선택) `composer run verify`**

전체 스위트가 필요하면 사용자 확인 후 실행.

- [ ] **Step 3: 수동 확인 체크리스트**

1. `/contacts` 접속 → 「담당: 전체 | 내 담당만」 보임, 기본은 전체 활성
2. Coach 계정으로 「내 담당만」 ON → 본인 TR 기관 교사만, 상단 건수 감소
3. OFF 복귀 → 전체 복원
4. ON 상태에서 엑셀 다운로드 → 동일 범위

- [ ] **Step 4: 스펙 상태 갱신 (선택)**

`docs/superpowers/specs/2026-07-27-contact-list-my-assigned-toggle-design.md`의 상태를 `구현 완료`로 바꾼다.

---

## Spec Coverage Self-Review

| 스펙 항목 | Task |
|-----------|------|
| 토글 UI, 기본 OFF | Task 3, Task 2 `$myAssignedOnly = false` |
| 팀→TR/CS/CO | Task 2 (`applyCurrentUserManagerScope`) + Task 1 팀별 테스트 |
| 목록·통계·엑셀 동일 조건 | Task 2 (목록/통계) + 엑셀은 `filteredTeachersQuery` + Task 1 테스트 |
| `InstitutionAccountListQuery` 재사용 | Task 2 |
| `team_menu`로 컬럼 변경 안 함 | Task 1이 user `team`만 사용 |
| 드롭다운/마이그레이션 없음 | 전 Task |
| 별칭 없으면 0건 | 기존 Query 동작 유지 (신규 로직 없음) |

## Placeholder / Consistency Check

- 엑셀 테스트는 `assertFileDownloaded` + `effects.download.content` base64 디코드 패턴으로 확정됨 (`ContactListInstitutionNameTest`와 동일).
- 프로퍼티명 일관: `myAssignedOnly` (bool) — 뷰·테스트·PHP 동일.
- 헬퍼명: `applyMyAssignedFilter`만 사용.
