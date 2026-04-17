<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BrochureRequest;
use App\Models\Institution;
use App\Models\Invoice;
use App\Models\RequestItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class RequestController extends Controller
{
    public function index(): JsonResponse
    {
        $requests = BrochureRequest::with(['requestItems', 'invoices'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (BrochureRequest $req) {
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
            });
        return response()->json($requests);
    }

    public function search(Request $request): JsonResponse
    {
        $schoolname = $request->query('schoolname');
        $phone = $request->query('phone');
        $schoolname = is_string($schoolname) ? trim($schoolname) : '';
        $phone = is_string($phone) ? trim($phone) : '';
        if ($schoolname === '' && $phone === '') {
            return response()->json(['error' => '기관명 또는 전화번호를 입력해 주세요.'], 400);
        }
        // 띄어쓰기 무시: 검색어와 DB 값 모두 공백 제거 후 비교
        $schoolnameNorm = preg_replace('/\s+/u', '', $schoolname);
        $phoneNorm = preg_replace('/\s+/u', '', $phone);
        $query = BrochureRequest::with(['requestItems', 'invoices']);
        if ($schoolnameNorm !== '' && $phoneNorm !== '') {
            $query->where(function ($q) use ($schoolnameNorm, $phoneNorm) {
                $q->whereRaw('REPLACE(REPLACE(schoolname, " ", ""), CHAR(9), "") LIKE ?', ['%' . $schoolnameNorm . '%'])
                    ->orWhereRaw('REPLACE(REPLACE(phone, " ", ""), CHAR(9), "") LIKE ?', ['%' . $phoneNorm . '%']);
            });
        } elseif ($schoolnameNorm !== '') {
            $query->whereRaw('REPLACE(REPLACE(schoolname, " ", ""), CHAR(9), "") LIKE ?', ['%' . $schoolnameNorm . '%']);
        } else {
            $query->whereRaw('REPLACE(REPLACE(phone, " ", ""), CHAR(9), "") LIKE ?', ['%' . $phoneNorm . '%']);
        }
        $requests = $query->orderByDesc('created_at')
            ->get()
            ->map(function (BrochureRequest $req) {
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
            });
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
            'brochures.*.brochure' => 'required',
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
        $invoices = $data['invoices'] ?? [];

        $schoolnameTrimmed = trim($data['schoolname']);
        $requestAddress = isset($data['address']) ? trim($data['address']) : null;
        $institution = Institution::firstOrCreate(
            ['name' => $schoolnameTrimmed],
            [
                'address' => $requestAddress,
                'is_active' => true,
                'sort_order' => 0,
            ]
        );
        if (($institution->address === null || trim((string) $institution->address) === '') && $requestAddress !== null && $requestAddress !== '') {
            $institution->update(['address' => $requestAddress]);
        }

        DB::beginTransaction();
        try {
            $req = BrochureRequest::create([
                'date' => $data['date'],
                'schoolname' => $data['schoolname'],
                'address' => $data['address'],
                'phone' => $data['phone'],
                'contact_id' => $data['contact_id'] ?? null,
                'contact_name' => $data['contact_name'] ?? null,
            ]);
            foreach ($data['brochures'] as $b) {
                RequestItem::create([
                    'request_id' => $req->id,
                    'brochure_id' => $b['brochure'],
                    'brochure_name' => $b['brochureName'],
                    'quantity' => $b['quantity'],
                ]);
            }
            $addedInvoiceNumbers = [];
            foreach ($invoices as $inv) {
                if (trim($inv) !== '') {
                    $num = trim($inv);
                    $addedInvoiceNumbers[] = $num;
                    Invoice::create(['request_id' => $req->id, 'invoice_number' => $num]);
                }
            }
            DB::commit();

            $webhookUrl = config('services.teams.webhook_url');
            if ($webhookUrl && $webhookUrl !== '') {
                try {
                    $items = $req->requestItems()->get();
                    $brochureFacts = [];
                    foreach ($items as $item) {
                        $brochureFacts[] = [
                            'name' => $item->brochure_name ?? '-',
                            'value' => ($item->quantity ?? 0) . '권',
                        ];
                    }
                    $logisticsUrl = url('requestbrochure-logistics');
                    $payload = [
                        '@type' => 'MessageCard',
                        '@context' => 'http://schema.org/extensions',
                        'themeColor' => '590091',
                        'summary' => '브로셔 발송 요청',
                        'sections' => [
                            [
                                'markdown' => true,
                                'activityTitle' => '**브로셔 발송 요청**',
                                'facts' => [
                                    ['name' => '담당자', 'value' => $req->contact_name ?? '-'],
                                    ['name' => '기관명', 'value' => $req->schoolname ?? '-'],
                                    ['name' => '연락처', 'value' => $req->phone ?? '-'],
                                    ['name' => '주소', 'value' => $req->address ?? '-'],
                                    ['name' => '신청일', 'value' => $req->date ?? '-'],
                                    /* ['name' => '링크', 'value' => '[운송장 입력 페이지](' . $logisticsUrl . ')'], */
                                ],
                            ],
                            [
                                'activityTitle' => '**발송 브로셔 목록**',
                                'facts' => $brochureFacts,
                            ],
                        ],
                        'potentialAction' => [
                            [
                                '@type' => 'OpenUri',
                                'name' => '운송장 입력',
                                'targets' => [
                                    ['os' => 'default', 'uri' => $logisticsUrl],
                                ],
                            ],
                        ],
                    ];
                    Http::timeout(5)->post($webhookUrl, $payload);

                    // 신청 시 운송장 번호가 함께 입력된 경우 운송장 등록 완료 알림도 전송
                    if (count($addedInvoiceNumbers) > 0) {
                        $invoiceFacts = [];
                        foreach ($addedInvoiceNumbers as $num) {
                            $invoiceFacts[] = ['name' => '운송장 번호', 'value' => $num];
                        }
                        $invoicePayload = [
                            '@type' => 'MessageCard',
                            '@context' => 'http://schema.org/extensions',
                            'themeColor' => '28a745',
                            'summary' => '운송장 등록 완료 (물류창고)',
                            'sections' => [
                                [
                                    'activityTitle' => '**운송장 등록 완료** (물류창고)',
                                    'facts' => [
                                        ['name' => '담당자', 'value' => $req->contact_name ?? '-'],
                                        ['name' => '기관명', 'value' => $req->schoolname ?? '-'],
                                        ['name' => '신청일', 'value' => $req->date ?? '-'],
                                    ],
                                ],
                                [
                                    'activityTitle' => '**등록된 운송장 번호**',
                                    'facts' => $invoiceFacts,
                                ],
                            ],
                        ];
                        Http::timeout(5)->post($webhookUrl, $invoicePayload);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }

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
            'brochures.*.brochure' => 'required',
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
        foreach ($data['invoices'] as $inv) {
            if (trim($inv) !== '') {
                $num = trim($inv);
                $addedNumbers[] = $num;
                Invoice::create(['request_id' => $id, 'invoice_number' => $num]);
            }
        }

        $webhookUrl = config('services.teams.webhook_url');
        if ($webhookUrl && $webhookUrl !== '' && count($addedNumbers) > 0) {
            try {
                $invoiceFacts = [];
                foreach ($addedNumbers as $num) {
                    $invoiceFacts[] = ['name' => '운송장 번호', 'value' => $num];
                }
                $payload = [
                    '@type' => 'MessageCard',
                    '@context' => 'http://schema.org/extensions',
                    'themeColor' => '28a745',
                    'summary' => '운송장 등록 완료 (물류창고)',
                    'sections' => [
                        [
                            'activityTitle' => '**운송장 등록 완료** (물류창고)',
                            'facts' => [
                                ['name' => '담당자', 'value' => $req->contact_name ?? '-'],
                                ['name' => '기관명', 'value' => $req->schoolname ?? '-'],
                                ['name' => '신청일', 'value' => $req->date ?? '-'],
                            ],
                        ],
                        [
                            'activityTitle' => '**등록된 운송장 번호**',
                            'facts' => $invoiceFacts,
                        ],
                    ],
                ];
                Http::timeout(5)->post($webhookUrl, $payload);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->json(['success' => true]);
    }

    public function deleteInvoices(string $id): JsonResponse
    {
        Invoice::where('request_id', $id)->delete();
        return response()->json(['success' => true]);
    }
}
