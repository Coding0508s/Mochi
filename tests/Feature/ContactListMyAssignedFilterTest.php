<?php

namespace Tests\Feature;

use App\Livewire\ContactList;
use App\Models\Institution;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
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
            ->assertSee('내 담당만')
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
        Carbon::setTestNow($now);

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
            Carbon::setTestNow();
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
