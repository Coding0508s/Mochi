<?php

namespace Tests\Feature;

use App\Livewire\SupportCreateForm;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class SupportCreateFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSupportTables();
    }

    private function createSupportTables(): void
    {
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
    }

    public function test_selecting_institution_fills_default_templates_when_empty(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-TPL-1',
            'AccountName' => '템플릿 테스트 기관',
        ]);

        $user = User::factory()->create();

        $expectedAccount = config('support_report_defaults.to_account_template');
        $expectedDepart = config('support_report_defaults.to_depart_template');

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-TPL-1')
            ->assertSet('formToAccount', $expectedAccount)
            ->assertSet('formToDepart', $expectedDepart);
    }

    public function test_selecting_institution_does_not_overwrite_existing_content(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-TPL-2',
            'AccountName' => '기존 내용 기관',
        ]);

        $user = User::factory()->create();
        $existing = '이미 작성한 소통 내용';

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->set('formToAccount', $existing)
            ->set('formToDepart', '타부서 기존')
            ->call('selectInstitution', 'SK-TPL-2')
            ->assertSet('formToAccount', $existing)
            ->assertSet('formToDepart', '타부서 기존');
    }

    public function test_save_persists_to_account_and_to_depart(): void
    {
        Institution::query()->create([
            'SKcode' => 'SK-SAVE-1',
            'AccountName' => '저장 테스트',
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(SupportCreateForm::class)
            ->call('selectInstitution', 'SK-SAVE-1')
            ->set('formToAccount', '기관 소통 본문')
            ->set('formToDepart', '타부서 공유 본문')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('S_SupportInfo_Account', [
            'SK_Code' => 'SK-SAVE-1',
            'TO_Account' => '기관 소통 본문',
            'TO_Depart' => '타부서 공유 본문',
        ]);
    }
}
