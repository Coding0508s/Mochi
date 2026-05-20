<?php

namespace Tests\Feature;

use App\Livewire\SupportList;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SupportListDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSupportListTables();
    }

    private function createMinimalSupportListTables(): void
    {
        Schema::dropIfExists('S_SupportInfo_Account');
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
    }

    public function test_admin_can_delete_support_record_from_list(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-DEL-1',
            'AccountName' => '삭제 테스트 기관',
        ]);

        $record = SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK-DEL-1',
            'Account_Name' => '삭제 테스트 기관',
            'TR_Name' => 'CO',
            'Support_Date' => '2026-04-20',
            'Meet_Time' => '10:00:00',
            'Support_Type' => '전화',
            'Status' => '진행중',
            'CreatedDate' => now(),
        ]);

        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(SupportList::class)
            ->call('deleteRecord', (int) $record->ID)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('S_SupportInfo_Account', [
            'ID' => $record->ID,
        ]);
    }

    public function test_non_admin_cannot_delete_support_record(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-DEL-2',
            'AccountName' => '권한 테스트',
        ]);

        $record = SupportRecord::query()->create([
            'Year' => 2026,
            'SK_Code' => 'SK-DEL-2',
            'Account_Name' => '권한 테스트',
            'TR_Name' => 'CO',
            'Support_Date' => '2026-04-20',
            'Meet_Time' => '10:00:00',
            'Support_Type' => '전화',
            'Status' => '진행중',
            'CreatedDate' => now(),
        ]);

        $user = User::factory()->create(['is_admin' => false]);

        Livewire::actingAs($user)
            ->test(SupportList::class)
            ->call('deleteRecord', (int) $record->ID);

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'ID' => $record->ID,
        ]);
    }
}
