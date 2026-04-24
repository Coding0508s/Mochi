<?php

namespace App\Livewire;

use App\Services\Store\StoreInventoryApiClient;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class StoreSalesHistoryList extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 20;

    public string $dateStart = '';

    public string $dateEnd = '';

    public ?string $loadError = null;

    public function mount(): void
    {
        $this->dateEnd = Carbon::now()->format('Y-m-d');
        $this->dateStart = Carbon::now()->subDays(30)->format('Y-m-d');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function applyDateFilter(): void
    {
        $this->resetPage();
    }

    private function resolveDateRange(): ?array
    {
        try {
            $start = Carbon::parse($this->dateStart)->startOfDay();
            $end = Carbon::parse($this->dateEnd)->endOfDay();
        } catch (Throwable) {
            $this->loadError = '시작일/종료일 형식이 올바르지 않습니다. (YYYY-MM-DD)';

            return null;
        }

        if ($end->lessThan($start)) {
            $this->loadError = '종료일은 시작일과 같거나 이후여야 합니다.';

            return null;
        }

        return [$start, $end];
    }

    private function emptyPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            [],
            0,
            max(1, $this->perPage),
            $this->getPage(),
            [
                'path' => request()->url(),
                'pageName' => 'page',
            ]
        );
    }

    public function render()
    {
        $this->loadError = null;
        $paginatedItems = $this->emptyPaginator();

        $range = $this->resolveDateRange();

        if ($range !== null) {
            [$start, $end] = $range;

            try {
                $keyword = trim($this->search);
                $paginatedItems = app(StoreInventoryApiClient::class)->fetchAllPaginatedSaleHistories(
                    $keyword !== '' ? $keyword : null,
                    $start,
                    $end,
                    max(1, $this->perPage),
                );
            } catch (Throwable $exception) {
                report($exception);
                $this->loadError = '판매내역 데이터를 불러오지 못했습니다. 잠시 후 다시 시도해 주세요.';
                $paginatedItems = $this->emptyPaginator();
            }
        }

        return view('livewire.store-sales-history-list', [
            'paginatedItems' => $paginatedItems,
        ]);
    }
}
