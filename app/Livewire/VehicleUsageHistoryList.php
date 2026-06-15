<?php

namespace App\Livewire;

use App\Models\SharedSupplyItem;
use App\Models\VehicleUsageLog;
use App\Support\VehicleArrivalLocation;
use App\Support\VehicleUsageLogRemark;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleUsageHistoryList extends Component
{
    use WithPagination;

    public string $selectedVehicle = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $today = now();
        $this->dateFrom = $today->copy()->startOfMonth()->format('Y-m-d');
        $this->dateTo = $today->copy()->endOfMonth()->format('Y-m-d');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['selectedVehicle', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
        }
    }

    public function exportToExcel(): ?StreamedResponse
    {
        try {
            $logs = $this->buildQuery()->with(['user', 'sharedSupply'])->get();

            if ($logs->isEmpty()) {
                session()->flash('error', '다운로드할 데이터가 없습니다.');

                return null;
            }

            $spreadsheet = new Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('차량 사용 내역');

            $headers = ['사용 일시', '사용자', '차량명', '운행 목적', '도착지', '운행 거리(km)', '상태'];
            foreach ($headers as $index => $header) {
                $column = chr(65 + $index);
                $sheet->setCellValue($column.'1', $header);
                $sheet->getStyle($column.'1')->getFont()->setBold(true);
            }

            $row = 2;
            foreach ($logs as $log) {
                $dateStr = $log->driven_on ? $log->driven_on->format('Y-m-d') : '';
                if ($log->sharedSupply) {
                    $dateStr = $log->sharedSupply->starts_at->format('Y-m-d H:i').' ~ '.$log->sharedSupply->ends_at->format('H:i');
                }

                $status = $log->odometer_after === null ? '운행 중' : '운행 완료';

                $purpose = trim((string) $log->usage_purpose_name);
                $displayRemark = VehicleUsageLogRemark::forDisplay((string) $log->remarks);
                if ($displayRemark !== '') {
                    $purpose .= $purpose ? ' - '.$displayRemark : $displayRemark;
                }

                $arrivalDisplay = VehicleArrivalLocation::forDisplay($log->arrival_location);
                if ($arrivalDisplay === '') {
                    $arrivalDisplay = $displayRemark;
                }

                $sheet->setCellValue('A'.$row, $dateStr);
                $sheet->setCellValue('B'.$row, $log->user?->name ?? '');
                $sheet->setCellValue('C'.$row, $log->vehicle_name);
                $sheet->setCellValue('D'.$row, $purpose);
                $sheet->setCellValue('E'.$row, $arrivalDisplay);
                $sheet->setCellValue('F'.$row, $log->distance ?? 0);
                $sheet->setCellValue('G'.$row, $status);

                $row++;
            }

            foreach (range('A', 'G') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, '차량_사용_내역_'.now()->format('Ymd_His').'.xlsx');
        } catch (\Exception $e) {
            session()->flash('error', '엑셀 다운로드 중 오류가 발생했습니다.');

            return null;
        }
    }

    private function buildQuery(): Builder
    {
        return VehicleUsageLog::query()
            ->when($this->selectedVehicle !== '', function (Builder $query) {
                $query->where('vehicle_name', $this->selectedVehicle);
            })
            ->when($this->dateFrom !== '', function (Builder $query) {
                $query->whereDate('driven_on', '>=', $this->dateFrom);
            })
            ->when($this->dateTo !== '', function (Builder $query) {
                $query->whereDate('driven_on', '<=', $this->dateTo);
            })
            ->orderByDesc('driven_on')
            ->orderByDesc('vehicle_usage_logs.id');
    }

    /**
     * @return array{total_count: int, total_distance: int, total_minutes: int}
     */
    private function getSummaryMetrics(): array
    {
        $query = $this->buildQuery();

        $query->join('shared_supplies', 'vehicle_usage_logs.shared_supply_id', '=', 'shared_supplies.id')
            ->selectRaw('COUNT(vehicle_usage_logs.id) as total_count')
            ->selectRaw('SUM(vehicle_usage_logs.distance) as total_distance');

        if (DB::connection()->getDriverName() === 'sqlite') {
            $query->selectRaw('SUM((julianday(shared_supplies.ends_at) - julianday(shared_supplies.starts_at)) * 24 * 60) as total_minutes');
        } else {
            $query->selectRaw('SUM(TIMESTAMPDIFF(MINUTE, shared_supplies.starts_at, shared_supplies.ends_at)) as total_minutes');
        }

        $metrics = $query->first();

        return [
            'total_count' => (int) ($metrics->total_count ?? 0),
            'total_distance' => (int) ($metrics->total_distance ?? 0),
            'total_minutes' => (int) round((float) ($metrics->total_minutes ?? 0)),
        ];
    }

    public function render(): View
    {
        $vehicles = SharedSupplyItem::query()
            ->active()
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->filter(function ($item) {
                return preg_match('/\d{2,3}[가-힣]\d{4}/u', $item->name) === 1;
            })->values();

        $logs = $this->buildQuery()
            ->with(['user', 'sharedSupply'])
            ->paginate(20);

        return view('livewire.vehicle-usage-history-list', [
            'vehicles' => $vehicles,
            'logs' => $logs,
            'summary' => $this->getSummaryMetrics(),
        ]);
    }
}
