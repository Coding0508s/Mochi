<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockHistoryController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $history = StockHistory::orderByDesc('created_at')->get();
            return response()->json($history);
        } catch (\Throwable $e) {
            \Log::error('Stock history index error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => '입출고 내역을 불러올 수 없습니다.', 'detail' => $e->getMessage()], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string',
            'location' => 'nullable|string|in:warehouse,hq',
            'date' => 'required|string',
            'brochure_id' => 'required|integer',
            'brochure_name' => 'required|string',
            'quantity' => 'required|integer',
            'contact_name' => 'nullable|string',
            'schoolname' => 'nullable|string',
            'before_stock' => 'required|integer',
            'after_stock' => 'required|integer',
            'memo' => 'nullable|string|max:1000',
        ]);
        StockHistory::create($data);
        return response()->json(['success' => true]);
    }
}
