<?php

namespace App\GsBrochure\Http\Controllers\Api;

use App\GsBrochure\Models\StockHistory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockHistoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(StockHistory::orderByDesc('created_at')->get());
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
