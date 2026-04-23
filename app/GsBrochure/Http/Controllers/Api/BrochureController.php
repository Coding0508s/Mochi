<?php

namespace App\GsBrochure\Http\Controllers\Api;

use App\GsBrochure\Models\Brochure;
use App\GsBrochure\Models\StockHistory;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BrochureController extends Controller
{
    public function health(): JsonResponse
    {
        try {
            DB::connection(config('gs_brochure.connection'))->getPdo();
            $driver = DB::connection(config('gs_brochure.connection'))->getDriverName();

            return response()->json([
                'ok' => true,
                'database' => 'connected',
                'driver' => $driver,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function index(): JsonResponse
    {
        return response()->json(Brochure::orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $conn = (string) config('gs_brochure.connection');
        $table = (new Brochure)->getTable();
        $request->validate([
            'name' => ['required', 'string', Rule::unique("{$conn}.{$table}", 'name')],
            'image_url' => 'sometimes|nullable|string|max:2048',
            'stock' => 'sometimes|integer',
            'stock_warehouse' => 'sometimes|integer',
        ], [
            'name.unique' => '이미 같은 이름의 브로셔가 있습니다. 다른 이름을 입력해 주세요.',
        ]);

        $name = trim((string) $request->input('name'));
        $imageUrl = trim((string) $request->input('image_url', '')) ?: null;
        $stock = (int) $request->input('stock', 0);
        $stockWarehouse = (int) $request->input('stock_warehouse', 0);

        try {
            $brochure = Brochure::create([
                'name' => $name,
                'image_url' => $imageUrl,
                'stock' => $stock,
                'stock_warehouse' => $stockWarehouse,
            ]);

            $date = now()->format('Y-m-d');
            $base = [
                'brochure_id' => $brochure->id,
                'brochure_name' => $name,
                'date' => $date,
                'contact_name' => '',
                'schoolname' => '',
                'memo' => null,
            ];

            StockHistory::create(array_merge($base, [
                'type' => '등록',
                'location' => 'warehouse',
                'quantity' => $stockWarehouse,
                'before_stock' => 0,
                'after_stock' => $stockWarehouse,
            ]));

            StockHistory::create(array_merge($base, [
                'type' => '등록',
                'location' => 'hq',
                'quantity' => $stock,
                'before_stock' => 0,
                'after_stock' => $stock,
            ]));

            return response()->json(['id' => $brochure->id, 'name' => $name, 'stock' => $brochure->stock]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => $e->getMessage() ?: '브로셔 저장 중 오류가 발생했습니다.'], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $brochure = Brochure::findOrFail($id);
        $request->validate([
            'name' => 'sometimes|string',
            'image_url' => 'sometimes|nullable|string|max:2048',
            'stock' => 'sometimes|integer',
            'stock_warehouse' => 'sometimes|integer',
        ]);

        $data = $request->only(['name', 'stock', 'stock_warehouse']);
        if ($request->has('image_url')) {
            $data['image_url'] = trim((string) $request->input('image_url')) ?: null;
        }

        $brochure->fill($data)->save();

        return response()->json(['success' => true]);
    }

    public function uploadImage(Request $request, string $id): JsonResponse
    {
        // max 단위는 KB. 브로셔 표지용으로 40MB까지 허용합니다.
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,bmp,avif', 'max:40960'],
        ], [
            'image.required' => '이미지 파일을 선택해 주세요.',
            'image.mimes' => 'jpg, jpeg, png, gif, webp, bmp, avif 형식의 이미지만 업로드할 수 있습니다. (heic/heif는 지원하지 않습니다.)',
            'image.max' => '이미지 용량은 40MB 이하로 올려 주세요.',
            'image.uploaded' => '이미지 업로드에 실패했습니다. 서버 업로드 제한(php.ini upload_max_filesize/post_max_size)보다 큰 파일일 수 있습니다.',
        ]);

        $brochure = Brochure::findOrFail($id);
        $file = $request->file('image');
        if (! $file || ! $file->isValid()) {
            return response()->json(['error' => '이미지 업로드에 실패했습니다.'], 422);
        }

        $ext = $file->getClientOriginalExtension() ?: $file->guessExtension();
        if (! in_array(strtolower((string) $ext), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'bmp'], true)) {
            $ext = 'jpg';
        }

        $path = 'brochures/'.$id.'_'.Str::uuid().'.'.$ext;
        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));
        $brochure->update(['image_url' => Storage::url($path)]);

        return response()->json(['image_url' => $brochure->fresh()->image_url]);
    }

    public function deleteImage(string $id): JsonResponse
    {
        $brochure = Brochure::findOrFail($id);
        $brochure->update(['image_url' => null]);

        return response()->json(['success' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        StockHistory::where('brochure_id', $id)->delete();
        Brochure::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function updateStock(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer',
            'date' => 'sometimes|string',
            'memo' => 'nullable|string|max:1000',
        ]);

        $brochure = Brochure::findOrFail($id);
        $quantity = (int) $request->input('quantity');
        $date = (string) $request->input('date', now()->format('Y-m-d'));
        $beforeStock = (int) $brochure->stock;
        $newStock = $beforeStock + $quantity;

        $brochure->update([
            'stock' => $newStock,
            'last_stock_quantity' => $quantity,
            'last_stock_date' => $date,
        ]);

        $memo = $request->input('memo');
        if (is_string($memo) && $memo !== '') {
            StockHistory::create([
                'type' => '수정',
                'location' => 'hq',
                'date' => $date,
                'brochure_id' => $brochure->id,
                'brochure_name' => $brochure->name,
                'quantity' => $quantity,
                'before_stock' => $beforeStock,
                'after_stock' => $newStock,
                'memo' => $memo,
            ]);
        }

        return response()->json(['success' => true, 'stock' => $newStock]);
    }

    public function updateWarehouseStock(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer',
            'date' => 'sometimes|string',
            'memo' => 'nullable|string|max:1000',
        ]);

        $brochure = Brochure::findOrFail($id);
        $quantity = (int) $request->input('quantity');
        $date = (string) $request->input('date', now()->format('Y-m-d'));
        $beforeStock = (int) ($brochure->stock_warehouse ?? 0);
        $newStock = $beforeStock + $quantity;

        if ($newStock < 0) {
            return response()->json(['error' => '화성 물류창고 재고가 부족합니다.', 'stock_warehouse' => $beforeStock], 400);
        }

        $brochure->update([
            'stock_warehouse' => $newStock,
            'last_warehouse_stock_quantity' => $quantity,
            'last_warehouse_stock_date' => $date,
        ]);

        $memo = $request->input('memo');
        if (is_string($memo) && $memo !== '') {
            StockHistory::create([
                'type' => '수정',
                'location' => 'warehouse',
                'date' => $date,
                'brochure_id' => $brochure->id,
                'brochure_name' => $brochure->name,
                'quantity' => $quantity,
                'before_stock' => $beforeStock,
                'after_stock' => $newStock,
                'memo' => '[화성물류] '.$memo,
            ]);
        }

        return response()->json(['success' => true, 'stock_warehouse' => $newStock]);
    }

    public function transferToHq(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'date' => 'sometimes|string',
            'memo' => 'nullable|string|max:1000',
        ]);

        $brochure = Brochure::findOrFail($id);
        $quantity = (int) $request->input('quantity');
        $date = (string) $request->input('date', now()->format('Y-m-d'));
        $warehouseBefore = (int) ($brochure->stock_warehouse ?? 0);
        $hqBefore = (int) ($brochure->stock ?? 0);

        if ($warehouseBefore < $quantity) {
            return response()->json(['error' => '물류창고 재고가 부족합니다.', 'stock_warehouse' => $warehouseBefore], 400);
        }

        $brochure->update([
            'stock_warehouse' => $warehouseBefore - $quantity,
            'stock' => $hqBefore + $quantity,
            'last_stock_quantity' => $quantity,
            'last_stock_date' => $date,
        ]);

        $memo = trim((string) $request->input('memo', ''));
        $memoText = '물류창고→본사 이동'.($memo !== '' ? ' - '.$memo : '');

        StockHistory::create([
            'type' => '이동',
            'location' => 'warehouse',
            'date' => $date,
            'brochure_id' => $brochure->id,
            'brochure_name' => $brochure->name,
            'quantity' => $quantity,
            'contact_name' => '',
            'schoolname' => '',
            'before_stock' => $warehouseBefore,
            'after_stock' => $warehouseBefore - $quantity,
            'memo' => $memoText,
        ]);

        StockHistory::create([
            'type' => '이동',
            'location' => 'hq',
            'date' => $date,
            'brochure_id' => $brochure->id,
            'brochure_name' => $brochure->name,
            'quantity' => $quantity,
            'contact_name' => '',
            'schoolname' => '',
            'before_stock' => $hqBefore,
            'after_stock' => $hqBefore + $quantity,
            'memo' => $memoText,
        ]);

        return response()->json([
            'success' => true,
            'stock_warehouse' => $warehouseBefore - $quantity,
            'stock' => $hqBefore + $quantity,
        ]);
    }
}
