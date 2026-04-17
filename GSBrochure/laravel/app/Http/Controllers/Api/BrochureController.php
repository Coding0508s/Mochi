<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Brochure;
use App\Models\StockHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrochureController extends Controller
{
    public function health(Request $request): JsonResponse
    {
        try {
            DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $tableList = $driver === 'pgsql'
                ? array_column(DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename"), 'tablename')
                : array_map(fn ($t) => $t->name, DB::select("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"));
            return response()->json([
                'ok' => true,
                'database' => 'connected',
                'driver' => $driver,
                'tables' => array_values($tableList),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function index(): JsonResponse
    {
        $brochures = Brochure::orderBy('id')->get();
        return response()->json($brochures);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|unique:brochures,name',
            'image_url' => 'sometimes|nullable|string|max:2048',
            'stock' => 'sometimes|integer',
            'stock_warehouse' => 'sometimes|integer',
        ], [
            'name.unique' => '이미 같은 이름의 브로셔가 있습니다. 다른 이름을 입력해 주세요.',
        ]);
        $name = trim((string) $request->input('name'));
        $imageUrl = $request->input('image_url');
        $imageUrl = is_string($imageUrl) ? trim($imageUrl) : null;
        if ($imageUrl === '') {
            $imageUrl = null;
        }
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
            \Log::error('Brochure store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'error' => $e->getMessage() ?: '브로셔 저장 중 오류가 발생했습니다.',
            ], 500);
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
        $imageUrl = $request->input('image_url');
        $data['image_url'] = is_string($imageUrl) ? trim($imageUrl) : $brochure->image_url;
        if ($data['image_url'] === '') {
            $data['image_url'] = null;
        }
        $brochure->fill($data);
        $brochure->save();
        return response()->json(['success' => true]);
    }

    public function uploadImage(Request $request, string $id): JsonResponse
    {
        try {
            $file = $request->file('image');
            if (! $file || ! $file->isValid()) {
                $msg = '이미지 업로드에 실패했습니다. 파일 크기(최대 2MB)를 확인하고, 서버의 upload_max_filesize·post_max_size 설정을 확인해 주세요.';
                return response()->json(['error' => $msg, 'errors' => ['image' => [$msg]]], 422);
            }
            $request->validate([
                'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp|max:2048',
            ], [
                'image.required' => '이미지 파일을 선택해 주세요.',
                'image.file' => '이미지 업로드에 실패했습니다. 파일 크기(최대 2MB)를 확인해 주세요.',
                'image.uploaded' => '이미지 업로드에 실패했습니다. 파일 크기(최대 2MB)와 서버 업로드 제한을 확인해 주세요.',
                'image.mimes' => 'JPEG, PNG, GIF, WebP 형식만 가능합니다.',
                'image.max' => '이미지 크기는 2MB 이하여야 합니다.',
            ]);
            $brochure = Brochure::findOrFail($id);
            $ext = $file->getClientOriginalExtension() ?: $file->guessExtension();
            if (! in_array(strtolower((string) $ext), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                $ext = 'jpg';
            }
            $path = 'brochures/' . $id . '_' . Str::uuid() . '.' . $ext;

            $oldUrl = $brochure->image_url;
            if (is_string($oldUrl) && $oldUrl !== '') {
                $prefix = '/storage/brochures/';
                if (str_starts_with($oldUrl, $prefix) || str_contains($oldUrl, '/storage/brochures/')) {
                    $oldPath = 'brochures/' . basename(parse_url($oldUrl, PHP_URL_PATH));
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
            }

            Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

            $brochure->update(['image_url' => Storage::url($path)]);

            return response()->json(['image_url' => $brochure->fresh()->image_url]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Log::error('Brochure uploadImage error: ' . $e->getMessage(), ['id' => $id, 'exception' => $e]);
            return response()->json(['error' => $e->getMessage() ?: '이미지 업로드 중 오류가 발생했습니다.'], 500);
        }
    }

    public function deleteImage(string $id): JsonResponse
    {
        $brochure = Brochure::findOrFail($id);
        $oldUrl = $brochure->image_url;
        if (is_string($oldUrl) && $oldUrl !== '') {
            $prefix = '/storage/brochures/';
            if (str_starts_with($oldUrl, $prefix) || str_contains($oldUrl, '/storage/brochures/')) {
                $oldPath = 'brochures/' . basename(parse_url($oldUrl, PHP_URL_PATH));
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
        }
        $brochure->update(['image_url' => null]);
        return response()->json(['success' => true]);
    }

    public function destroy(string $id): JsonResponse
    {
        $brochure = Brochure::findOrFail($id);
        StockHistory::where('brochure_id', $id)->delete();
        try {
            $brochure->delete();
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'foreign key') || $e->getCode() === '23000') {
                return response()->json([
                    'error' => '이 브로셔는 발송 내역이 있어 삭제할 수 없습니다.',
                ], 422);
            }
            throw $e;
        }
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
        $date = $request->input('date', now()->format('Y-m-d'));
        $memo = $request->input('memo');
        $beforeStock = $brochure->stock;
        $newStock = $beforeStock + $quantity;
        $brochure->update([
            'stock' => $newStock,
            'last_stock_quantity' => $quantity,
            'last_stock_date' => $date,
        ]);
        if ($memo !== null && $memo !== '') {
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

    /**
     * 화성 물류창고 재고만 변경 (신청 시 출고 차감용). 본사 재고(stock)는 변경하지 않음.
     */
    public function updateWarehouseStock(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer',
            'date' => 'sometimes|string',
            'memo' => 'nullable|string|max:1000',
        ]);
        $brochure = Brochure::findOrFail($id);
        $quantity = (int) $request->input('quantity');
        $date = $request->input('date', now()->format('Y-m-d'));
        $memo = $request->input('memo');
        $beforeStock = $brochure->stock_warehouse ?? 0;
        $newStock = $beforeStock + $quantity;
        if ($newStock < 0) {
            return response()->json(['error' => '화성 물류창고 재고가 부족합니다.', 'stock_warehouse' => $beforeStock], 400);
        }
        $brochure->update([
            'stock_warehouse' => $newStock,
            'last_warehouse_stock_quantity' => $quantity,
            'last_warehouse_stock_date' => $date,
        ]);
        if ($memo !== null && $memo !== '') {
            StockHistory::create([
                'type' => '수정',
                'location' => 'warehouse',
                'date' => $date,
                'brochure_id' => $brochure->id,
                'brochure_name' => $brochure->name,
                'quantity' => $quantity,
                'before_stock' => $beforeStock,
                'after_stock' => $newStock,
                'memo' => '[화성물류] ' . $memo,
            ]);
        }
        return response()->json(['success' => true, 'stock_warehouse' => $newStock]);
    }

    /**
     * 물류창고 재고에서 본사 재고로 이동 (물류 -N, 본사 +N).
     */
    public function transferToHq(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'date' => 'sometimes|string',
            'memo' => 'nullable|string|max:1000',
        ]);
        $brochure = Brochure::findOrFail($id);
        $quantity = (int) $request->input('quantity');
        $date = $request->input('date', now()->format('Y-m-d'));
        $memo = $request->input('memo');
        $warehouseBefore = $brochure->stock_warehouse ?? 0;
        $hqBefore = $brochure->stock ?? 0;
        if ($warehouseBefore < $quantity) {
            return response()->json([
                'error' => '물류창고 재고가 부족합니다.',
                'stock_warehouse' => $warehouseBefore,
            ], 400);
        }
        $brochure->update([
            'stock_warehouse' => $warehouseBefore - $quantity,
            'stock' => $hqBefore + $quantity,
            'last_stock_quantity' => $quantity,
            'last_stock_date' => $date,
        ]);
        $memoText = '물류창고→본사 이동' . ($memo ? ' - ' . $memo : '');
        // 물류센터 입출고 내역용 (물류 재고 감소)
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
        // 본사 입출고 내역용 (본사 재고 증가)
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
