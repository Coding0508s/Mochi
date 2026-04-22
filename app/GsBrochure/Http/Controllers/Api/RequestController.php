<?php

namespace App\GsBrochure\Http\Controllers\Api;

use App\GsBrochure\Models\BrochureRequest;
use App\GsBrochure\Models\Brochure;
use App\GsBrochure\Models\Institution;
use App\GsBrochure\Models\Invoice;
use App\GsBrochure\Models\RequestItem;
use App\GsBrochure\Models\StockHistory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class RequestController extends Controller
{
    public function index(): JsonResponse
    {
        $requests = BrochureRequest::with(['requestItems', 'invoices'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (BrochureRequest $req) => $this->serializeRequest($req));

        return response()->json($requests);
    }

    public function search(Request $request): JsonResponse
    {
        $schoolname = trim((string) $request->query('schoolname', ''));
        $phone = trim((string) $request->query('phone', ''));
        if ($schoolname === '' && $phone === '') {
            return response()->json(['error' => '기관명 또는 전화번호를 입력해 주세요.'], 400);
        }

        $schoolnameNorm = preg_replace('/\s+/u', '', $schoolname);
        $phoneNorm = preg_replace('/\s+/u', '', $phone);
        $table = (new BrochureRequest)->getTable();

        $query = BrochureRequest::with(['requestItems', 'invoices']);
        if ($schoolnameNorm !== '' && $phoneNorm !== '') {
            $query->where(function ($q) use ($table, $schoolnameNorm, $phoneNorm) {
                $q->whereRaw("REPLACE(REPLACE({$table}.schoolname, ' ', ''), CHAR(9), '') LIKE ?", ['%'.$schoolnameNorm.'%'])
                    ->orWhereRaw("REPLACE(REPLACE({$table}.phone, ' ', ''), CHAR(9), '') LIKE ?", ['%'.$phoneNorm.'%']);
            });
        } elseif ($schoolnameNorm !== '') {
            $query->whereRaw("REPLACE(REPLACE({$table}.schoolname, ' ', ''), CHAR(9), '') LIKE ?", ['%'.$schoolnameNorm.'%']);
        } else {
            $query->whereRaw("REPLACE(REPLACE({$table}.phone, ' ', ''), CHAR(9), '') LIKE ?", ['%'.$phoneNorm.'%']);
        }

        $requests = $query->orderByDesc('created_at')->get()->map(fn (BrochureRequest $req) => $this->serializeRequest($req));

        return response()->json($requests->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => 'required|string',
            'schoolname' => 'required|string',
            'address' => 'required|string',
            'phone' => 'required|string',
            'contact_id' => 'nullable|integer',
            'contact_name' => 'nullable|string',
            'brochures' => 'required|array',
            'brochures.*.brochure' => 'required|integer|min:1',
            'brochures.*.brochureName' => 'required|string',
            'brochures.*.quantity' => [
                'required',
                'integer',
                'min:10',
                function ($attribute, $value, $fail) {
                    if ((int) $value % 10 !== 0) {
                        $fail('수량은 10권 단위로 입력해 주세요.');
                    }
                },
            ],
            'invoices' => 'sometimes|array',
            'invoices.*' => 'string',
        ]);

        $schoolnameTrimmed = trim((string) $data['schoolname']);
        $requestAddress = trim((string) ($data['address'] ?? ''));

        $institution = Institution::firstOrCreate(
            ['name' => $schoolnameTrimmed],
            ['address' => $requestAddress, 'is_active' => true, 'sort_order' => 0]
        );

        if ((string) $institution->address === '' && $requestAddress !== '') {
            $institution->update(['address' => $requestAddress]);
        }

        $requestDate = trim((string) $data['date']) !== '' ? trim((string) $data['date']) : now()->format('Y-m-d');
        $inputContactName = trim((string) ($data['contact_name'] ?? ''));
        $requesterName = trim((string) ($request->user()?->name ?? ''));
        if ($requesterName === '') {
            $requesterName = trim((string) ($request->user()?->email ?? ''));
        }
        $contactName = $requesterName !== '' ? $requesterName : $inputContactName;
        $requestedBrochures = collect($data['brochures'])
            ->map(function (array $item): array {
                return [
                    'brochure_id' => (int) ($item['brochure'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 0),
                ];
            })
            ->values();
        $requestedByBrochure = $requestedBrochures
            ->groupBy('brochure_id')
            ->map(fn ($items) => (int) $items->sum('quantity'));
        $brochureIds = $requestedByBrochure->keys()->filter(fn ($id) => (int) $id > 0)->values();

        if ($brochureIds->isEmpty()) {
            throw ValidationException::withMessages([
                'brochures' => ['유효한 브로셔를 선택해 주세요.'],
            ]);
        }

        $invoices = $data['invoices'] ?? [];
        DB::beginTransaction();
        try {
            $brochureMap = Brochure::query()
                ->whereIn('id', $brochureIds->all())
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Brochure $brochure) => (string) $brochure->id);

            if ($brochureMap->count() !== $brochureIds->count()) {
                throw ValidationException::withMessages([
                    'brochures' => ['유효하지 않은 브로셔가 포함되어 있습니다.'],
                ]);
            }

            $insufficientStockMessages = [];
            foreach ($requestedByBrochure as $brochureId => $totalQuantity) {
                $brochure = $brochureMap->get((string) $brochureId);
                if (! $brochure) {
                    continue;
                }
                $available = (int) ($brochure->stock_warehouse ?? 0);
                $required = (int) $totalQuantity;
                if ($available < 100) {
                    $insufficientStockMessages[] = sprintf(
                        '%s: 현재 재고 %d권 (100권 이상일 때만 신청 가능)',
                        $brochure->name,
                        $available
                    );
                    continue;
                }
                if ($available < $required) {
                    $insufficientStockMessages[] = sprintf(
                        '%s: 요청 %d권, 보유 %d권',
                        $brochure->name,
                        $required,
                        $available
                    );
                }
            }

            if ($insufficientStockMessages !== []) {
                throw ValidationException::withMessages([
                    'brochures' => array_merge(['재고가 부족하여 신청을 진행할 수 없습니다.'], $insufficientStockMessages),
                ]);
            }

            $req = BrochureRequest::create([
                'date' => $data['date'],
                'schoolname' => $data['schoolname'],
                'address' => $data['address'],
                'phone' => $data['phone'],
                'contact_id' => $data['contact_id'] ?? null,
                'contact_name' => $contactName !== '' ? $contactName : null,
            ]);

            foreach ($requestedBrochures as $b) {
                $brochure = $brochureMap->get((string) $b['brochure_id']);
                if (! $brochure) {
                    continue;
                }
                RequestItem::create([
                    'request_id' => $req->id,
                    'brochure_id' => $brochure->id,
                    'brochure_name' => $brochure->name,
                    'quantity' => $b['quantity'],
                ]);
            }

            foreach ($requestedByBrochure as $brochureId => $totalQuantity) {
                $brochure = $brochureMap->get((string) $brochureId);
                if (! $brochure) {
                    continue;
                }

                $beforeStock = (int) ($brochure->stock_warehouse ?? 0);
                $delta = (int) $totalQuantity;
                $afterStock = $beforeStock - $delta;
                $brochure->update([
                    'stock_warehouse' => $afterStock,
                    'last_warehouse_stock_quantity' => -$delta,
                    'last_warehouse_stock_date' => $requestDate,
                ]);

                StockHistory::create([
                    'type' => '출고',
                    'location' => 'warehouse',
                    'date' => $requestDate,
                    'brochure_id' => $brochure->id,
                    'brochure_name' => $brochure->name,
                    'quantity' => $delta,
                    'contact_name' => $contactName,
                    'schoolname' => $schoolnameTrimmed,
                    'before_stock' => $beforeStock,
                    'after_stock' => $afterStock,
                    'memo' => null,
                ]);
            }

            $addedInvoiceNumbers = [];
            foreach ($invoices as $inv) {
                $num = trim((string) $inv);
                if ($num !== '') {
                    $addedInvoiceNumbers[] = $num;
                    Invoice::create(['request_id' => $req->id, 'invoice_number' => $num]);
                }
            }
            DB::commit();

            $this->notifyTeams($req, $addedInvoiceNumbers);

            return response()->json(['id' => $req->id]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'date' => 'required|string',
            'schoolname' => 'required|string',
            'address' => 'required|string',
            'phone' => 'required|string',
            'contact_id' => 'nullable|integer',
            'contact_name' => 'nullable|string',
            'brochures' => 'sometimes|array',
            'brochures.*.brochure' => 'required|integer|min:1',
            'brochures.*.brochureName' => 'required|string',
            'brochures.*.quantity' => [
                'required',
                'integer',
                'min:10',
                function ($attribute, $value, $fail) {
                    if ((int) $value % 10 !== 0) {
                        $fail('수량은 10권 단위로 입력해 주세요.');
                    }
                },
            ],
        ]);

        $req = BrochureRequest::findOrFail($id);
        DB::beginTransaction();
        try {
            $req->update([
                'date' => $data['date'],
                'schoolname' => $data['schoolname'],
                'address' => $data['address'],
                'phone' => $data['phone'],
                'contact_id' => $data['contact_id'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
            ]);

            RequestItem::where('request_id', $id)->delete();
            foreach ($data['brochures'] ?? [] as $b) {
                RequestItem::create([
                    'request_id' => $req->id,
                    'brochure_id' => $b['brochure'],
                    'brochure_name' => $b['brochureName'],
                    'quantity' => $b['quantity'],
                ]);
            }

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function destroy(string $id): JsonResponse
    {
        BrochureRequest::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function addInvoices(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['invoices' => 'required|array', 'invoices.*' => 'string']);
        $req = BrochureRequest::findOrFail($id);
        $addedNumbers = [];
        foreach ($data['invoices'] as $invoice) {
            $num = trim((string) $invoice);
            if ($num !== '') {
                $addedNumbers[] = $num;
                Invoice::create(['request_id' => $id, 'invoice_number' => $num]);
            }
        }

        $this->notifyInvoice($req, $addedNumbers);

        return response()->json(['success' => true]);
    }

    public function deleteInvoices(string $id): JsonResponse
    {
        Invoice::where('request_id', $id)->delete();

        return response()->json(['success' => true]);
    }

    private function serializeRequest(BrochureRequest $req): array
    {
        return [
            'id' => $req->id,
            'date' => $req->date,
            'schoolname' => $req->schoolname,
            'address' => $req->address,
            'phone' => $req->phone,
            'contact_id' => $req->contact_id,
            'contact_name' => $req->contact_name,
            'submitted_at' => $req->created_at?->toIso8601String(),
            'updated_at' => $req->updated_at?->toIso8601String(),
            'items' => $req->requestItems->map(fn ($ri) => [
                'brochure_id' => $ri->brochure_id,
                'brochure_name' => $ri->brochure_name,
                'quantity' => $ri->quantity,
            ])->values()->all(),
            'invoices' => $req->invoices->pluck('invoice_number')->all(),
        ];
    }

    private function notifyTeams(BrochureRequest $req, array $addedInvoiceNumbers): void
    {
        $webhookUrl = config('services.gs_brochure_teams.webhook_url');
        if (! is_string($webhookUrl) || $webhookUrl === '') {
            return;
        }

        try {
            $items = $req->requestItems()->get();
            $requesterName = trim((string) ($req->contact_name ?? '')) ?: '외부 신청자';
            $brochureFacts = $items->map(fn ($item) => [
                'name' => $item->brochure_name ?? '-',
                'value' => ($item->quantity ?? 0).'권',
            ])->values()->all();

            $logisticsUrl = config('services.gs_brochure_teams.logistics_url');
            if (! is_string($logisticsUrl) || $logisticsUrl === '') {
                $logisticsUrl = route('co.gs-brochure.admin.dashboard').'?section=logistics';
            }

            $payload = [
                '@type' => 'MessageCard',
                '@context' => 'http://schema.org/extensions',
                'themeColor' => '590091',
                'summary' => '브로셔 발송 요청',
                'sections' => [[
                    'markdown' => true,
                    'activityTitle' => '**브로셔 발송 요청**',
                    'facts' => [
                        ['name' => '신청자', 'value' => $requesterName],
                        ['name' => '기관명', 'value' => $req->schoolname ?? '-'],
                        ['name' => '연락처', 'value' => $req->phone ?? '-'],
                        ['name' => '주소', 'value' => $req->address ?? '-'],
                        ['name' => '신청일', 'value' => $req->date ?? '-'],
                    ],
                ], [
                    'activityTitle' => '**발송 브로셔 목록**',
                    'facts' => $brochureFacts,
                ]],
                'potentialAction' => [[
                    '@type' => 'OpenUri',
                    'name' => '운송장 입력',
                    'targets' => [[
                        'os' => 'default',
                        'uri' => $logisticsUrl,
                    ]],
                ]],
            ];

            Http::timeout(5)->post($webhookUrl, $payload);
            $this->notifyInvoice($req, $addedInvoiceNumbers);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function notifyInvoice(BrochureRequest $req, array $addedNumbers): void
    {
        $webhookUrl = config('services.gs_brochure_teams.webhook_url');
        if (! is_string($webhookUrl) || $webhookUrl === '' || $addedNumbers === []) {
            return;
        }

        try {
            $requesterName = trim((string) ($req->contact_name ?? '')) ?: '외부 신청자';
            $invoiceFacts = array_map(fn ($num) => ['name' => '운송장 번호', 'value' => $num], $addedNumbers);
            $payload = [
                '@type' => 'MessageCard',
                '@context' => 'http://schema.org/extensions',
                'themeColor' => '28a745',
                'summary' => '운송장 등록 완료 (물류창고)',
                'sections' => [[
                    'activityTitle' => '**운송장 등록 완료** (물류창고)',
                    'facts' => [
                        ['name' => '신청자', 'value' => $requesterName],
                        ['name' => '기관명', 'value' => $req->schoolname ?? '-'],
                        ['name' => '신청일', 'value' => $req->date ?? '-'],
                    ],
                ], [
                    'activityTitle' => '**등록된 운송장 번호**',
                    'facts' => $invoiceFacts,
                ]],
            ];

            Http::timeout(5)->post($webhookUrl, $payload);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
