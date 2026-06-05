<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Support\EmployeeExcelImporter;
use App\Support\EmployeeHireDate;
use App\Support\EmployeeSex;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmployeeExcelImporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('department')) {
            Schema::create('department', function (Blueprint $table): void {
                $table->string('DEPTNO')->primary();
                $table->string('DEPTNAME')->nullable();
                $table->string('MGRNO')->nullable();
                $table->string('ADMRDEPT')->nullable();
                $table->string('LOCATION')->nullable();
            });
        }

        if (! Schema::hasTable('employee')) {
            Schema::create('employee', function (Blueprint $table): void {
                $table->string('EMPNO')->primary();
                $table->string('WORKDEPT')->nullable();
                $table->string('KOREANAME')->nullable();
                $table->string('ENGLISHNAME')->nullable();
                $table->string('JOB')->nullable();
                $table->string('EMAIL')->nullable();
                $table->string('PHONENO')->nullable();
                $table->integer('STATUS')->nullable();
                $table->date('HIREDATE')->nullable();
                $table->string('SEX')->default('');
            });
        }

        Department::query()->insert([
            ['DEPTNO' => 'A01', 'DEPTNAME' => '팀 A', 'MGRNO' => '', 'ADMRDEPT' => '', 'LOCATION' => ''],
            ['DEPTNO' => 'A05', 'DEPTNAME' => 'Coach', 'MGRNO' => '', 'ADMRDEPT' => '', 'LOCATION' => ''],
        ]);

        Employee::query()->create([
            'EMPNO' => 'E001',
            'KOREANAME' => '홍길동',
            'ENGLISHNAME' => 'Hong',
            'JOB' => '매니저',
            'EMAIL' => 'hong@example.com',
            'PHONENO' => '010-1111-1111',
            'WORKDEPT' => 'A01',
            'STATUS' => 1,
        ]);
    }

    /**
     * @param  array<int, array<int, string>>  $dataRows
     * @return array<int, array<int, mixed>>
     */
    private function rows(array $dataRows): array
    {
        return array_merge([
            ['성명', '부서', '전화', '모바일', 'Email', '사내전화(내선)'],
        ], $dataRows);
    }

    public function test_import_updates_existing_employee_by_email(): void
    {
        $actor = User::factory()->admin()->create();

        $result = app(EmployeeExcelImporter::class)->importRows(
            $this->rows([
                ['홍길동 수정', '팀 A', '', '010-9999-9999', 'hong@example.com', ''],
            ]),
            $actor->id,
            dryRun: false,
        );

        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['updated']);

        $employee = Employee::query()->where('EMPNO', 'E001')->first();
        $this->assertSame('홍길동 수정', $employee?->KOREANAME);
        $this->assertSame('010-9999-9999', $employee?->PHONENO);
        $this->assertSame('Hong', $employee?->ENGLISHNAME);
        $this->assertSame('매니저', $employee?->JOB);
    }

    public function test_import_inserts_new_employee_and_user_without_reset_mail(): void
    {
        $actor = User::factory()->admin()->create();

        $result = app(EmployeeExcelImporter::class)->importRows(
            $this->rows([
                ['김신규', '팀 A', '', '010-2222-2222', 'new@example.com', ''],
            ]),
            $actor->id,
            dryRun: false,
        );

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(0, $result['reset_emails_sent']);
        $this->assertSame(0, $result['reset_emails_failed']);

        $employee = Employee::query()->where('EMAIL', 'new@example.com')->first();
        $this->assertNotNull($employee);
        $this->assertSame('Staff', $employee->JOB);
        $this->assertSame('김신규', $employee->ENGLISHNAME);
        $this->assertDatabaseHas('employee', [
            'EMAIL' => 'new@example.com',
            'HIREDATE' => EmployeeHireDate::defaultForStorage(),
            'SEX' => EmployeeSex::UNSPECIFIED,
        ]);

        $user = User::query()->where('email', 'new@example.com')->first();
        $this->assertNotNull($user);
    }

    public function test_import_requires_mobile_for_new_employee(): void
    {
        $actor = User::factory()->admin()->create();

        $result = app(EmployeeExcelImporter::class)->importRows(
            $this->rows([
                ['김신규', '팀 A', '', '', 'new@example.com', ''],
            ]),
            $actor->id,
            dryRun: false,
        );

        $this->assertSame(0, $result['inserted']);
        $this->assertSame(1, $result['skipped']);
        $this->assertStringContainsString('모바일', $result['errors'][0]);
        $this->assertDatabaseMissing('employee', ['EMAIL' => 'new@example.com']);
    }

    public function test_import_auto_creates_department(): void
    {
        $actor = User::factory()->admin()->create();

        $result = app(EmployeeExcelImporter::class)->importRows(
            $this->rows([
                ['신규팀원', 'New Team', '', '010-3333-3333', 'team@example.com', ''],
            ]),
            $actor->id,
            dryRun: false,
        );

        $this->assertSame(1, $result['departments_created']);
        $this->assertDatabaseHas('department', ['DEPTNAME' => 'New Team']);

        $employee = Employee::query()->where('EMAIL', 'team@example.com')->first();
        $this->assertNotNull($employee);
        $this->assertSame(
            Department::query()->where('DEPTNAME', 'New Team')->value('DEPTNO'),
            $employee->WORKDEPT,
        );
    }

    public function test_import_maps_training_department_to_coach(): void
    {
        $actor = User::factory()->admin()->create();

        app(EmployeeExcelImporter::class)->importRows(
            $this->rows([
                ['코치', 'Training Team', '', '010-4444-4444', 'coach@example.com', ''],
            ]),
            $actor->id,
            dryRun: false,
        );

        $this->assertDatabaseHas('employee', [
            'EMAIL' => 'coach@example.com',
            'WORKDEPT' => 'A05',
        ]);
    }

    public function test_import_hides_active_employees_not_in_excel(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E999',
            'KOREANAME' => '퇴사예정',
            'ENGLISHNAME' => 'Leave',
            'JOB' => 'Staff',
            'EMAIL' => 'leave@example.com',
            'PHONENO' => '010-5555-5555',
            'WORKDEPT' => 'A01',
            'STATUS' => 1,
        ]);

        $actor = User::factory()->admin()->create();

        $result = app(EmployeeExcelImporter::class)->importRows(
            $this->rows([
                ['홍길동', '팀 A', '', '010-1111-1111', 'hong@example.com', ''],
            ]),
            $actor->id,
            dryRun: false,
        );

        $this->assertSame(1, $result['hidden']);
        $this->assertSame(0, Employee::query()->where('EMPNO', 'E999')->value('STATUS'));
    }

    public function test_import_skips_hiding_last_active_admin(): void
    {
        $actor = User::factory()->admin()->create([
            'email' => 'actor@example.com',
            'employee_empno' => 'E777',
            'is_active' => true,
        ]);

        Employee::query()->create([
            'EMPNO' => 'E777',
            'KOREANAME' => '로그인관리자',
            'ENGLISHNAME' => 'Actor Admin',
            'JOB' => 'Staff',
            'EMAIL' => 'actor@example.com',
            'PHONENO' => '010-6666-6666',
            'WORKDEPT' => 'A01',
            'STATUS' => 1,
        ]);

        $result = app(EmployeeExcelImporter::class)->importRows(
            $this->rows([
                ['홍길동', '팀 A', '', '010-1111-1111', 'hong@example.com', ''],
            ]),
            $actor->id,
            dryRun: false,
        );

        $this->assertSame(1, Employee::query()->where('EMPNO', 'E777')->value('STATUS'));
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
    }

    public function test_import_rejects_duplicate_email_in_file(): void
    {
        $actor = User::factory()->admin()->create();

        $result = app(EmployeeExcelImporter::class)->importRows(
            $this->rows([
                ['A', '팀 A', '', '010-7777-7777', 'dup@example.com', ''],
                ['B', '팀 A', '', '010-8888-8888', 'dup@example.com', ''],
            ]),
            $actor->id,
            dryRun: false,
        );

        $this->assertSame(1, $result['inserted']);
        $this->assertSame(1, $result['skipped']);
        $this->assertStringContainsString('중복', $result['errors'][0]);
    }

    public function test_dry_run_does_not_persist_changes(): void
    {
        $actor = User::factory()->admin()->create();

        $result = app(EmployeeExcelImporter::class)->importRows(
            $this->rows([
                ['김신규', 'New Team', '', '010-3333-3333', 'dry@example.com', ''],
            ]),
            $actor->id,
            dryRun: true,
        );

        $this->assertTrue($result['dry_run']);
        $this->assertSame(1, $result['inserted']);
        $this->assertDatabaseMissing('employee', ['EMAIL' => 'dry@example.com']);
        $this->assertDatabaseMissing('department', ['DEPTNAME' => 'New Team']);
        $this->assertArrayNotHasKey('rollback', $result);
    }

    public function test_rollback_reverses_insert_update_hide_and_department_changes(): void
    {
        Employee::query()->create([
            'EMPNO' => 'E999',
            'KOREANAME' => '퇴사예정',
            'ENGLISHNAME' => 'Leave',
            'JOB' => 'Staff',
            'EMAIL' => 'leave@example.com',
            'PHONENO' => '010-5555-5555',
            'WORKDEPT' => 'A01',
            'STATUS' => 1,
        ]);

        $actor = User::factory()->admin()->create();
        $importer = app(EmployeeExcelImporter::class);

        $result = $importer->importRows(
            $this->rows([
                ['홍길동', '팀 A', '', '010-8888-8888', 'hong@example.com', ''],
                ['김신규', 'Rollback Team', '', '010-2222-2222', 'new@example.com', ''],
            ]),
            $actor->id,
            dryRun: false,
        );

        $this->assertArrayHasKey('rollback', $result);
        $this->assertDatabaseHas('employee', ['EMAIL' => 'new@example.com']);
        $this->assertSame('010-8888-8888', Employee::query()->where('EMPNO', 'E001')->value('PHONENO'));
        $this->assertSame(0, (int) Employee::query()->where('EMPNO', 'E999')->value('STATUS'));
        $this->assertDatabaseHas('department', ['DEPTNAME' => 'Rollback Team']);

        $rollbackResult = $importer->rollback($result['rollback']);

        $this->assertSame(1, $rollbackResult['deleted_employees']);
        $this->assertSame(1, $rollbackResult['restored_updates']);
        $this->assertSame(1, $rollbackResult['restored_hidden']);
        $this->assertSame(1, $rollbackResult['deleted_departments']);
        $this->assertDatabaseMissing('employee', ['EMAIL' => 'new@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'new@example.com']);
        $this->assertSame('010-1111-1111', Employee::query()->where('EMPNO', 'E001')->value('PHONENO'));
        $this->assertSame(1, (int) Employee::query()->where('EMPNO', 'E999')->value('STATUS'));
        $this->assertDatabaseMissing('department', ['DEPTNAME' => 'Rollback Team']);
    }
}
