# 직책 × 권한 매트릭스 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Setup에서 직책×권한 표를 편집하고, JOB 변경·표 저장·Artisan 시 비관리자 `users` 기능 플래그 7개를 표와 동기화한다 (방안 A).

**Architecture:** `job_title_permissions`가 직책별 표준 플래그를 보관한다. `JobTitlePermissionSynchronizer`가 단일 writer로 `users`의 7개 플래그를 복사한다. Gate·팀 스코프(ADR 0003)는 `users` 플래그를 계속 읽는다. `is_admin`은 사람 단위만 유지한다.

**Tech Stack:** Laravel 13, Livewire 4, PHPUnit 12, Eloquent, Artisan

**Spec:** `docs/superpowers/specs/2026-07-30-job-title-permission-matrix-design.md`  
**ADR:** `docs/adr/0007-job-title-permission-matrix.md`

## Global Constraints

- 방안 A만: 표 → `users` 플래그 복사. 요청마다 JOB 실시간 해석(방안 B) 금지
- `is_admin`은 매트릭스/동기화로 **절대** 부여·변경하지 않음 (사람 단위만)
- 배포·migrate 직후 **자동 전체 sync 금지**. 표가 비어 있으면 비관리자 7개 권한이 일괄 off될 수 있음
- 초기 역시드(기존 `users` 플래그 → 표 역추정) 없음
- JOB 매칭: `trim(employee.JOB) === job_title_permissions.job_code` (trim만, fuzzy 없음)
- `setup_manage = true`이면 동기화/저장 시 `setup_view = true` 강제
- Coach KPI·Store 반품·institution-coverage 등 **다른 WIP 파일 건드리지 말 것**
- 작업 브랜치: `feature/job-title-permission-matrix` — **main(또는 깨끗한 base)에서 신규**. 현재 dirty coach/store 브랜치에서 구현하지 않음
- 커밋은 사용자 요청 시에만 (이 플랜의 Commit 스텝은 사용자가 커밋을 요청한 경우에만 실행)
- Spatie Permission 등 패키지 도입 금지

### 동기화 대상 플래그 7개 (상수로 고정)

```php
public const FLAG_COLUMNS = [
    'setup_view',
    'setup_manage',
    'can_manage_store_inventory',
    'is_gs_brochure_admin',
    'is_coach_team_lead',
    'can_view_all_institutions',
    'is_deputy_admin',
];
```

---

## File Structure

| File | Role |
|------|------|
| `database/migrations/2026_07_30_000001_create_job_title_permissions_table.php` | `job_title_permissions` 테이블 |
| `app/Models/JobTitlePermission.php` | 매트릭스 행 모델 |
| `database/factories/JobTitlePermissionFactory.php` | 테스트용 팩토리 |
| `app/Support/JobTitlePermissionSynchronizer.php` | 단일 writer — users 플래그 동기화 |
| `app/Console/Commands/SyncUserPermissionsFromJobTitles.php` | `users:sync-permissions-from-job-titles` |
| `app/Console/Commands/SyncCoachTeamLeadFromJobs.php` | deprecate → 새 명령으로 위임 + 안내 |
| `app/Livewire/SetupJobTitlePermissionMatrix.php` | Setup 매트릭스 Livewire |
| `resources/views/livewire/setup-job-title-permission-matrix.blade.php` | 매트릭스 UI |
| `resources/views/pages/setup/job-title-permissions.blade.php` | 라우트 페이지 |
| `resources/views/pages/setup/index.blade.php` | 허브 카드 링크 |
| `routes/web.php` | `setup.job-title-permissions` 라우트만 추가 |
| `app/Livewire/PeopleEmployeesList.php` | 7플래그 수동 편집 제거, JOB 옵션=공통코드, 저장 후 sync |
| `resources/views/livewire/people-employees-list.blade.php` | 권한 UI 읽기전용/제거 + 안내 |
| `app/Support/EmployeeExcelImporter.php` | 신규 계정 생성 후 `syncUser` |
| `tests/Feature/JobTitlePermissionSynchronizerTest.php` | Synchronizer + admin skip |
| `tests/Feature/SetupJobTitlePermissionMatrixTest.php` | Setup 저장·권한·동기화 |
| `tests/Feature/PeopleEmployeePermissionsTest.php` | JOB 변경 sync·7플래그 persist 불가 |
| `tests/Feature/CoachTeamLeadRolePrecedenceTest.php` | deprecate 명령 → 매트릭스 기준 갱신 |
| `tests/Feature/SetupPagesTest.php` | 새 Setup 라우트 스모크 |
| `docs/platform-user-guide.md` | §5 권한 출처 갱신 |
| `docs/adr/0003-...` / `0007` / `README` | 이미 초안 있음 — 브랜치에 포함·정합만 확인 |

**건드리지 않을 것:** Coach KPI aggregator/list, Store return, institution-coverage, `config/coach_team_kpi.php`의 매트릭스 KPI 설정(메뉴 스코프용 `coach_work_depts`는 유지; `team_lead_jobs`는 sync 권한 on/off에 더 이상 쓰지 않음).

---

### Task 0: 깨끗한 브랜치 준비

**Files:**
- Create branch from `main` (or clean remote main)
- Bring only design docs: spec + ADR 0007 + ADR 0003/README 상태 갱신

**Interfaces:**
- Produces: working tree with design docs only (no Coach/Store WIP)

- [ ] **Step 1: 현재 dirty 작업과 분리**

현재 브랜치에 Coach/Store WIP가 있으면 **커밋·stash하지 말고**, 구현은 새 worktree 또는:

```bash
git fetch origin
git checkout main
git pull origin main
git checkout -b feature/job-title-permission-matrix
```

스펙/ADR이 다른 브랜치에만 있으면 해당 파일만 cherry-pick/checkout:

```bash
# 예시 — 파일이 다른 브랜치에 있을 때
git checkout feature/coach-team-kpi-matrix -- \
  docs/superpowers/specs/2026-07-30-job-title-permission-matrix-design.md \
  docs/adr/0007-job-title-permission-matrix.md \
  docs/adr/0003-gate-based-rbac-team-scope.md \
  docs/adr/README.md
```

- [ ] **Step 2: 브랜치 상태 확인**

```bash
git status -sb
git diff --stat
```

Expected: design/ADR 관련 파일만, Coach/Store Livewire·StoreReturn 등은 **없음**.

- [ ] **Step 3: Commit (사용자 요청 시에만)**

```bash
git add docs/superpowers/specs/2026-07-30-job-title-permission-matrix-design.md \
  docs/adr/0007-job-title-permission-matrix.md \
  docs/adr/0003-gate-based-rbac-team-scope.md \
  docs/adr/README.md
git commit -m "$(cat <<'EOF'
docs: 직책×권한 매트릭스 설계·ADR 0007

권한 출처를 직책 표→users 동기화로 옮기는 합의 문서를 고정한다.
EOF
)"
```

---

### Task 1: Migration + Model + Factory

**Files:**
- Create: `database/migrations/2026_07_30_000001_create_job_title_permissions_table.php`
- Create: `app/Models/JobTitlePermission.php`
- Create: `database/factories/JobTitlePermissionFactory.php`
- Test: `tests/Feature/JobTitlePermissionSynchronizerTest.php` (스모크용 모델 생성만 먼저 가능 — Task 2에서 본격)

**Interfaces:**
- Produces: `JobTitlePermission` with fillable/casts for 7 flags + `job_code`
- Produces: factory state helpers optional

- [ ] **Step 1: Write failing test that model persists**

Create `tests/Feature/JobTitlePermissionModelTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\JobTitlePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobTitlePermissionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_title_permission_persists_flags(): void
    {
        $row = JobTitlePermission::query()->create([
            'job_code' => 'Department Manager',
            'setup_view' => true,
            'setup_manage' => true,
            'can_manage_store_inventory' => false,
            'is_gs_brochure_admin' => false,
            'is_coach_team_lead' => true,
            'can_view_all_institutions' => false,
            'is_deputy_admin' => false,
        ]);

        $this->assertDatabaseHas('job_title_permissions', [
            'id' => $row->id,
            'job_code' => 'Department Manager',
            'setup_manage' => 1,
            'is_coach_team_lead' => 1,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test --compact tests/Feature/JobTitlePermissionModelTest.php
```

Expected: FAIL (table/model missing)

- [ ] **Step 3: Create migration**

```bash
php artisan make:migration create_job_title_permissions_table --no-interaction
```

Migration body:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_title_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('job_code')->unique();
            $table->boolean('setup_view')->default(false);
            $table->boolean('setup_manage')->default(false);
            $table->boolean('can_manage_store_inventory')->default(false);
            $table->boolean('is_gs_brochure_admin')->default(false);
            $table->boolean('is_coach_team_lead')->default(false);
            $table->boolean('can_view_all_institutions')->default(false);
            $table->boolean('is_deputy_admin')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_title_permissions');
    }
};
```

- [ ] **Step 4: Create model + factory**

`app/Models/JobTitlePermission.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobTitlePermission extends Model
{
    /** @use HasFactory<\Database\Factories\JobTitlePermissionFactory> */
    use HasFactory;

    protected $fillable = [
        'job_code',
        'setup_view',
        'setup_manage',
        'can_manage_store_inventory',
        'is_gs_brochure_admin',
        'is_coach_team_lead',
        'can_view_all_institutions',
        'is_deputy_admin',
    ];

    protected function casts(): array
    {
        return [
            'setup_view' => 'boolean',
            'setup_manage' => 'boolean',
            'can_manage_store_inventory' => 'boolean',
            'is_gs_brochure_admin' => 'boolean',
            'is_coach_team_lead' => 'boolean',
            'can_view_all_institutions' => 'boolean',
            'is_deputy_admin' => 'boolean',
        ];
    }
}
```

Factory:

```php
<?php

namespace Database\Factories;

use App\Models\JobTitlePermission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobTitlePermission>
 */
class JobTitlePermissionFactory extends Factory
{
    protected $model = JobTitlePermission::class;

    public function definition(): array
    {
        return [
            'job_code' => fake()->unique()->jobTitle(),
            'setup_view' => false,
            'setup_manage' => false,
            'can_manage_store_inventory' => false,
            'is_gs_brochure_admin' => false,
            'is_coach_team_lead' => false,
            'can_view_all_institutions' => false,
            'is_deputy_admin' => false,
        ];
    }
}
```

- [ ] **Step 5: Run migration + test**

```bash
php artisan migrate --no-interaction
php artisan test --compact tests/Feature/JobTitlePermissionModelTest.php
```

Expected: PASS

- [ ] **Step 6: Pint dirty PHP**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7: Commit (사용자 요청 시에만)**

```bash
git add database/migrations/*job_title_permissions* app/Models/JobTitlePermission.php \
  database/factories/JobTitlePermissionFactory.php tests/Feature/JobTitlePermissionModelTest.php
git commit -m "$(cat <<'EOF'
feat: job_title_permissions 테이블·모델 추가

직책별 표준 권한 매트릭스 저장소를 도입한다.
EOF
)"
```

---

### Task 2: JobTitlePermissionSynchronizer

**Files:**
- Create: `app/Support/JobTitlePermissionSynchronizer.php`
- Create: `tests/Feature/JobTitlePermissionSynchronizerTest.php`

**Interfaces:**
- Consumes: `JobTitlePermission`, `User`, `Employee`, `AccountAuditLog::record`
- Produces:
  - `syncUser(User $user, ?User $actor = null): bool` — true if saved
  - `syncUsersForJobCode(string $jobCode, ?User $actor = null): int` — synced count
  - `syncAll(?User $actor = null): array{synced: int, skipped_admin: int, skipped_no_employee: int}`

- [ ] **Step 1: Write failing tests**

`tests/Feature/JobTitlePermissionSynchronizerTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobTitlePermission;
use App\Models\User;
use App\Support\JobTitlePermissionSynchronizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class JobTitlePermissionSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('employee')) {
            Schema::create('employee', function (Blueprint $table): void {
                $table->string('EMPNO')->primary();
                $table->string('JOB')->nullable();
                $table->string('WORKDEPT')->nullable();
                $table->integer('STATUS')->nullable();
            });
        }
    }

    public function test_sync_user_copies_matrix_flags_and_forces_setup_view(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E100',
            'JOB' => 'Team Lead',
            'WORKDEPT' => 'A01',
            'STATUS' => 1,
        ]);

        JobTitlePermission::query()->create([
            'job_code' => 'Team Lead',
            'setup_view' => false,
            'setup_manage' => true,
            'can_manage_store_inventory' => true,
            'is_gs_brochure_admin' => false,
            'is_coach_team_lead' => true,
            'can_view_all_institutions' => false,
            'is_deputy_admin' => false,
        ]);

        $user = User::factory()->create([
            'employee_empno' => 'E100',
            'is_admin' => false,
            'setup_view' => false,
            'setup_manage' => false,
            'can_manage_store_inventory' => false,
            'is_coach_team_lead' => false,
        ]);

        $synced = app(JobTitlePermissionSynchronizer::class)->syncUser($user);

        $this->assertTrue($synced);
        $user->refresh();
        $this->assertTrue((bool) $user->setup_manage);
        $this->assertTrue((bool) $user->setup_view); // forced by setup_manage
        $this->assertTrue((bool) $user->can_manage_store_inventory);
        $this->assertTrue((bool) $user->is_coach_team_lead);
        $this->assertFalse((bool) $user->is_admin);
    }

    public function test_sync_skips_admin_user(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E200',
            'JOB' => 'Team Lead',
            'STATUS' => 1,
        ]);

        JobTitlePermission::query()->create([
            'job_code' => 'Team Lead',
            'setup_manage' => true,
            'setup_view' => true,
            'is_coach_team_lead' => true,
        ]);

        $admin = User::factory()->admin()->create([
            'employee_empno' => 'E200',
            'setup_view' => true,
            'setup_manage' => true,
            'is_coach_team_lead' => false,
        ]);

        $before = $admin->only(JobTitlePermissionSynchronizer::FLAG_COLUMNS);

        $synced = app(JobTitlePermissionSynchronizer::class)->syncUser($admin);

        $this->assertFalse($synced);
        $admin->refresh();
        $this->assertSame($before, $admin->only(JobTitlePermissionSynchronizer::FLAG_COLUMNS));
    }

    public function test_missing_matrix_row_clears_all_seven_flags(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E300',
            'JOB' => 'Legacy Title',
            'STATUS' => 1,
        ]);

        $user = User::factory()->create([
            'employee_empno' => 'E300',
            'is_admin' => false,
            'setup_view' => true,
            'setup_manage' => true,
            'can_manage_store_inventory' => true,
            'is_gs_brochure_admin' => true,
            'is_coach_team_lead' => true,
            'can_view_all_institutions' => true,
            'is_deputy_admin' => true,
        ]);

        app(JobTitlePermissionSynchronizer::class)->syncUser($user);

        $user->refresh();
        foreach (JobTitlePermissionSynchronizer::FLAG_COLUMNS as $column) {
            $this->assertFalse((bool) $user->{$column}, $column);
        }
    }

    public function test_sync_users_for_job_code_updates_matching_non_admins_only(): void
    {
        Employee::query()->create(['EMPNO' => 'E401', 'JOB' => 'Coach', 'STATUS' => 1]);
        Employee::query()->create(['EMPNO' => 'E402', 'JOB' => 'Coach', 'STATUS' => 1]);
        Employee::query()->create(['EMPNO' => 'E403', 'JOB' => 'Staff', 'STATUS' => 1]);

        JobTitlePermission::query()->create([
            'job_code' => 'Coach',
            'is_coach_team_lead' => true,
            'setup_view' => true,
        ]);

        $u1 = User::factory()->create(['employee_empno' => 'E401', 'is_admin' => false, 'is_coach_team_lead' => false]);
        $u2 = User::factory()->admin()->create(['employee_empno' => 'E402', 'is_coach_team_lead' => false]);
        $u3 = User::factory()->create(['employee_empno' => 'E403', 'is_admin' => false, 'is_coach_team_lead' => false]);

        $count = app(JobTitlePermissionSynchronizer::class)->syncUsersForJobCode('Coach');

        $this->assertSame(1, $count);
        $this->assertTrue((bool) $u1->fresh()->is_coach_team_lead);
        $this->assertFalse((bool) $u2->fresh()->is_coach_team_lead);
        $this->assertFalse((bool) $u3->fresh()->is_coach_team_lead);
    }
}
```

- [ ] **Step 2: Run tests — expect FAIL**

```bash
php artisan test --compact tests/Feature/JobTitlePermissionSynchronizerTest.php
```

- [ ] **Step 3: Implement Synchronizer**

`app/Support/JobTitlePermissionSynchronizer.php`:

```php
<?php

namespace App\Support;

use App\Models\AccountAuditLog;
use App\Models\Employee;
use App\Models\JobTitlePermission;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class JobTitlePermissionSynchronizer
{
    public const FLAG_COLUMNS = [
        'setup_view',
        'setup_manage',
        'can_manage_store_inventory',
        'is_gs_brochure_admin',
        'is_coach_team_lead',
        'can_view_all_institutions',
        'is_deputy_admin',
    ];

    public function syncUser(User $user, ?User $actor = null): bool
    {
        if ((bool) $user->is_admin) {
            return false;
        }

        $empNo = trim((string) $user->employee_empno);
        if ($empNo === '') {
            return false;
        }

        $jobCode = $this->resolveJobCode($empNo);
        $flags = $this->flagsForJobCode($jobCode);

        $before = $user->only(self::FLAG_COLUMNS);
        if ($this->flagsEqual($before, $flags)) {
            return false;
        }

        $user->forceFill($flags)->save();

        if (Schema::hasTable('account_audit_logs')) {
            AccountAuditLog::record($user, $actor, 'job_title_permission_synced', [
                'job_code' => $jobCode,
                'before' => $before,
                'after' => $flags,
            ]);
        }

        return true;
    }

    public function syncUsersForJobCode(string $jobCode, ?User $actor = null): int
    {
        $normalized = trim($jobCode);
        $empNos = Employee::query()
            ->whereRaw('TRIM(JOB) = ?', [$normalized])
            ->pluck('EMPNO')
            ->map(fn ($v): string => trim((string) $v))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($empNos === []) {
            return 0;
        }

        $users = User::query()
            ->whereIn('employee_empno', $empNos)
            ->where('is_admin', false)
            ->get();

        $count = 0;
        foreach ($users as $user) {
            if ($this->syncUser($user, $actor)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{synced: int, skipped_admin: int, skipped_no_employee: int}
     */
    public function syncAll(?User $actor = null): array
    {
        $stats = ['synced' => 0, 'skipped_admin' => 0, 'skipped_no_employee' => 0];

        $users = User::query()
            ->whereNotNull('employee_empno')
            ->where('employee_empno', '!=', '')
            ->get();

        foreach ($users as $user) {
            if ((bool) $user->is_admin) {
                $stats['skipped_admin']++;

                continue;
            }

            $empNo = trim((string) $user->employee_empno);
            if ($empNo === '' || ! Employee::query()->where('EMPNO', $empNo)->exists()) {
                $stats['skipped_no_employee']++;

                continue;
            }

            if ($this->syncUser($user, $actor)) {
                $stats['synced']++;
            }
        }

        return $stats;
    }

    /**
     * @return array<string, bool>
     */
    public function flagsForJobCode(?string $jobCode): array
    {
        $normalized = trim((string) $jobCode);
        $row = $normalized === ''
            ? null
            : JobTitlePermission::query()->where('job_code', $normalized)->first();

        $flags = [];
        foreach (self::FLAG_COLUMNS as $column) {
            $flags[$column] = (bool) ($row?->{$column} ?? false);
        }

        if ($flags['setup_manage']) {
            $flags['setup_view'] = true;
        }

        return $flags;
    }

    private function resolveJobCode(string $empNo): ?string
    {
        $job = Employee::query()->where('EMPNO', $empNo)->value('JOB');

        if (! is_string($job)) {
            return null;
        }

        $trimmed = trim($job);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, bool>  $b
     */
    private function flagsEqual(array $a, array $b): bool
    {
        foreach (self::FLAG_COLUMNS as $column) {
            if ((bool) ($a[$column] ?? false) !== (bool) ($b[$column] ?? false)) {
                return false;
            }
        }

        return true;
    }
}
```

주의:
- `syncAll`은 Artisan **수동** 실행 전용. migrate/boot에서 호출하지 않음.
- 표가 비어 있으면 `flagsForJobCode`가 전부 false → 비관리자 권한이 꺼짐. 이게 의도된 동작이므로 Artisan 설명에 경고를 넣음 (Task 3).

- [ ] **Step 4: Run tests — expect PASS**

```bash
php artisan test --compact tests/Feature/JobTitlePermissionSynchronizerTest.php
```

- [ ] **Step 5: Pint + Commit (요청 시)**

```bash
vendor/bin/pint --dirty --format agent
```

---

### Task 3: Artisan 명령 + 기존 Coach sync deprecate

**Files:**
- Create: `app/Console/Commands/SyncUserPermissionsFromJobTitles.php`
- Modify: `app/Console/Commands/SyncCoachTeamLeadFromJobs.php`
- Modify: `tests/Feature/CoachTeamLeadRolePrecedenceTest.php`

**Interfaces:**
- Consumes: `JobTitlePermissionSynchronizer::syncAll`
- Produces: `users:sync-permissions-from-job-titles`
- Produces: old command prints deprecation and delegates to `syncAll` (또는 새 명령만 안내 후 SUCCESS)

- [ ] **Step 1: Write / update failing test**

`CoachTeamLeadRolePrecedenceTest`를 매트릭스 기준으로 교체:

```php
public function test_sync_permissions_command_sets_coach_team_lead_from_matrix(): void
{
    Employee::query()->create([
        'EMPNO' => 'E001',
        'JOB' => 'Department Manager',
        'WORKDEPT' => 'A05',
        'STATUS' => 1,
    ]);

    \App\Models\JobTitlePermission::query()->create([
        'job_code' => 'Department Manager',
        'is_coach_team_lead' => true,
        'setup_view' => true,
    ]);

    $user = User::factory()->create([
        'employee_empno' => 'E001',
        'is_admin' => false,
        'is_coach_team_lead' => false,
    ]);

    $this->artisan('users:sync-permissions-from-job-titles')
        ->expectsOutputToContain('주의')
        ->assertSuccessful();

    $this->assertTrue((bool) $user->fresh()->is_coach_team_lead);
}

public function test_deprecated_coach_sync_command_delegates(): void
{
    // 동일 픽스처 후
    $this->artisan('users:sync-coach-team-lead-from-jobs')
        ->expectsOutputToContain('deprecated')
        ->assertSuccessful();
}
```

(출력 문구는 구현 문자열과 맞출 것)

- [ ] **Step 2: Implement new command**

```php
<?php

namespace App\Console\Commands;

use App\Support\JobTitlePermissionSynchronizer;
use Illuminate\Console\Command;

class SyncUserPermissionsFromJobTitles extends Command
{
    protected $signature = 'users:sync-permissions-from-job-titles';

    protected $description = '직책 권한 매트릭스(job_title_permissions)를 users 기능 플래그에 동기화합니다. is_admin은 변경하지 않습니다.';

    public function handle(JobTitlePermissionSynchronizer $synchronizer): int
    {
        $this->warn('주의: 표가 비어 있거나 직책이 없으면 비관리자 7개 권한이 모두 꺼질 수 있습니다.');
        $this->warn('운영 절차: (1) migrate (2) Setup에서 표 설정 (3) 이 명령 수동 실행');

        $stats = $synchronizer->syncAll();

        $this->table(
            ['항목', '건수'],
            [
                ['동기화(변경됨)', $stats['synced']],
                ['관리자 건너뜀', $stats['skipped_admin']],
                ['직원 없음 건너뜀', $stats['skipped_no_employee']],
            ],
        );

        return self::SUCCESS;
    }
}
```

- [ ] **Step 3: Deprecate old command**

`SyncCoachTeamLeadFromJobs::handle`를 새 synchronizer 호출로 교체하고:

```php
$this->warn('users:sync-coach-team-lead-from-jobs is deprecated. Use users:sync-permissions-from-job-titles instead.');
return $this->call('users:sync-permissions-from-job-titles');
```

`--revoke-ineligible` 옵션은 무시해도 됨(매트릭스가 이미 전체 7플래그를 덮어씀). 설명에 deprecated 명시.

- [ ] **Step 4: Run tests**

```bash
php artisan test --compact tests/Feature/CoachTeamLeadRolePrecedenceTest.php tests/Feature/JobTitlePermissionSynchronizerTest.php
```

- [ ] **Step 5: Commit (요청 시)**

---

### Task 4: Setup 매트릭스 UI + 라우트

**Files:**
- Create: `app/Livewire/SetupJobTitlePermissionMatrix.php`
- Create: `resources/views/livewire/setup-job-title-permission-matrix.blade.php`
- Create: `resources/views/pages/setup/job-title-permissions.blade.php`
- Modify: `resources/views/pages/setup/index.blade.php` — 「직책 권한」카드 (실제 링크)
- Modify: `routes/web.php` — `setup.job-title-permissions`만 추가 (`can:accessSetup` 그룹 안)
- Create: `tests/Feature/SetupJobTitlePermissionMatrixTest.php`
- Modify: `tests/Feature/SetupPagesTest.php` — 라우트 스모크

**Interfaces:**
- Consumes: `SetupCommonCode` (`category=job_title`, `is_active`), `JobTitlePermission`, `JobTitlePermissionSynchronizer::syncUsersForJobCode`, Gates `accessSetup` / `manageTeamStructure`
- Produces: 저장 시 upsert + 저장된 job_code별 sync + flash

- [ ] **Step 1: Write failing feature tests**

```php
<?php

namespace Tests\Feature;

use App\Livewire\SetupJobTitlePermissionMatrix;
use App\Models\Employee;
use App\Models\JobTitlePermission;
use App\Models\SetupCommonCode;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SetupJobTitlePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('employee')) {
            Schema::create('employee', function (Blueprint $table): void {
                $table->string('EMPNO')->primary();
                $table->string('JOB')->nullable();
                $table->string('WORKDEPT')->nullable();
                $table->integer('STATUS')->nullable();
            });
        }
    }

    public function test_setup_manage_user_can_save_matrix_and_sync_users(): void
    {
        SetupCommonCode::query()->create([
            'category' => 'job_title',
            'code' => 'Coach',
            'label' => '코치',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Employee::query()->create(['EMPNO' => 'E1', 'JOB' => 'Coach', 'STATUS' => 1]);
        $target = User::factory()->create([
            'employee_empno' => 'E1',
            'is_admin' => false,
            'is_coach_team_lead' => false,
        ]);

        $actor = User::factory()->create([
            'setup_view' => true,
            'setup_manage' => true,
        ]);

        Livewire::actingAs($actor)
            ->test(SetupJobTitlePermissionMatrix::class)
            ->set('rows.Coach.is_coach_team_lead', true)
            ->set('rows.Coach.setup_view', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('job_title_permissions', [
            'job_code' => 'Coach',
            'is_coach_team_lead' => 1,
        ]);
        $this->assertTrue((bool) $target->fresh()->is_coach_team_lead);
    }

    public function test_setup_view_only_cannot_save(): void
    {
        SetupCommonCode::query()->create([
            'category' => 'job_title',
            'code' => 'Staff',
            'label' => 'Staff',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $viewer = User::factory()->create([
            'setup_view' => true,
            'setup_manage' => false,
        ]);

        Livewire::actingAs($viewer)
            ->test(SetupJobTitlePermissionMatrix::class)
            ->set('rows.Staff.setup_view', true)
            ->call('save')
            ->assertForbidden();
    }

    public function test_route_ok_for_setup_viewer(): void
    {
        $viewer = User::factory()->create(['setup_view' => true]);

        $this->actingAs($viewer)
            ->get(route('setup.job-title-permissions'))
            ->assertOk();
    }
}
```

- [ ] **Step 2: Run — expect FAIL**

```bash
php artisan test --compact tests/Feature/SetupJobTitlePermissionMatrixTest.php
```

- [ ] **Step 3: Livewire component**

패턴: `SetupCommonCodeManagement`처럼 `Gate::authorize('manageTeamStructure')` on save; mount/render는 `accessSetup` 라우트 미들웨어에 의존.

핵심 골격:

```php
public array $rows = []; // keyed by job_code => flag booleans

public function mount(): void
{
    $this->loadRows();
}

public function save(): void
{
    Gate::authorize('manageTeamStructure');

    // validate each known active job_code flags are boolean
    // for each row: if setup_manage then setup_view = true
    // JobTitlePermission::updateOrCreate(['job_code' => $code], $flags)
    // app(JobTitlePermissionSynchronizer::class)->syncUsersForJobCode($code, auth()->user())
    // session flash success with synced counts
}

private function loadRows(): void
{
    $codes = SetupCommonCode::query()
        ->where('category', 'job_title')
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('code')
        ->get(['code', 'label']);

    $existing = JobTitlePermission::query()
        ->whereIn('job_code', $codes->pluck('code'))
        ->get()
        ->keyBy('job_code');

    $this->rows = [];
    foreach ($codes as $code) {
        $row = $existing->get($code->code);
        $this->rows[$code->code] = [
            'label' => $code->label !== '' ? $code->label : $code->code,
            // 7 flags from $row or false
        ];
    }
}
```

Blade: 표 (행=직책 label+code, 열=7 체크박스), 저장 버튼은 `canManageSetup`/`@can('manageTeamStructure')`일 때만. view-only는 disabled checkbox.

Page:

```blade
<x-layouts.app title="SetUp — 직책 권한">
    ...
    <livewire:setup-job-title-permission-matrix />
</x-layouts.app>
```

Route (`routes/web.php` setup 그룹 안, **다른 Coach 라우트 변경 금지**):

```php
Route::get('/setup/job-title-permissions', function () {
    return view('pages.setup.job-title-permissions');
})->name('setup.job-title-permissions');
```

Hub card in `pages/setup/index.blade.php`: 팀 관리 옆(또는 같은 grid)에 실제 `<a href="{{ route('setup.job-title-permissions') }}">` 카드 추가. 「준비중」 더미에 넣지 말 것.

- [ ] **Step 4: Run tests + SetupPagesTest 추가**

```bash
php artisan test --compact tests/Feature/SetupJobTitlePermissionMatrixTest.php tests/Feature/SetupPagesTest.php
```

- [ ] **Step 5: Commit (요청 시)**

---

### Task 5: People — 7플래그 수동 편집 제거 + JOB 옵션·동기화

**Files:**
- Modify: `app/Livewire/PeopleEmployeesList.php`
- Modify: `resources/views/livewire/people-employees-list.blade.php`
- Modify: `tests/Feature/PeopleEmployeePermissionsTest.php` (관련 케이스 갱신)

**Interfaces:**
- Consumes: `SetupCommonCode` job_title active codes; `JobTitlePermissionSynchronizer::syncUser`
- Produces: People에서 `is_admin` / `is_active`만 편집 가능; 7플래그는 표시만 또는 숨김; JOB 저장 후 sync

- [ ] **Step 1: Identify failing expectations**

기존 `PeopleEmployeePermissionsTest`에서 setup_view/manage, store inventory, gs brochure, deputy, can_view_all_institutions를 **모달 체크로 바꾸는** 테스트를 찾아:

1. 저장 payload에서 7플래그 제거 → 매트릭스 sync로 대체
2. 요청으로 7플래그를 바꿔도 persist되지 않음(또는 Livewire 프로퍼티 없음)

새 테스트 추가:

```php
public function test_job_change_syncs_linked_user_flags_from_matrix(): void
{
    SetupCommonCode::query()->create([
        'category' => 'job_title',
        'code' => 'Coach',
        'label' => '코치',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    SetupCommonCode::query()->create([
        'category' => 'job_title',
        'code' => 'Staff',
        'label' => 'Staff',
        'is_active' => true,
        'sort_order' => 2,
    ]);

    JobTitlePermission::query()->create([
        'job_code' => 'Coach',
        'is_coach_team_lead' => true,
        'setup_view' => true,
    ]);

    // employee Staff + linked user, admin actor with manageUserAccounts
    // open edit, set editJob=Coach, save
    // assert user is_coach_team_lead true, setup_view true
}

public function test_seven_matrix_flags_are_not_persisted_from_people_request(): void
{
    // linked user starts all false, matrix empty/all false
    // if old properties still exist, set them true and save — must remain false
    // preferred: properties removed → assertNotSet / UI text about Setup matrix
}
```

`getJobOptions()` 변경:

```php
private function getJobOptions(): \Illuminate\Support\Collection
{
    return SetupCommonCode::query()
        ->where('category', 'job_title')
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->orderBy('code')
        ->get(['code', 'label']);
}
```

Validation: `Rule::in($codes->pluck('code'))`.  
레거시 JOB이 옵션에 없으면: 현재 `editJob` 값을 옵션에 **임시 포함**(label에 `(레거시)` 표시)해 다른 필드 저장은 가능하되, sync는 “표에 없음” → 7개 false. 저장 시 활성 code로 바꾸도록 유도하는 안내 문구.

계정 저장 로직 (`saveEmployee` 등):

```php
// REMOVE from linkedUserPayload:
// setup_view, setup_manage, can_manage_store_inventory, is_gs_brochure_admin,
// can_view_all_institutions, is_deputy_admin
// KEEP: is_admin, is_active, name, email, employee_empno, team

$linkedUser->forceFill($linkedUserPayload)->save();

if (! $linkedUser->is_admin) {
    app(JobTitlePermissionSynchronizer::class)->syncUser($linkedUser, auth()->user());
}
```

신규 계정 생성 경로도 동일: 기본 false로 만든 뒤 `syncUser` 호출.

Blade: 7개 체크박스 섹션을 제거하고 안내:

```blade
<p class="text-xs text-gray-500">
  Setup 조회·관리, Store 재고, GS Brochure, Coach 팀 KPI, 기관 전체 조회, 부관리자 권한은
  <a href="{{ route('setup.job-title-permissions') }}" class="text-[#2b78c5] underline">Setup → 직책 권한</a>
  표에서 직책 기준으로 관리됩니다. Full Access(관리자)만 이 화면에서 지정합니다.
</p>
```

읽기 전용으로 현재 플래그 표시는 선택(있으면 `linkedUser` 값 표시만, wire:model 없음).

- [ ] **Step 2: Run People permission tests, fix until green**

```bash
php artisan test --compact tests/Feature/PeopleEmployeePermissionsTest.php
```

- [ ] **Step 3: Commit (요청 시)**

---

### Task 6: Excel import — 신규 계정 후 syncUser

**Files:**
- Modify: `app/Support/EmployeeExcelImporter.php` (신규 User 생성 직후)
- Modify: `tests/Feature/PeopleEmployeeExcelImportTest.php` (또는 소형 Feature 테스트 추가)

**Interfaces:**
- Consumes: `JobTitlePermissionSynchronizer::syncUser`
- Note: 현재 임포트는 JOB을 엑셀에서 갱신하지 않고 신규만 `config('employee_import.default_job')`를 넣음. JOB 변경 훅은 “신규 생성 후 sync”로 충족. 나중에 JOB 컬럼이 임포트되면 같은 `syncUser` 호출을 업데이트 경로에도 추가.

- [ ] **Step 1: Test**

신규 직원+유저 생성 임포트 후, 매트릭스에 `default_job` 행이 있으면 해당 플래그가 반영되는지 검증. 매트릭스 없으면 7개 false 유지.

- [ ] **Step 2: After `User::query()->create(...)` (or forceFill save) for new accounts**

```php
app(JobTitlePermissionSynchronizer::class)->syncUser($linkedUser, User::query()->find($actorUserId));
```

admin 계정은 synchronizer가 no-op.

- [ ] **Step 3: Run excel import tests**

```bash
php artisan test --compact tests/Feature/PeopleEmployeeExcelImportTest.php
```

- [ ] **Step 4: Commit (요청 시)**

---

### Task 7: Docs — 사용자 가이드

**Files:**
- Modify: `docs/platform-user-guide.md` §5
- Confirm: `docs/adr/0003`, `0007`, `README` already correct on this branch

- [ ] **Step 1: Update §5**

유지: “직원 마스터의 직책(JOB)만으로는 관리자가 되지 **않습니다.**”

추가/수정:

- Setup **직책 권한** 표에서 직책별 기능 권한(Setup 조회/관리, Store 재고, GS Brochure, Coach 팀 KPI, 기관 전체 조회, 부관리자)을 설정한다.
- People에서는 Full Access(`is_admin`)와 계정 활성만 개인 지정.
- 운영: migrate → Setup에서 표 저장 → 필요 시 `php artisan users:sync-permissions-from-job-titles` 수동 실행. 표가 빈 채로 전체 sync 하지 말 것.

- [ ] **Step 2: Commit (요청 시)**

---

### Task 8: 검증 + 자체 코드 리뷰

- [ ] **Step 1: 관련 테스트 묶음**

```bash
php artisan test --compact \
  tests/Feature/JobTitlePermissionModelTest.php \
  tests/Feature/JobTitlePermissionSynchronizerTest.php \
  tests/Feature/SetupJobTitlePermissionMatrixTest.php \
  tests/Feature/SetupPagesTest.php \
  tests/Feature/PeopleEmployeePermissionsTest.php \
  tests/Feature/PeopleEmployeeExcelImportTest.php \
  tests/Feature/CoachTeamLeadRolePrecedenceTest.php \
  tests/Feature/SetupRolePermissionsTest.php
```

- [ ] **Step 2: Pint**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 3: (가능하면) composer run verify**

```bash
composer run verify
```

- [ ] **Step 4: Self-review checklist**

- [ ] `is_admin`이 매트릭스/Synchronizer/Artisan 어디에도 쓰이지 않는가?
- [ ] migrate/boot/ServiceProvider에서 `syncAll` 자동 호출이 없는가?
- [ ] Coach/Store WIP 파일이 diff에 없는가?
- [ ] People에서 7플래그 wire:model이 제거되었는가?
- [ ] Setup hub에 실제 링크가 있는가?
- [ ] 사용자 가이드에 “표 설정 후 수동 sync”가 있는가?

---

## Spec coverage (self-review)

| Spec 요구 | Task |
|-----------|------|
| `job_title_permissions` 테이블·모델 | Task 1 |
| Synchronizer + admin skip + missing→all false + setup_manage→setup_view | Task 2 |
| Artisan syncAll + Coach 명령 흡수/deprecate | Task 3 |
| Setup UI·라우트·권한·저장 시 job sync | Task 4 |
| People 7플래그 수동 불가 + JOB 공통코드 + JOB 저장 sync | Task 5 |
| Excel import sync | Task 6 |
| platform-user-guide + ADR | Task 0/7 |
| 배포 자동 sync 금지 | Global + Task 3 경고문 |
| is_admin 사람 단위만 | Global + Task 2/5 |
| 방안 A | Global |

## Placeholder scan

없음 — 구현 코드·명령·파일 경로를 태스크에 명시함.

## Type consistency

- `JobTitlePermissionSynchronizer::FLAG_COLUMNS` / `syncUser` / `syncUsersForJobCode` / `syncAll` 시그니처를 Task 2→3→4→5→6이 공유
- Livewire `rows` keyed by `job_code` string
- Artisan name: `users:sync-permissions-from-job-titles`

---

## Deployment note (PR 본문에 넣을 문구)

1. `php artisan migrate`
2. Setup → 직책 권한 표 설정·저장 (저장 시 해당 직책 사용자만 sync)
3. 필요 시 **수동**: `php artisan users:sync-permissions-from-job-titles`
4. 표가 비어 있는 상태로 3을 실행하지 말 것
