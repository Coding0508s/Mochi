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
use Tests\TestCase;

class ContactListTeamScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createContactTables();
    }

    public function test_coach_team_contact_list_shows_all_team_assignments(): void
    {
        $coachUser = User::factory()->create([
            'team' => 'COACH',
            'name' => 'Current Coach',
            'email' => 'current.coach@example.com',
        ]);

        $this->seedTeacherWithAssignments(
            'SK-COACH-TEAM',
            '다른 Coach 담당 교사',
            ['TR' => 'Other Coach', 'CS' => null, 'CO' => null],
        );
        $this->seedTeacherWithAssignments(
            'SK-CS-ONLY',
            'CS 담당 교사',
            ['TR' => null, 'CS' => 'CS Manager', 'CO' => null],
        );

        Livewire::actingAs($coachUser)
            ->withQueryParams(['team_menu' => 'coach'])
            ->test(ContactList::class)
            ->assertSee('다른 Coach 담당 교사')
            ->assertSee('CS 담당 교사');
    }

    public function test_cs_team_contact_list_shows_all_team_assignments(): void
    {
        $csUser = User::factory()->create([
            'team' => 'CS',
            'name' => 'Current CS',
            'email' => 'current.cs@example.com',
        ]);

        $this->seedTeacherWithAssignments(
            'SK-CS-TEAM',
            'CS 팀 담당 교사',
            ['TR' => null, 'CS' => 'CS Manager', 'CO' => null],
        );
        $this->seedTeacherWithAssignments(
            'SK-COACH-ONLY',
            'Coach 담당 교사',
            ['TR' => 'Coach Manager', 'CS' => null, 'CO' => null],
        );

        Livewire::actingAs($csUser)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(ContactList::class)
            ->assertSee('CS 팀 담당 교사')
            ->assertSee('Coach 담당 교사');
    }

    public function test_contact_list_shows_all_assignments_even_when_other_team_menu_is_requested(): void
    {
        $coachUser = User::factory()->create([
            'team' => 'COACH',
            'name' => 'Current Coach',
            'email' => 'current.coach.cross@example.com',
        ]);

        $this->seedTeacherWithAssignments(
            'SK-HOME-COACH',
            '홈 Coach 팀 교사',
            ['TR' => 'Coach Manager', 'CS' => null, 'CO' => null],
        );
        $this->seedTeacherWithAssignments(
            'SK-OTHER-CS',
            '다른 CS 팀 교사',
            ['TR' => null, 'CS' => 'CS Manager', 'CO' => null],
        );

        Livewire::actingAs($coachUser)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(ContactList::class)
            ->assertSee('홈 Coach 팀 교사')
            ->assertSee('다른 CS 팀 교사');
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
