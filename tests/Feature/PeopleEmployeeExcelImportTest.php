<?php

namespace Tests\Feature;

use App\Livewire\PeopleEmployeesList;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobTitlePermission;
use App\Models\User;
use App\Support\EmployeeExcelImporter;
use App\Support\EmployeeImportRollback;
use App\Support\JobTitlePermissionSynchronizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PeopleEmployeeExcelImportTest extends TestCase
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
        ]);

        Employee::query()->create([
            'EMPNO' => 'E001',
            'KOREANAME' => '홍길동',
            'ENGLISHNAME' => 'Hong',
            'JOB' => 'Staff',
            'EMAIL' => 'hong@example.com',
            'PHONENO' => '010-1111-1111',
            'WORKDEPT' => 'A01',
            'STATUS' => 1,
        ]);
    }

    /**
     * @param  array<int, array<int, string>>  $dataRows
     */
    private function makeImportFile(array $dataRows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['성명', '부서', '전화', '모바일', 'Email', '사내전화(내선)'],
        ], null, 'A1');

        $rowNumber = 2;
        foreach ($dataRows as $dataRow) {
            $sheet->fromArray([$dataRow], null, 'A'.$rowNumber);
            $rowNumber++;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'employee-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);

        return UploadedFile::fake()->createWithContent(
            'employees.xlsx',
            (string) file_get_contents($tempPath),
        );
    }

    public function test_admin_can_preview_and_apply_employee_import(): void
    {
        $admin = User::factory()->admin()->create();
        $file = $this->makeImportFile([
            ['홍길동', '팀 A', '', '010-9999-9999', 'hong@example.com', ''],
        ]);

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->set('importFile', $file)
            ->call('previewEmployeeImport')
            ->assertSet('importPreview.updated', 1)
            ->set('importFile', $this->makeImportFile([
                ['홍길동', '팀 A', '', '010-9999-9999', 'hong@example.com', ''],
            ]))
            ->call('applyEmployeeImport')
            ->assertSet('importPreview', null);

        $this->assertSame('010-9999-9999', Employee::query()->where('EMPNO', 'E001')->value('PHONENO'));
    }

    public function test_non_admin_cannot_preview_employee_import(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test(PeopleEmployeesList::class)
            ->call('previewEmployeeImport')
            ->assertForbidden();
    }

    public function test_admin_can_reset_last_employee_import(): void
    {
        Cache::flush();

        $admin = User::factory()->admin()->create();
        $result = app(EmployeeExcelImporter::class)->importRows(
            [
                ['성명', '부서', '전화', '모바일', 'Email', '사내전화(내선)'],
                ['김신규', '팀 A', '', '010-2222-2222', 'rollback@example.com', ''],
            ],
            $admin->id,
            dryRun: false,
        );

        $this->assertArrayHasKey('rollback', $result);
        EmployeeImportRollback::save($result['rollback']);

        Livewire::actingAs($admin)
            ->test(PeopleEmployeesList::class)
            ->set('importResetConfirmationText', PeopleEmployeesList::IMPORT_RESET_CONFIRMATION_PHRASE)
            ->call('resetLastEmployeeImport')
            ->assertHasNoErrors();

        $this->assertFalse(EmployeeImportRollback::hasPending());
        $this->assertDatabaseMissing('employee', ['EMAIL' => 'rollback@example.com']);
    }

    public function test_non_admin_cannot_reset_last_employee_import(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test(PeopleEmployeesList::class)
            ->call('resetLastEmployeeImport')
            ->assertForbidden();
    }

    public function test_new_imported_user_gets_permissions_from_job_title_matrix(): void
    {
        $defaultJob = (string) config('employee_import.default_job', 'Staff');

        JobTitlePermission::query()->create([
            'job_code' => $defaultJob,
            'setup_view' => false,
            'setup_manage' => true,
            'can_manage_store_inventory' => true,
            'is_gs_brochure_admin' => false,
            'is_coach_team_lead' => true,
            'can_view_all_institutions' => false,
            'is_deputy_admin' => false,
        ]);

        $admin = User::factory()->admin()->create();

        app(EmployeeExcelImporter::class)->importRows(
            [
                ['성명', '부서', '전화', '모바일', 'Email', '사내전화(내선)'],
                ['매트릭스신규', '팀 A', '', '010-3333-3333', 'matrix-new@example.com', ''],
            ],
            $admin->id,
            dryRun: false,
        );

        $user = User::query()->where('email', 'matrix-new@example.com')->firstOrFail();
        $this->assertTrue((bool) $user->setup_manage);
        $this->assertTrue((bool) $user->setup_view);
        $this->assertTrue((bool) $user->can_manage_store_inventory);
        $this->assertTrue((bool) $user->is_coach_team_lead);
        $this->assertFalse((bool) $user->is_gs_brochure_admin);
        $this->assertFalse((bool) $user->is_deputy_admin);
    }

    public function test_new_imported_user_keeps_false_flags_when_matrix_row_missing(): void
    {
        $admin = User::factory()->admin()->create();

        app(EmployeeExcelImporter::class)->importRows(
            [
                ['성명', '부서', '전화', '모바일', 'Email', '사내전화(내선)'],
                ['노매트릭스', '팀 A', '', '010-4444-4444', 'no-matrix@example.com', ''],
            ],
            $admin->id,
            dryRun: false,
        );

        $user = User::query()->where('email', 'no-matrix@example.com')->firstOrFail();
        foreach (JobTitlePermissionSynchronizer::FLAG_COLUMNS as $column) {
            $this->assertFalse((bool) $user->{$column}, $column);
        }
    }
}
