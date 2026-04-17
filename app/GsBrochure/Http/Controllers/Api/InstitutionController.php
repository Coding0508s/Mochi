<?php

namespace App\GsBrochure\Http\Controllers\Api;

use App\GsBrochure\Models\Institution;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstitutionController extends Controller
{
    public function listPublic(Request $request): JsonResponse
    {
        $search = trim((string) $request->get('search', ''));
        $query = Institution::where('is_active', true)->orderBy('sort_order')->orderBy('name')->limit(50);
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $list = $query->get(['id', 'name', 'address'])->map(fn ($row) => [
            'id' => $row->id,
            'name' => $row->name,
            'address' => $row->address ? (string) $row->address : null,
        ]);

        return response()->json($list->values()->all());
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min((int) $request->get('per_page', 20), 100));
        $query = Institution::query();
        $search = trim((string) $request->get('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }

        $isActive = $request->get('is_active');
        if ($isActive !== null && $isActive !== '') {
            $query->where('is_active', (int) $isActive);
        }

        return response()->json($query->orderBy('sort_order')->orderBy('name')->paginate($perPage));
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(Institution::findOrFail($id));
    }

    public function store(Request $request): JsonResponse
    {
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
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $institution = Institution::findOrFail($id);
        $institution->update($request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'address' => 'nullable|string|max:512',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]));

        return response()->json(['success' => true, 'message' => '기관이 수정되었습니다.']);
    }

    public function destroy(string $id): JsonResponse
    {
        Institution::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => '기관이 삭제되었습니다.']);
    }

    public function bulkUpdateIsActive(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (! is_array($ids) || $ids === []) {
            return response()->json(['error' => '선택된 기관이 없습니다.'], 400);
        }

        $isActive = $request->boolean('is_active');
        $ids = array_map('intval', array_filter($ids));
        Institution::whereIn('id', $ids)->update(['is_active' => $isActive]);

        return response()->json([
            'success' => true,
            'message' => $isActive ? count($ids).'개 기관이 활성화되었습니다.' : count($ids).'개 기관이 비활성화되었습니다.',
        ]);
    }
}
