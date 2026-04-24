<?php

namespace Tests\Feature;

use App\Livewire\StoreSalesHistoryList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Tests\TestCase;

class StoreSalesHistoryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('store.data_source', 'ecount');
        Config::set('store.sales_history_source', 'ecount');
    }

    public function test_sales_history_page_shows_error_when_source_is_not_gnuboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('store.sales.index'))
            ->assertOk()
            ->assertSee('판매내역 데이터를 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.');
    }

    public function test_sales_history_page_rejects_invalid_date_range(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StoreSalesHistoryList::class)
            ->set('dateStart', '2026-04-23')
            ->set('dateEnd', '2026-04-01')
            ->call('applyDateFilter')
            ->assertSee('종료일은 시작일과 같거나 이후여야 합니다.');
    }
}
