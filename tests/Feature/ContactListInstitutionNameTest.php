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

class ContactListInstitutionNameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createContactTables();
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
            $table->string('School_Name', 255)->nullable();
            $table->string('Status', 50)->nullable();
            $table->boolean('ClassInOut')->default(true);
            $table->dateTime('Created_Date')->nullable();
        });
    }

    public function test_select_teacher_institution_uses_account_information_name_first(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-SSOT-CONTACT-1',
            'AccountName' => '레거시 연락처 기관명',
        ]);

        DB::table('S_Account_Information')->insert([
            'SK_Code' => 'SK-SSOT-CONTACT-1',
            'Account_Name' => '마스터 연락처 기관명',
        ]);

        Teacher::query()->create([
            'SK_Code' => 'SK-SSOT-CONTACT-1',
            'Name' => '테스트 교사',
            'Email' => 'teacher@example.com',
            'ClassInOut' => true,
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ContactList::class)
            ->call('selectTeacherInstitution', 'SK-SSOT-CONTACT-1')
            ->assertSet('newSkCode', 'SK-SSOT-CONTACT-1')
            ->assertSet('newSchoolName', '마스터 연락처 기관명');
    }
}
