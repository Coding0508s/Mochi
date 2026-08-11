<?php

namespace App\Livewire;

use App\Services\Store\StoreInventoryApiClient;
use App\Support\UnicodeTextNormalizer;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    public function exportToExcel(): ?StreamedResponse
    {
        $range = $this->resolveDateRange();
        if ($range === null) {
            session()->flash('error', $this->loadError ?? '조회 기간을 확인해 주세요.');

            return null;
        }

        [$start, $end] = $range;
        $keyword = trim($this->search);

        try {
            $rows = app(StoreInventoryApiClient::class)->fetchAllSaleHistoriesForExport(
                $keyword !== '' ? $keyword : null,
                $start,
                $end,
            );
        } catch (RuntimeException $exception) {
            session()->flash('error', $exception->getMessage());

            return null;
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('error', '엑셀 다운로드 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.');

            return null;
        }

        if ($rows === []) {
            session()->flash('error', '다운로드할 데이터가 없습니다.');

            return null;
        }

        try {
            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Store 판매내역');

            $headers = [
                '주문 일시',
                '주문번호',
                '기관명',
                '주문자',
                '상품코드',
                '상품명',
                '수량',
                '상태',
                '결제수단',
                '전하실 말씀',
            ];

            foreach ($headers as $index => $header) {
                $column = chr(65 + $index);
                $sheet->setCellValue($column.'1', $header);
                $sheet->getStyle($column.'1')->getFont()->setBold(true);
            }

            $row = 2;
            foreach ($rows as $item) {
                // 자유 입력(전하실 말씀 등)이 =로 시작해도 수식으로 해석되지 않도록 문자열로 기록
                $sheet->setCellValueExplicit('A'.$row, UnicodeTextNormalizer::toNfc((string) ($item->sold_at ?? '')), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('B'.$row, UnicodeTextNormalizer::toNfc((string) ($item->order_ref ?? '')), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('C'.$row, UnicodeTextNormalizer::toNfc((string) ($item->institution_nickname ?? '')), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('D'.$row, UnicodeTextNormalizer::toNfc((string) ($item->order_customer_name ?? '')), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('E'.$row, UnicodeTextNormalizer::toNfc((string) ($item->product_code ?? '')), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('F'.$row, UnicodeTextNormalizer::toNfc((string) ($item->product_name ?? '')), DataType::TYPE_STRING);
                $sheet->setCellValue('G'.$row, (int) ($item->qty ?? 0));
                $sheet->setCellValueExplicit('H'.$row, UnicodeTextNormalizer::toNfc((string) ($item->order_status ?? '')), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('I'.$row, UnicodeTextNormalizer::toNfc((string) ($item->order_reason ?? '')), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit('J'.$row, UnicodeTextNormalizer::toNfc((string) ($item->order_memo ?? '')), DataType::TYPE_STRING);

                $row++;
            }

            foreach (range('A', 'J') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'Store_판매내역_'.now()->format('Ymd_His').'.xlsx';

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename);
        } catch (Throwable $exception) {
            report($exception);
            session()->flash('error', '엑셀 다운로드 중 오류가 발생했습니다. 잠시 후 다시 시도해 주세요.');

            return null;
        }
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
