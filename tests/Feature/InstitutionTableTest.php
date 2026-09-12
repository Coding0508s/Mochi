<?php

namespace Tests\Feature;

use App\Livewire\InstitutionTable;
use App\Models\AccountInformation;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAccountTables();
    }

    private function createAccountTables(): void
    {
        Schema::dropIfExists('S_Account_Information');
        Schema::dropIfExists('S_AccountName');

        Schema::create('S_AccountName', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SKcode', 100)->unique();
            $table->string('AccountName', 255);
            $table->string('Address', 255)->nullable();
        });

        Schema::create('S_Account_Information', function (Blueprint $table): void {
            $table->increments('ID');
            $table->string('SK_Code', 100);
            $table->string('Account_Name', 255)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
            $table->string('Customer_Type', 255)->nullable();
            $table->string('Address', 255)->nullable();
            $table->timestamp('FGC_CreateDate')->nullable();
            $table->timestamp('FGC_LastModifyDate')->nullable();
        });
    }

    public function test_lists_account_information_rows_with_eager_loaded_institution(): void
    {
        $user = User::factory()->create();

        DB::table('S_Account_Information')->insert([
            [
                'SK_Code' => 'SK-TABLE-1',
                'Account_Name' => '테이블 기관 1',
                'FGC_CreateDate' => '2024-01-11 18:18:51',
            ],
            [
                'SK_Code' => 'SK-TABLE-2',
                'Account_Name' => '테이블 기관 2',
                'FGC_CreateDate' => '2024-01-11 18:18:52',
            ],
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionTable::class)
            ->assertViewHas('institutions', function ($institutions): bool {
                return $institutions->count() === 2
                    && $institutions->first()->relationLoaded('institution');
            });
    }

    public function test_row_click_dispatches_institution_row_selected(): void
    {
        $user = User::factory()->create();

        $account = AccountInformation::query()->create([
            'SK_Code' => 'SK-ROW-CLICK',
            'Account_Name' => '행 클릭 기관',
        ]);

        Livewire::actingAs($user)
            ->test(InstitutionTable::class)
            ->call('selectRow', $account->ID)
            ->assertDispatched('institution-row-selected', institutionId: $account->ID);
    }

    public function test_shows_all_matching_rows_without_pagination(): void
    {
        $user = User::factory()->create();

        for ($index = 1; $index <= 21; $index++) {
            DB::table('S_Account_Information')->insert([
                'SK_Code' => 'SK-PAGE-'.$index,
                'Account_Name' => '페이지 기관 '.$index,
                'FGC_CreateDate' => '2024-01-11 18:18:'.str_pad((string) $index, 2, '0', STR_PAD_LEFT),
            ]);
        }

        $html = Livewire::actingAs($user)
            ->test(InstitutionTable::class)
            ->assertViewHas('institutions', function ($institutions): bool {
                return $institutions->count() === 21;
            })
            ->html();

        $this->assertStringContainsString('institution-list-table-scroll', $html);
        $this->assertStringNotContainsString('mochi-pagination-nav', $html);
    }
}
