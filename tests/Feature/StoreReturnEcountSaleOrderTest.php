<?php

namespace Tests\Feature;

use App\Livewire\StoreReturnRegistrationForm;
use App\Models\InstitutionExternalMapping;
use App\Models\StoreReturnRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StoreReturnEcountSaleOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('store.data_source', 'ecount');
        Config::set('store.ecount.base_url', 'https://oapi.ecount.com');
        Config::set('store.ecount.zone', '');
        Config::set('store.ecount.session_id', 'test-session');
        Config::set('store.ecount.auto_login_when_empty_session', false);
        Config::set('store.ecount.product_code', '');
        Config::set('store.return_registration.ecount_enabled', false);
        Config::set('store.return_registration.ecount_product_codes', '');
        Config::set('store.return_registration.ecount_cache_ttl_seconds', 0);
        Config::set('store.ecount.fetch_product_names', false);
        Config::set('store.return_registration.sale_order_enabled', true);
        Config::set('store.return_registration.sale_order_endpoint', '/OAPI/V2/SaleOrder/SaveSaleOrder');
        Config::set('store.timeout', 5);
    }

    public function test_cs_can_create_ecount_sale_order_and_stores_slip_no(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/SaleOrder/SaveSaleOrder*' => Http::response([
                'Status' => '200',
                'Data' => [
                    'SuccessCnt' => 1,
                    'FailCnt' => 0,
                    'SlipNos' => ['20260708-21'],
                ],
                'Error' => null,
            ], 200),
        ]);

        $user = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        $this->seedInstitutionMapping('SK-ECOUNT-1');

        $groupKey = (string) Str::uuid();

        $anchor = StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->forRegistrationGroup($groupKey)
            ->create([
                'returned_at' => '2026-07-08',
                'institution_name' => 'Ecount 테스트 기관',
                'institution_sk_code' => 'SK-ECOUNT-1',
                'freight' => '선불',
                'item_name' => 'J11S-SSET-400',
                'quantity' => 2,
                'class_name' => '1학년 A반',
                'ecount_remarks' => '반품 적요',
                'shipping_address' => '울산 북구 배송지',
            ]);

        StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->forRegistrationGroup($groupKey)
            ->create([
                'returned_at' => '2026-07-08',
                'institution_name' => 'Ecount 테스트 기관',
                'institution_sk_code' => 'SK-ECOUNT-1',
                'freight' => '선불',
                'item_name' => 'J12S-SSET-400',
                'quantity' => 1,
                'class_name' => '2학년 B반',
                'ecount_remarks' => '반품 적요 2',
                'shipping_address' => '울산 북구 배송지',
            ]);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(StoreReturnRegistrationForm::class)
            ->call('openDetailModal', $anchor->id)
            ->call('createEcountSaleOrder', $anchor->id)
            ->assertHasNoErrors()
            ->assertSee('Ecount 주문서가 생성되었습니다.', false);

        $this->assertDatabaseHas('store_return_registrations', [
            'id' => $anchor->id,
            'ecount_slip_no' => '20260708-21',
        ]);

        $this->assertDatabaseMissing('store_return_registrations', [
            'registration_group_key' => $groupKey,
            'ecount_slip_no' => null,
        ]);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'SaveSaleOrder');
        });
    }

    public function test_create_blocked_when_slip_already_exists(): void
    {
        Http::fake();

        $user = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        $this->seedInstitutionMapping('SK-ECOUNT-2');

        $anchor = StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->create([
                'institution_sk_code' => 'SK-ECOUNT-2',
                'item_name' => 'J11S-SSET-400',
                'class_name' => '1학년',
                'ecount_remarks' => '적요',
                'ecount_slip_no' => 'EXISTING-SLIP',
            ]);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(StoreReturnRegistrationForm::class)
            ->call('openDetailModal', $anchor->id)
            ->call('createEcountSaleOrder', $anchor->id)
            ->assertSee('이미 생성된 주문서가 있습니다.', false);

        Http::assertNothingSent();

        $this->assertDatabaseHas('store_return_registrations', [
            'id' => $anchor->id,
            'ecount_slip_no' => 'EXISTING-SLIP',
        ]);
    }

    public function test_create_button_not_for_logistics_menu(): void
    {
        $user = User::factory()->create([
            'team' => 'CO',
            'is_admin' => false,
        ]);

        $registration = StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->create([
                'item_name' => 'J11S-SSET-400',
                'class_name' => '1학년',
                'ecount_remarks' => '적요',
            ]);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'logistics'])
            ->test(StoreReturnRegistrationForm::class)
            ->call('openDetailModal', $registration->id)
            ->assertDontSee('Ecount 주문서 생성', false);
    }

    public function test_create_ecount_sale_order_forbidden_for_logistics_menu(): void
    {
        $user = User::factory()->create([
            'team' => 'CO',
            'is_admin' => false,
        ]);

        $registration = StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->create([
                'item_name' => 'J11S-SSET-400',
            ]);

        $this->withoutExceptionHandling();

        $this->expectException(HttpException::class);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'logistics'])
            ->test(StoreReturnRegistrationForm::class)
            ->call('createEcountSaleOrder', $registration->id);
    }

    public function test_complete_does_not_call_ecount(): void
    {
        Http::fake();

        $user = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        $registration = StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->create([
                'institution_name' => '완료만 테스트',
                'item_name' => 'Unit 4',
                'status' => '접수',
            ]);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(StoreReturnRegistrationForm::class)
            ->call('completeReturnGroup', $registration->id);

        Http::assertNotSent(function ($request): bool {
            return str_contains($request->url(), 'SaveSaleOrder');
        });
    }

    public function test_api_failure_does_not_store_slip_no(): void
    {
        Http::fake([
            'https://oapi.ecount.com/OAPI/V2/SaleOrder/SaveSaleOrder*' => Http::response([
                'Status' => '500',
                'Data' => null,
                'Error' => ['Message' => '서버 오류'],
            ], 200),
        ]);

        $user = User::factory()->create([
            'team' => 'CS',
            'is_admin' => false,
        ]);

        $this->seedInstitutionMapping('SK-ECOUNT-3');

        $anchor = StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->create([
                'institution_sk_code' => 'SK-ECOUNT-3',
                'item_name' => 'J11S-SSET-400',
                'class_name' => '1학년',
                'ecount_remarks' => '적요',
            ]);

        Livewire::actingAs($user)
            ->withQueryParams(['team_menu' => 'cs'])
            ->test(StoreReturnRegistrationForm::class)
            ->call('createEcountSaleOrder', $anchor->id)
            ->assertSee('서버 오류', false);

        $this->assertDatabaseHas('store_return_registrations', [
            'id' => $anchor->id,
            'ecount_slip_no' => null,
        ]);
    }

    private function seedInstitutionMapping(string $skCode): void
    {
        InstitutionExternalMapping::query()->create([
            'institution_name' => '테스트 기관',
            'account_no' => 'X',
            'sk_code' => $skCode,
            'erp_institution_name' => '테스트 ERP 기관',
            'erp_account_no' => '1069626354',
            'portal_campus_id' => null,
        ]);
    }
}
