<?php

namespace App\GsBrochure\Http\Controllers\Api;

use App\GsBrochure\Models\AdminUser;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate(['username' => 'required|string', 'password' => 'required|string']);

        $user = AdminUser::where('username', $request->input('username'))->first();
        if (! $user || ! Hash::check((string) $request->input('password'), (string) $user->password_hash)) {
            return response()->json(['error' => '인증 실패'], 401);
        }

        return response()->json(['success' => true, 'username' => $user->username]);
    }

    public function users(): JsonResponse
    {
        return response()->json(AdminUser::orderBy('id')->get(['id', 'username', 'created_at', 'updated_at']));
    }

    public function createUser(Request $request): JsonResponse
    {
        $request->validate(['username' => 'required|string', 'password' => 'required|string']);
        $username = (string) $request->input('username');

        if (AdminUser::where('username', $username)->exists()) {
            return response()->json(['error' => '이미 존재하는 사용자명입니다.'], 400);
        }

        $user = AdminUser::create([
            'username' => $username,
            'password_hash' => Hash::make((string) $request->input('password')),
        ]);

        return response()->json([
            'success' => true,
            'id' => $user->id,
            'username' => $user->username,
            'message' => '계정이 생성되었습니다.',
        ]);
    }

    public function changePassword(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'password' => 'required|string',
            'newPassword' => 'required|string',
        ]);

        $user = AdminUser::findOrFail($id);
        if (! Hash::check((string) $request->input('password'), (string) $user->password_hash)) {
            return response()->json(['error' => '현재 비밀번호가 올바르지 않습니다.'], 401);
        }

        $user->update(['password_hash' => Hash::make((string) $request->input('newPassword'))]);

        return response()->json(['success' => true, 'message' => '비밀번호가 변경되었습니다.']);
    }

    public function deleteUser(string $id): JsonResponse
    {
        if (AdminUser::count() <= 1) {
            return response()->json(['error' => '최소 하나의 관리자 계정이 필요합니다.'], 400);
        }

        AdminUser::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => '계정이 삭제되었습니다.']);
    }
}
