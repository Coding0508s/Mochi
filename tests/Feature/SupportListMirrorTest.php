<?php

namespace Tests\Feature;

use App\Livewire\SupportList;
use App\Models\CoNewTarget;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SupportListMirrorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSupportTables();
    }

    private function createSupportTables(): void
    {
        Schema::dropIfExists('S_CO_NewTarget_Detail');
        Schema::dropIfExists('S_CO_NewTarget');
        Schema::dropIfExists('S_SupportInfo_Account');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
        });

        Schema::create('S_SupportInfo_Account', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('SK_Code', 100)->nullable();
            $table->string('Account_Name', 255)->nullable();
            $table->string('TR_Name', 255)->nullable();
            $table->string('Support_Date', 50)->nullable();
            $table->string('Meet_Time', 50)->nullable();
            $table->string('Support_Type', 100)->nullable();
            $table->string('Target', 255)->nullable();
            $table->text('Issue')->nullable();
            $table->text('TO_Account')->nullable();
            $table->text('TO_Depart')->nullable();
            $table->string('Status', 50)->nullable();
            $table->timestamp('CompletedDate')->nullable();
            $table->timestamp('CreatedDate')->nullable();
        });

        Schema::create('S_CO_NewTarget', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->date('CreatedDate')->nullable();
            $table->string('AccountManager', 100)->nullable();
            $table->string('AccountCode', 100)->nullable();
            $table->string('AccountName', 150);
            $table->boolean('IsContract')->default(false);
            $table->string('Possibility', 20)->nullable();
            $table->string('Type', 100)->nullable();
            $table->string('Gubun', 100)->nullable();
            $table->integer('LS')->default(0);
            $table->integer('GS_K')->default(0);
            $table->integer('GS_E')->default(0);
            $table->integer('Total')->default(0);
            $table->integer('Approaching')->default(0);
            $table->integer('Presenting')->default(0);
            $table->integer('Consulting')->default(0);
            $table->integer('Closing')->default(0);
            $table->integer('DroppedOut')->default(0);
            $table->string('Address', 255)->nullable();
            $table->string('Director', 100)->nullable();
            $table->string('Phone', 50)->nullable();
            $table->string('Connected', 100)->nullable();
            $table->date('ContractedDate')->nullable();
        });

        Schema::create('S_CO_NewTarget_Detail', function (Blueprint $table): void {
            $table->increments('ID');
            $table->integer('Year')->nullable();
            $table->string('AccountName', 150);
            $table->string('AccountManager', 100)->nullable();
            $table->date('MeetingDate');
            $table->string('MeetingTime', 20)->nullable();
            $table->string('MeetingTime_End', 20)->nullable();
            $table->text('Description')->nullable();
            $table->string('ConsultingType', 100)->nullable();
            $table->string('Possibility', 20)->nullable();
        });
    }

    public function test_edit_save_does_not_create_detail_row_for_uncontracted_potential_target(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-EDIT-1',
            'AccountName' => '편집 대상 기관',
        ]);

        CoNewTarget::query()->create([
            'AccountCode' => 'SK-EDIT-1',
            'AccountName' => '편집 대상 기관',
            'AccountManager' => '잠재 담당자',
            'IsContract' => false,
            'Possibility' => 'A',
        ]);

        $record = SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK-EDIT-1',
            'Account_Name' => '편집 대상 기관',
            'TR_Name' => '초기 담당',
            'Support_Date' => '2026-04-01',
            'Meet_Time' => '09:00:00',
            'Support_Type' => '전화',
            'Target' => '초기 참석자',
            'TO_Account' => '초기 소통',
            'Status' => '진행중',
            'CreatedDate' => now(),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->call('openEditModal', $record->ID)
            ->set('formSupportDate', '2026-04-12')
            ->set('formSupportTime', '11:20')
            ->set('formSupportType', '대면')
            ->set('formToAccount', '수정된 소통 내용')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('S_CO_NewTarget_Detail', [
            'AccountName' => '편집 대상 기관',
            'AccountManager' => '잠재 담당자',
            'MeetingDate' => '2026-04-12 00:00:00',
            'MeetingTime' => '11:20',
            'ConsultingType' => '대면',
            'Possibility' => 'A',
            'Description' => '수정된 소통 내용',
        ]);
    }

    public function test_edit_save_does_not_mirror_for_contracted_target(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-EDIT-2',
            'AccountName' => '계약 완료 기관',
        ]);

        CoNewTarget::query()->create([
            'AccountCode' => 'SK-EDIT-2',
            'AccountName' => '계약 완료 기관',
            'IsContract' => true,
        ]);

        $record = SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK-EDIT-2',
            'Account_Name' => '계약 완료 기관',
            'TR_Name' => '초기 담당',
            'Support_Date' => '2026-04-01',
            'Meet_Time' => '09:00:00',
            'Support_Type' => '전화',
            'Target' => '초기 참석자',
            'TO_Account' => '초기 소통',
            'Status' => '진행중',
            'CreatedDate' => now(),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->call('openEditModal', $record->ID)
            ->set('formSupportDate', '2026-04-12')
            ->set('formSupportTime', '11:20')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('S_CO_NewTarget_Detail', [
            'AccountName' => '계약 완료 기관',
            'MeetingDate' => '2026-04-12 00:00:00',
        ]);
    }
}

