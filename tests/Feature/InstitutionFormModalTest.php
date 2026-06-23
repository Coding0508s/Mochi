<?php

namespace Tests\Feature;

use App\Livewire\InstitutionFormModal;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionFormModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_embed_opens_modal_from_event(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test(InstitutionFormModal::class, [
                'embedMode' => 'manager',
            ])
            ->dispatch(
                'institution-form-open-manager',
                institutionId: 10,
                skCode: 'SK-OPEN',
                institutionName: '테스트 기관',
                co: 'Peter Kim',
                tr: 'Rami Lee',
                cs: 'Bella Joo',
            )
            ->assertSet('showManagerModal', true)
            ->assertSet('editSkCode', 'SK-OPEN')
            ->assertSet('editCo', 'Peter Kim');
    }

    public function test_manager_embed_does_not_open_without_permission(): void
    {
        Livewire::actingAs(User::factory()->create([
            'team' => '',
            'is_admin' => false,
            'can_view_all_institutions' => false,
        ]))
            ->test(InstitutionFormModal::class, [
                'embedMode' => 'manager',
            ])
            ->dispatch(
                'institution-form-open-manager',
                institutionId: 10,
                skCode: 'SK-DENY',
                institutionName: '권한 없음 기관',
                co: 'Peter Kim',
            )
            ->assertSet('showManagerModal', false);
    }

    public function test_manager_embed_save_requires_permission(): void
    {
        $this->createMinimalAccountTables();

        Livewire::actingAs(User::factory()->create([
            'team' => '',
            'is_admin' => false,
            'can_view_all_institutions' => false,
        ]))
            ->test(InstitutionFormModal::class, [
                'embedMode' => 'manager',
            ])
            ->set('editSkCode', 'SK-DENY-SAVE')
            ->set('editInstitutionName', '저장 차단')
            ->set('editCo', 'Peter Kim')
            ->call('saveManagers')
            ->assertHasErrors('managerEdit');
    }

    public function test_detail_embed_enters_edit_mode_from_event(): void
    {
        $this->createMinimalAccountTables();

        $institutionPayload = [
            'id' => 1,
            'skcode' => 'SK-DETAIL',
            'name' => '상세 편집 테스트',
            'english_name' => null,
            'portal_name' => null,
            'portal_campus_id' => null,
            'account_no' => null,
            'gubun' => null,
            'director' => null,
            'phone' => null,
            'account_tel' => null,
            'address' => null,
            'co' => 'Peter Kim',
            'tr' => null,
            'cs' => null,
            'customer_type' => null,
            'gs_no' => null,
        ];

        Livewire::test(InstitutionFormModal::class, [
            'embedMode' => 'detail',
        ])
            ->dispatch('institution-form-start-detail-edit', institution: $institutionPayload)
            ->assertSet('isEditingDetail', true)
            ->assertSet('editDetailCo', 'Peter Kim')
            ->assertSet('editDetailInstitutionName', '상세 편집 테스트')
            ->assertDispatched('institution-form-detail-edit-state', function (string $event, array $params): bool {
                return $event === 'institution-form-detail-edit-state'
                    && ($params['isEditing'] ?? null) === true;
            });
    }

    private function createMinimalAccountTables(): void
    {
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
            $table->string('CO', 255)->nullable();
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
        });
    }
}
