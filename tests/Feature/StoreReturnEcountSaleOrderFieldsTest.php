<?php

namespace Tests\Feature;

use App\Livewire\StoreReturnRegistrationForm;
use App\Models\StoreReturnRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class StoreReturnEcountSaleOrderFieldsTest extends TestCase
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

    public function test_save_detail_persists_class_name_ecount_remarks_and_shipping_address(): void
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
                'item_name' => 'Unit 4',
                'quantity' => 1,
                'status' => '정상',
            ]);

        StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->forRegistrationGroup($groupKey)
            ->create([
                'returned_at' => '2026-07-10',
                'institution_name' => '포도씨 유치원',
                'institution_sk_code' => 'SK1001',
                'freight' => '선불',
                'item_name' => 'Unit 2',
                'quantity' => 2,
                'status' => '접수',
            ]);

        $component = Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openDetailModal', $first->id)
            ->call('startDetailEdit');

        $detailItemRows = $component->get('detailItemRows');
        $detailItemRows[0]['className'] = '1학년 A반';
        $detailItemRows[0]['ecountRemarks'] = '반품 적요 1';
        $detailItemRows[1]['className'] = '2학년 B반';
        $detailItemRows[1]['ecountRemarks'] = '반품 적요 2';

        $component
            ->set('detailShippingAddress', '울산 북구 배송지 123')
            ->set('detailItemRows', $detailItemRows)
            ->call('saveDetail')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('store_return_registrations', [
            'id' => $first->id,
            'class_name' => '1학년 A반',
            'ecount_remarks' => '반품 적요 1',
            'shipping_address' => '울산 북구 배송지 123',
        ]);

        $this->assertDatabaseHas('store_return_registrations', [
            'institution_name' => '포도씨 유치원',
            'item_name' => 'Unit 2',
            'class_name' => '2학년 B반',
            'ecount_remarks' => '반품 적요 2',
            'shipping_address' => '울산 북구 배송지 123',
        ]);
    }

    public function test_list_shows_ecount_slip_no_when_present(): void
    {
        $user = User::factory()->create();

        StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->create([
                'institution_name' => '슬립 표시 유치원',
                'item_name' => 'Unit 1',
                'ecount_slip_no' => 'SO-2026-001',
            ]);

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->assertSee('슬립 표시 유치원', false)
            ->assertSee('SO-2026-001', false);
    }

    public function test_load_detail_fields_from_anchor_includes_ecount_fields(): void
    {
        $user = User::factory()->create();

        $registration = StoreReturnRegistration::factory()
            ->for($user, 'registrant')
            ->create([
                'class_name' => '3학년',
                'ecount_remarks' => '기존 적요',
                'shipping_address' => '서울 강남구',
                'item_name' => 'Unit 5',
            ]);

        Livewire::actingAs($user)
            ->test(StoreReturnRegistrationForm::class)
            ->call('openDetailModal', $registration->id)
            ->assertSet('detailShippingAddress', '서울 강남구')
            ->assertSet('detailItemRows.0.className', '3학년')
            ->assertSet('detailItemRows.0.ecountRemarks', '기존 적요');
    }
}
