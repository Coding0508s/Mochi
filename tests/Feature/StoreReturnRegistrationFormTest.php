<?php

namespace Tests\Feature;

use App\Livewire\StoreReturnRegistrationForm;
use App\Models\AccountInformation;
use App\Models\Institution;
use App\Models\StoreReturnEcountProduct;
use App\Models\StoreReturnRegistration;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StoreReturnRegistrationFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('store.data_source', 'ecount');
        Config::set('store.ecount.product_code', '');
        Config::set('store.return_registration.ecount_enabled', true);
        Config::set('store.return_registration.ecount_product_codes', '');
        Config::set('store.return_registration.ecount_cache_ttl_seconds', 0);
        Config::set('store.ecount.fetch_product_names', false);
    }

    public function test_authenticated_user_can_access_return_registration_page(): void
    {
        $user = User::factory()->create([
            'team' => 'CO',
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('store.returns.index', ['team_menu' => 'logistics']))
            ->assertOk()
            ->assertSee('반품 등록', false)
            ->assertSee('Date', false)
            ->assertSee('기관명', false)
            ->assertSee('품목명', false)
            ->assertSee('담당 CS 팀', false);
    }

    public function test_create_modal_opens_from_register_button(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->assertSet('showCreateModal', false)
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true)
            ->assertSee('Date', false)
            ->assertSee('품목명', false)
            ->assertSee('행 추가하기', false);
    }

    public function test_guest_cannot_access_return_registration_page(): void
    {
        $this->get(route('store.returns.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_can_register_return_and_see_it_in_list(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openCreateModal')
            ->set('returnDate', '2026-07-09')
            ->set('institutionKeyword', '테스트 유치원')
            ->set('freight', '선불')
            ->set('itemRows.0.itemName', 'GrapeSEED 교재')
            ->set('itemRows.0.quantity', '3')
            ->set('itemRows.0.status', '정상')
            ->set('itemRows.0.notes', '박스 훼손')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showCreateModal', false)
            ->assertSee('반품이 등록되었습니다.', false)
            ->assertSee('테스트 유치원', false)
            ->assertSee('GrapeSEED 교재', false)
            ->assertSee('박스 훼손', false);

        $this->assertDatabaseHas('store_return_registrations', [
            'institution_name' => '테스트 유치원',
            'item_name' => 'GrapeSEED 교재',
            'quantity' => 3,
            'status' => '정상',
            'freight' => '선불',
            'notes' => '박스 훼손',
            'registered_by' => $user->id,
        ]);

        $this->assertSame(
            '2026-07-09',
            StoreReturnRegistration::query()->value('returned_at')?->format('Y-m-d'),
        );
    }

    public function test_teams_notification_is_sent_when_return_registration_is_saved(): void
    {
        Http::fake();

        config([
            'services.store_return_teams.webhook_url' => 'https://example.test/teams-webhook',
        ]);

        $user = User::factory()->create(['name' => '물류 담당자']);

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openCreateModal')
            ->set('returnDate', '2026-07-10')
            ->set('institutionKeyword', '분당 미금 꿈터유치원')
            ->set('freight', '선불')
            ->set('itemRows.0.itemName', 'Unit 1')
            ->set('itemRows.0.quantity', '12')
            ->set('itemRows.0.status', '정상')
            ->set('itemRows.0.notes', '스티커')
            ->call('save')
            ->assertHasNoErrors();

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://example.test/teams-webhook') {
                return false;
            }

            $data = $request->data();

            return ($data['summary'] ?? '') === '물류 반품 등록'
                && ($data['sections'][0]['facts'][0]['value'] ?? '') === '물류 담당자'
                && ($data['sections'][0]['facts'][2]['value'] ?? '') === '분당 미금 꿈터유치원';
        });
    }

    public function test_teams_notification_uses_english_registrant_name_when_available(): void
    {
        Http::fake();

        config([
            'services.store_return_teams.webhook_url' => 'https://example.test/teams-webhook',
        ]);

        if (! Schema::hasTable('employee')) {
            Schema::create('employee', function (Blueprint $table): void {
                $table->string('EMPNO')->primary();
                $table->string('WORKDEPT')->nullable();
                $table->string('KOREANAME')->nullable();
                $table->string('ENGLISHNAME')->nullable();
                $table->integer('STATUS')->nullable();
            });
        }

        DB::table('employee')->insert([
            'EMPNO' => 'E-LOG-001',
            'KOREANAME' => '허보석',
            'ENGLISHNAME' => 'Boseok Hur',
            'STATUS' => 1,
        ]);

        $user = User::factory()->create([
            'name' => '허보석',
            'employee_empno' => 'E-LOG-001',
        ]);

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openCreateModal')
            ->set('returnDate', '2026-07-10')
            ->set('institutionKeyword', '분당 미금 꿈터유치원')
            ->set('freight', '선불')
            ->set('itemRows.0.itemName', 'Unit 1')
            ->set('itemRows.0.quantity', '1')
            ->set('itemRows.0.status', '정상')
            ->call('save')
            ->assertHasNoErrors();

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://example.test/teams-webhook') {
                return false;
            }

            $data = $request->data();

            return ($data['sections'][0]['facts'][0]['value'] ?? '') === 'Boseok Hur';
        });
    }

    public function test_teams_notification_is_skipped_when_webhook_url_is_missing(): void
    {
        Http::fake();

        config([
            'services.store_return_teams.webhook_url' => null,
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openCreateModal')
            ->set('returnDate', '2026-07-10')
            ->set('institutionKeyword', '테스트 유치원')
            ->set('freight', '선불')
            ->set('itemRows.0.itemName', 'Unit 1')
            ->set('itemRows.0.quantity', '1')
            ->set('itemRows.0.status', '정상')
            ->call('save')
            ->assertHasNoErrors();

        Http::assertNothingSent();
    }

    public function test_create_modal_shows_ecount_product_dropdown_when_return_product_codes_are_configured(): void
    {
        Config::set('store.ecount.base_url', 'https://oapi.ecount.com');
        Config::set('store.ecount.session_id', 'session-123');
        Config::set('store.ecount.product_basic_endpoint', '/OAPI/V2/InventoryBasic/GetBasicProductsList');
        Config::set('store.ecount.fetch_product_names', true);
        Config::set('store.return_registration.ecount_product_codes', '00P228');

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => '00P228',
                            'PROD_DES' => 'GrapeSEED Unit 4',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openCreateModal')
            ->assertSee('품목명 또는 코드로 검색', false)
            ->assertSee('GrapeSEED Unit 4', false);
    }

    public function test_user_can_register_return_with_ecount_product_from_database_dropdown(): void
    {
        Config::set('store.ecount.base_url', 'https://oapi.ecount.com');
        Config::set('store.ecount.session_id', 'session-123');
        Config::set('store.ecount.product_basic_endpoint', '/OAPI/V2/InventoryBasic/GetBasicProductsList');
        Config::set('store.ecount.fetch_product_names', true);
        Config::set('store.return_registration.ecount_product_codes', '00P999');

        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/InventoryBasic/GetBasicProductsList*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'Result' => [
                        [
                            'PROD_CD' => '00P228',
                            'PROD_DES' => 'GrapeSEED Unit 4',
                        ],
                    ],
                ],
            ], 200),
        ]);

        StoreReturnEcountProduct::query()->create([
            'prod_cd' => '00P228',
            'product_name' => 'GrapeSEED Unit 4',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openCreateModal')
            ->set('returnDate', '2026-07-10')
            ->set('institutionKeyword', '테스트 유치원')
            ->set('freight', '선불')
            ->set('itemRows.0.itemName', '00P228')
            ->set('itemRows.0.quantity', '2')
            ->set('itemRows.0.status', '정상')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('store_return_registrations', [
            'institution_name' => '테스트 유치원',
            'item_name' => 'GrapeSEED Unit 4',
            'quantity' => 2,
        ]);
    }

    public function test_list_and_detail_show_display_name_when_stored_item_name_is_product_code(): void
    {
        StoreReturnEcountProduct::query()->create([
            'prod_cd' => 'U01C-CM-400',
            'product_name' => 'GrapeSEED Unit 1 Class Material',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        StoreReturnEcountProduct::query()->create([
            'prod_cd' => 'U12S-SB-400',
            'product_name' => 'GrapeSEED Unit 12 Student Book',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $user = User::factory()->create();
        $groupKey = (string) Str::uuid();

        $registrations = collect([
            ['U01C-CM-400', 3],
            ['U12S-SB-400', 13],
        ])->map(
            fn (array $row) => StoreReturnRegistration::factory()
                ->for($user, 'registrant')
                ->forRegistrationGroup($groupKey)
                ->create([
                    'returned_at' => '2026-07-10',
                    'institution_name' => '부산 광서 이든',
                    'institution_sk_code' => 'SK2792',
                    'item_name' => $row[0],
                    'quantity' => $row[1],
                ]),
        );

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->assertSee('GrapeSEED Unit 1 Class Material 외 1건', false)
            ->assertDontSee('U01C-CM-400', false)
            ->assertDontSee('U12S-SB-400', false)
            ->call('openDetailModal', $registrations->first()->id)
            ->assertSee('GrapeSEED Unit 1 Class Material', false)
            ->assertSee('GrapeSEED Unit 12 Student Book', false);
    }

    public function test_user_can_register_multiple_item_rows_at_once(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openCreateModal')
            ->set('returnDate', '2026-07-09')
            ->set('institutionKeyword', '테스트 유치원')
            ->set('freight', '착불')
            ->set('itemRows.0.itemName', '교재 A')
            ->set('itemRows.0.quantity', '1')
            ->set('itemRows.0.status', '정상')
            ->call('addItemRow')
            ->set('itemRows.1.itemName', '교재 B')
            ->set('itemRows.1.quantity', '2')
            ->set('itemRows.1.status', '정상')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('반품 2건이 등록되었습니다.', false)
            ->assertSee('교재 A 외 1건', false);

        $this->assertDatabaseCount('store_return_registrations', 2);
        $this->assertDatabaseHas('store_return_registrations', [
            'institution_name' => '테스트 유치원',
            'item_name' => '교재 A',
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('store_return_registrations', [
            'institution_name' => '테스트 유치원',
            'item_name' => '교재 B',
            'quantity' => 2,
            'freight' => '착불',
        ]);

        $groupKeys = StoreReturnRegistration::query()->pluck('registration_group_key')->unique()->values();
        $this->assertCount(1, $groupKeys);
        $this->assertNotNull($groupKeys->first());
    }

    public function test_return_registration_requires_required_fields(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openCreateModal')
            ->set('returnDate', '')
            ->set('institutionKeyword', '')
            ->set('itemRows.0.itemName', '')
            ->set('itemRows.0.quantity', '')
            ->call('save')
            ->assertHasErrors([
                'returnDate' => 'required',
                'institutionKeyword' => 'required',
                'itemRows.0.itemName' => 'required',
                'itemRows.0.quantity' => 'required',
            ]);
    }

    public function test_user_can_register_return_with_manually_typed_institution_name(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openCreateModal')
            ->set('returnDate', '2026-07-10')
            ->set('institutionKeyword', '직접 입력 기관')
            ->set('itemRows.0.itemName', '교재 A')
            ->set('itemRows.0.quantity', '1')
            ->set('itemRows.0.status', '정상')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('store_return_registrations', [
            'institution_name' => '직접 입력 기관',
            'institution_sk_code' => null,
            'item_name' => '교재 A',
        ]);
    }

    public function test_list_shows_single_row_with_item_summary_for_grouped_registrations(): void
    {
        $user = User::factory()->create();
        $groupKey = (string) Str::uuid();

        foreach (['Unit 4', 'Unit 2', 'Unit 1'] as $itemName) {
            StoreReturnRegistration::factory()
                ->for($user, 'registrant')
                ->forRegistrationGroup($groupKey)
                ->create([
                    'returned_at' => '2026-07-10',
                    'institution_name' => '포도씨 유치원',
                    'institution_sk_code' => 'SK1001',
                    'freight' => '선불',
                    'cs_team' => 'Bella Joo',
                    'item_name' => $itemName,
                ]);
        }

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->assertSee('Unit 4 외 2건', false)
            ->assertSee('포도씨 유치원', false)
            ->assertSee('Bella Joo', false);
    }

    public function test_second_registration_same_day_and_institution_stays_separate_from_completed_group(): void
    {
        $user = User::factory()->create();
        $completedBatch = (string) Str::uuid();

        StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->forRegistrationGroup($completedBatch)
            ->create([
                'returned_at' => '2026-07-10',
                'institution_name' => '포도씨 유치원',
                'institution_sk_code' => 'SK1001',
                'freight' => '선불',
                'item_name' => 'Unit 4',
                'status' => (string) config('store.return_registration.completed_status'),
            ]);

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openCreateModal')
            ->set('returnDate', '2026-07-10')
            ->set('institutionKeyword', '포도씨 유치원')
            ->set('freight', '선불')
            ->set('itemRows.0.itemName', 'Unit 1')
            ->set('itemRows.0.quantity', '1')
            ->set('itemRows.0.status', '정상')
            ->call('save')
            ->assertHasNoErrors();

        $registrations = StoreReturnRegistration::query()->orderBy('id')->get();
        $this->assertCount(2, $registrations);
        $this->assertNotSame(
            $registrations[0]->registration_group_key,
            $registrations[1]->registration_group_key,
        );

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->assertSee('Unit 4', false)
            ->assertSee('Unit 1', false)
            ->assertDontSee('Unit 4 외', false);
    }

    public function test_institution_click_opens_detail_modal_with_all_items(): void
    {
        $user = User::factory()->create();
        $groupKey = (string) Str::uuid();

        $registrations = collect(['Unit 4', 'Unit 2', 'Unit 1'])->map(
            fn (string $itemName) => StoreReturnRegistration::factory()
                ->for($user, 'registrant')
                ->forRegistrationGroup($groupKey)
                ->create([
                    'returned_at' => '2026-07-10',
                    'institution_name' => '포도씨 유치원',
                    'institution_sk_code' => 'SK1001',
                    'freight' => '선불',
                    'cs_team' => null,
                    'item_name' => $itemName,
                ]),
        );

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openDetailModal', $registrations->first()->id)
            ->assertSet('showDetailModal', true)
            ->assertSee('반품 상세', false)
            ->assertSee('Unit 4', false)
            ->assertSee('수정하기', false)
            ->assertSet('detailEditMode', false)
            ->call('startDetailEdit')
            ->assertSet('detailEditMode', true)
            ->call('cancelDetailEdit')
            ->assertSet('detailEditMode', false)
            ->call('closeDetailModal')
            ->assertSet('showDetailModal', false);
    }

    public function test_user_can_update_return_group_from_detail_modal(): void
    {
        $user = User::factory()->create();
        $groupKey = (string) Str::uuid();

        $first = StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->forRegistrationGroup($groupKey)
            ->create([
                'returned_at' => '2026-07-10',
                'institution_name' => '포도씨 유치원',
                'institution_sk_code' => 'SK1001',
                'freight' => '선불',
                'cs_team' => null,
                'item_name' => 'Unit 4',
                'quantity' => 1,
                'status' => '정상',
                'notes' => '스티커',
            ]);

        StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->forRegistrationGroup($groupKey)
            ->create([
                'returned_at' => '2026-07-10',
                'institution_name' => '포도씨 유치원',
                'institution_sk_code' => 'SK1001',
                'freight' => '선불',
                'cs_team' => null,
                'item_name' => 'Unit 2',
                'quantity' => 2,
                'status' => '접수',
                'notes' => null,
            ]);

        $component = Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openDetailModal', $first->id)
            ->call('startDetailEdit');

        $detailItemRows = $component->get('detailItemRows');
        $this->assertCount(2, $detailItemRows);
        $detailItemRows[0]['itemName'] = 'Unit 4 수정';
        $detailItemRows[0]['quantity'] = '3';
        $detailItemRows[0]['status'] = '기타';
        $detailItemRows[0]['notes'] = '수정 메모';

        $component
            ->set('detailReturnDate', '2026-07-11')
            ->set('detailInstitutionKeyword', '수정된 유치원')
            ->set('detailFreight', '착불')
            ->set('detailCsTeam', 'Chris Kim')
            ->set('detailItemRows', $detailItemRows)
            ->call('saveDetail')
            ->assertHasNoErrors()
            ->assertSet('showDetailModal', false)
            ->assertSee('반품 내역이 수정되었습니다.', false)
            ->assertSee('수정된 유치원', false)
            ->assertSee('Unit 4 수정 외 1건', false);

        $this->assertDatabaseHas('store_return_registrations', [
            'id' => $first->id,
            'returned_at' => '2026-07-11',
            'institution_name' => '수정된 유치원',
            'freight' => '착불',
            'cs_team' => 'Chris Kim',
            'item_name' => 'Unit 4 수정',
            'quantity' => 3,
            'status' => '기타',
            'notes' => '수정 메모',
        ]);

        $this->assertDatabaseHas('store_return_registrations', [
            'institution_name' => '수정된 유치원',
            'item_name' => 'Unit 2',
            'freight' => '착불',
            'cs_team' => 'Chris Kim',
        ]);
    }

    public function test_registration_stores_cs_team_when_institution_is_selected(): void
    {
        $this->createLegacyAccountTables();

        $user = User::factory()->create();

        Institution::query()->create([
            'SKcode' => 'SK-RETURN-1',
            'AccountName' => '테스트 유치원',
        ]);

        AccountInformation::query()->create([
            'SK_Code' => 'SK-RETURN-1',
            'Account_Name' => '테스트 유치원',
            'TR' => 'Coach A',
            'CS' => 'Bella Joo',
            'CO' => 'CO A',
            'Customer_Type' => 'GTS 13 기존',
        ]);

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openCreateModal')
            ->call('selectInstitution', 'SK-RETURN-1')
            ->assertSet('csTeam', 'Bella Joo')
            ->set('returnDate', '2026-07-10')
            ->set('itemRows.0.itemName', '교재 A')
            ->set('itemRows.0.quantity', '1')
            ->set('itemRows.0.status', '정상')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Bella Joo', false);

        $this->assertDatabaseHas('store_return_registrations', [
            'institution_sk_code' => 'SK-RETURN-1',
            'institution_name' => '테스트 유치원',
            'cs_team' => 'Bella Joo',
        ]);
    }

    private function createLegacyAccountTables(): void
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
            $table->string('TR', 255)->nullable();
            $table->string('CS', 255)->nullable();
            $table->string('CO', 255)->nullable();
            $table->string('Customer_Type', 255)->nullable();
        });
    }

    public function test_search_filters_registered_returns(): void
    {
        $user = User::factory()->create();

        StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'institution_name' => '알파 유치원',
            'item_name' => '교재 A',
        ]);

        StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'institution_name' => '베타 학원',
            'item_name' => '교재 B',
        ]);

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->set('search', '알파')
            ->assertSee('알파 유치원', false)
            ->assertDontSee('베타 학원', false);
    }

    public function test_search_filters_registered_returns_by_cs_team(): void
    {
        $user = User::factory()->create();

        StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'institution_name' => '알파 유치원',
            'item_name' => '교재 A',
            'cs_team' => 'Bella Joo',
        ]);

        StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'institution_name' => '베타 학원',
            'item_name' => '교재 B',
            'cs_team' => 'Chris Kim',
        ]);

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->set('search', 'Bella')
            ->assertSee('Bella Joo', false)
            ->assertDontSee('Chris Kim', false);
    }

    public function test_cs_team_menu_shows_complete_button_and_hides_register_button(): void
    {
        $user = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'institution_name' => '포도씨 유치원',
            'item_name' => 'Unit 4',
            'status' => '접수',
            'cs_team' => null,
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(StoreReturnRegistrationForm::class)
            ->assertSet('isCsTeamMenu', true)
            ->assertSee('완료', false)
            ->assertSee('처리 완료합니다', false)
            ->assertDontSee('wire:click="openCreateModal"', false);
    }

    public function test_logistics_menu_does_not_show_complete_button(): void
    {
        $user = User::factory()->create([
            'team' => 'CO',
            'is_admin' => false,
        ]);

        StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'institution_name' => '포도씨 유치원',
            'item_name' => 'Unit 4',
            'status' => '접수',
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'logistics'])
            ->test(StoreReturnRegistrationForm::class)
            ->assertSet('isCsTeamMenu', false)
            ->assertDontSee('>완료<', false)
            ->assertSee('>반품 등록<', false);
    }

    public function test_cs_team_can_complete_return_group_from_list(): void
    {
        $user = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        $first = StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'returned_at' => '2026-07-10',
            'institution_name' => '포도씨 유치원',
            'institution_sk_code' => 'SK1001',
            'freight' => '선불',
            'cs_team' => null,
            'item_name' => 'Unit 4',
            'status' => '접수',
        ]);

        $second = StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'returned_at' => '2026-07-10',
            'institution_name' => '포도씨 유치원',
            'institution_sk_code' => 'SK1001',
            'freight' => '선불',
            'cs_team' => null,
            'item_name' => 'Unit 2',
            'status' => '수거중',
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(StoreReturnRegistrationForm::class)
            ->call('completeReturnGroup', $first->id)
            ->assertSee('반품 처리가 완료되었습니다.', false)
            ->assertSee('포도씨 유치원', false)
            ->assertSee('>완료<', false);

        $this->assertDatabaseHas('store_return_registrations', [
            'id' => $first->id,
            'status' => '전표 등록 완료',
        ]);

        $this->assertDatabaseHas('store_return_registrations', [
            'id' => $second->id,
            'status' => '전표 등록 완료',
        ]);
    }

    public function test_cs_menu_context_persists_after_complete_for_non_cs_user(): void
    {
        $user = User::factory()->create([
            'team' => 'CO',
            'is_admin' => false,
        ]);

        $completed = StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'returned_at' => '2026-07-10',
            'institution_name' => '포도씨 유치원',
            'institution_sk_code' => 'SK1001',
            'freight' => '선불',
            'cs_team' => null,
            'item_name' => 'Unit 4',
            'status' => '접수',
        ]);

        StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'returned_at' => '2026-07-11',
            'institution_name' => '알파 유치원',
            'institution_sk_code' => 'SK2002',
            'freight' => '착불',
            'cs_team' => null,
            'item_name' => 'Unit 1',
            'status' => '접수',
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(StoreReturnRegistrationForm::class)
            ->assertSet('teamMenu', 'cs')
            ->assertSet('isCsTeamMenu', true)
            ->call('completeReturnGroup', $completed->id)
            ->assertSet('teamMenu', 'cs')
            ->assertSet('isCsTeamMenu', true)
            ->assertSee('알파 유치원', false)
            ->assertSee('wire:click="completeReturnGroup', false);
    }

    public function test_list_shows_in_progress_status_for_registered_returns(): void
    {
        $user = User::factory()->create();

        StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'institution_name' => '포도씨 유치원',
            'item_name' => 'Unit 4',
            'status' => '정상',
        ]);

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->assertSee('진행 중', false)
            ->assertDontSee('전표 등록 완료', false);
    }

    public function test_list_shows_completed_display_status_after_cs_completion(): void
    {
        $user = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        $registration = StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'institution_name' => '포도씨 유치원',
            'item_name' => 'Unit 4',
            'status' => '접수',
            'cs_team' => null,
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(StoreReturnRegistrationForm::class)
            ->assertSee('진행 중', false)
            ->call('completeReturnGroup', $registration->id)
            ->assertSee('전표 등록 완료', false)
            ->assertDontSee('>진행 중<', false);
    }

    public function test_cs_team_can_complete_return_group_from_detail_modal(): void
    {
        $user = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        $registration = StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'institution_name' => '포도씨 유치원',
            'item_name' => 'Unit 4',
            'status' => '접수',
            'cs_team' => null,
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(StoreReturnRegistrationForm::class)
            ->call('openDetailModal', $registration->id)
            ->assertSee('완료', false)
            ->assertDontSee('수정하기', false)
            ->call('completeReturnGroup', $registration->id)
            ->assertSet('isDetailGroupCompleted', true)
            ->assertSee('>완료<', false)
            ->assertSee('포도씨 유치원', false);

        $this->assertDatabaseHas('store_return_registrations', [
            'id' => $registration->id,
            'status' => '전표 등록 완료',
        ]);
    }

    public function test_status_filter_can_show_only_in_progress_groups(): void
    {
        $user = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'institution_name' => '진행 중 유치원',
            'item_name' => 'Unit 1',
            'status' => '접수',
            'cs_team' => null,
        ]);

        StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'institution_name' => '완료 유치원',
            'item_name' => 'Unit 2',
            'status' => '전표 등록 완료',
            'cs_team' => null,
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(StoreReturnRegistrationForm::class)
            ->set('statusFilter', 'in_progress')
            ->assertSee('진행 중 유치원', false)
            ->assertDontSee('완료 유치원', false);
    }

    public function test_status_filter_can_show_only_completed_groups(): void
    {
        $user = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'institution_name' => '진행 중 유치원',
            'item_name' => 'Unit 1',
            'status' => '접수',
            'cs_team' => null,
        ]);

        StoreReturnRegistration::factory()->for($user, 'registrant')->create([
            'institution_name' => '완료 유치원',
            'item_name' => 'Unit 2',
            'status' => '전표 등록 완료',
            'cs_team' => null,
        ]);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(StoreReturnRegistrationForm::class)
            ->set('statusFilter', 'completed')
            ->assertSee('완료 유치원', false)
            ->assertDontSee('진행 중 유치원', false);
    }

    public function test_admin_can_see_delete_button_and_remove_return_group(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $groupKey = (string) Str::uuid();

        $registrations = collect(['Unit 4', 'Unit 2', 'Unit 1'])->map(
            fn (string $itemName) => StoreReturnRegistration::factory()
                ->for($admin, 'registrant')
                ->forRegistrationGroup($groupKey)
                ->create([
                    'returned_at' => '2026-07-10',
                    'institution_name' => '삭제 대상 유치원',
                    'institution_sk_code' => 'SK9001',
                    'item_name' => $itemName,
                ]),
        );

        $anchorId = $registrations->first()->id;

        Livewire::actingAs($admin)
            ->test(StoreReturnRegistrationForm::class)
            ->assertSeeHtml('wire:click="deleteReturnGroup('.$anchorId.')"')
            ->call('deleteReturnGroup', $anchorId);

        $this->assertDatabaseCount('store_return_registrations', 0);
    }

    public function test_non_admin_cannot_see_delete_button_or_delete_return_group(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $groupKey = (string) Str::uuid();

        $registration = StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->forRegistrationGroup($groupKey)
            ->create([
                'returned_at' => '2026-07-10',
                'institution_name' => '보호 대상 유치원',
                'item_name' => 'Unit 1',
            ]);

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->assertDontSeeHtml('wire:click="deleteReturnGroup(')
            ->assertSet('canDeleteReturnGroups', false);

        $this->withoutExceptionHandling();

        $this->expectException(HttpException::class);

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('deleteReturnGroup', $registration->id);
    }
}
