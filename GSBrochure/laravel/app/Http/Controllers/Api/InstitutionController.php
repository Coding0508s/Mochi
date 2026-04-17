<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class InstitutionController extends Controller
{
    /** Public list for brochure request form (active institutions only, optional search). */
    public function listPublic(Request $request): JsonResponse
    {
        $search = $request->get('search');
        $query = Institution::where('is_active', true)->orderBy('sort_order')->orderBy('name')->limit(50);
        if (is_string($search) && trim($search) !== '') {
            $query->where('name', 'like', '%' . trim($search) . '%');
        }
        $list = $query->get(['id', 'name', 'address'])->map(fn ($i) => [
            'id' => $i->id,
            'name' => $i->name,
            'address' => $i->address ? (string) $i->address : null,
        ]);
        return response()->json($list->values()->all());
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 20);
        $perPage = $perPage >= 1 && $perPage <= 100 ? $perPage : 20;
        $query = Institution::query();
        $search = $request->get('search');
        if (is_string($search) && trim($search) !== '') {
            $query->where('name', 'like', '%' . trim($search) . '%');
        }
        $isActive = $request->get('is_active');
        if ($isActive !== null && $isActive !== '') {
            $query->where('is_active', (int) $isActive);
        }
        $paginator = $query->orderBy('sort_order')->orderBy('name')->paginate($perPage);
        return response()->json($paginator);
    }

    public function show(string $id): JsonResponse
    {
        $institution = Institution::findOrFail($id);
        return response()->json($institution);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'address' => 'nullable|string|max:512',
                'is_active' => 'nullable|boolean',
                'sort_order' => 'nullable|integer',
            ]);
            $data['is_active'] = $data['is_active'] ?? true;
            $data['sort_order'] = $data['sort_order'] ?? 0;
            $institution = Institution::create($data);
            return response()->json(['id' => $institution->id, 'name' => $institution->name, 'message' => '기관이 추가되었습니다.']);
        } catch (\Throwable $e) {
            Log::error('Institution store exception', ['message' => $e->getMessage(), 'exception' => $e]);
            return response()->json(['error' => $e->getMessage() ?: '기관 추가 중 오류가 발생했습니다.'], 500);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $institution = Institution::findOrFail($id);
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'address' => 'nullable|string|max:512',
                'is_active' => 'nullable|boolean',
                'sort_order' => 'nullable|integer',
            ]);
            $institution->update($data);
            return response()->json(['success' => true, 'message' => '기관이 수정되었습니다.']);
        } catch (\Throwable $e) {
            Log::error('Institution update exception', ['id' => $id, 'message' => $e->getMessage(), 'exception' => $e]);
            return response()->json(['error' => $e->getMessage() ?: '기관 수정 중 오류가 발생했습니다.'], 500);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            Institution::findOrFail($id)->delete();
            return response()->json(['success' => true, 'message' => '기관이 삭제되었습니다.']);
        } catch (\Throwable $e) {
            Log::error('Institution destroy exception', ['id' => $id, 'message' => $e->getMessage(), 'exception' => $e]);
            return response()->json(['error' => $e->getMessage() ?: '기관 삭제 중 오류가 발생했습니다.'], 500);
        }
    }

    public function bulkUpdateIsActive(Request $request): JsonResponse
    {
        try {
            $ids = $request->input('ids', []);
            $isActive = $request->boolean('is_active');
            if (! is_array($ids) || empty($ids)) {
                return response()->json(['error' => '선택된 기관이 없습니다.'], 400);
            }
            $ids = array_map('intval', array_filter($ids));
            Institution::whereIn('id', $ids)->update(['is_active' => $isActive]);
            $count = count($ids);
            return response()->json([
                'success' => true,
                'message' => $isActive ? $count . '개 기관이 활성화되었습니다.' : $count . '개 기관이 비활성화되었습니다.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Institution bulkUpdateIsActive exception', ['message' => $e->getMessage(), 'exception' => $e]);
            return response()->json(['error' => $e->getMessage() ?: '일괄 변경 중 오류가 발생했습니다.'], 500);
        }
    }
}
