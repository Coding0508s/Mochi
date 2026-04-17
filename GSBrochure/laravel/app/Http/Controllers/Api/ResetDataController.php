<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brochure;
use App\Models\BrochureRequest;
use App\Models\Invoice;
use App\Models\RequestItem;
use App\Models\StockHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResetDataController extends Controller
{
    /**
     * Reset operational data by type.
     * Types: full | stock_history | requests | brochure_stock
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate(['type' => 'required|string|in:full,stock_history,requests,brochure_stock']);

        $type = $request->input('type');

        try {
            DB::beginTransaction();

            if ($type === 'full' || $type === 'stock_history') {
                StockHistory::query()->delete();
            }

            if ($type === 'full' || $type === 'requests') {
                Invoice::query()->delete();
                RequestItem::query()->delete();
                BrochureRequest::query()->delete();
            }

            if ($type === 'full' || $type === 'brochure_stock') {
                Brochure::query()->update([
                    'stock' => 0,
                    'stock_warehouse' => 0,
                    'last_stock_quantity' => 0,
                    'last_stock_date' => null,
                    'last_warehouse_stock_quantity' => 0,
                    'last_warehouse_stock_date' => null,
                ]);
            }

            DB::commit();

            $messages = [
                'full' => '재고 수량, 입출고 내역, 신청 내역, 운송장 정보가 모두 초기화되었습니다.',
                'stock_history' => '입출고 내역이 삭제되었습니다.',
                'requests' => '신청 내역과 운송장 정보가 삭제되었습니다.',
                'brochure_stock' => '모든 브로셔 재고 수량이 0으로 초기화되었습니다.',
            ];

            return response()->json(['success' => true, 'message' => $messages[$type]]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Log::error('Reset data error: ' . $e->getMessage(), ['type' => $type, 'exception' => $e]);

            return response()->json([
                'error' => '초기화 중 오류가 발생했습니다.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }
}
