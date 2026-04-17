<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        try {
            $request->validate(['username' => 'required|string', 'password' => 'required|string']);
            $username = $request->input('username');
            $user = AdminUser::where('username', $username)->first();
            if (!$user || !Hash::check($request->input('password'), $user->password_hash)) {
                Log::warning('Admin login failed', ['username' => $username]);
                return response()->json(['error' => '인증 실패'], 401);
            }
            return response()->json(['success' => true, 'username' => $user->username]);
        } catch (\Throwable $e) {
            Log::error('Admin login exception', ['message' => $e->getMessage(), 'exception' => $e]);
            return response()->json(['error' => '로그인 처리 중 오류가 발생했습니다.'], 500);
        }
    }

    public function users(): JsonResponse
    {
        try {
            $users = AdminUser::orderBy('id')->get(['id', 'username', 'created_at', 'updated_at']);
            return response()->json($users);
        } catch (\Throwable $e) {
            Log::error('Admin users list exception', ['message' => $e->getMessage(), 'exception' => $e]);
            return response()->json(['error' => '관리자 목록 조회 중 오류가 발생했습니다.'], 500);
        }
    }

    public function createUser(Request $request): JsonResponse
    {
        try {
            $request->validate(['username' => 'required|string', 'password' => 'required|string']);
            $username = $request->input('username');
            if (AdminUser::where('username', $username)->exists()) {
                Log::warning('Admin createUser duplicate', ['username' => $username]);
                return response()->json(['error' => '이미 존재하는 사용자명입니다.'], 400);
            }
            $hash = Hash::make($request->input('password'));
            $user = AdminUser::create([
                'username' => $username,
                'password_hash' => $hash,
            ]);
            return response()->json([
                'success' => true,
                'id' => $user->id,
                'username' => $user->username,
                'message' => '계정이 생성되었습니다.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Admin createUser exception', ['message' => $e->getMessage(), 'exception' => $e]);
            return response()->json(['error' => '계정 생성 중 오류가 발생했습니다.'], 500);
        }
    }

    public function changePassword(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'password' => 'required|string',
                'newPassword' => 'required|string',
            ]);
            $user = AdminUser::findOrFail($id);
            if (!Hash::check($request->input('password'), $user->password_hash)) {
                Log::warning('Admin changePassword wrong current', ['user_id' => $id]);
                return response()->json(['error' => '현재 비밀번호가 올바르지 않습니다.'], 401);
            }
            $user->update(['password_hash' => Hash::make($request->input('newPassword'))]);
            return response()->json(['success' => true, 'message' => '비밀번호가 변경되었습니다.']);
        } catch (\Throwable $e) {
            Log::error('Admin changePassword exception', ['id' => $id, 'message' => $e->getMessage(), 'exception' => $e]);
            return response()->json(['error' => '비밀번호 변경 중 오류가 발생했습니다.'], 500);
        }
    }

    public function deleteUser(string $id): JsonResponse
    {
        try {
            $user = AdminUser::findOrFail($id);
            if (AdminUser::count() <= 1) {
                Log::warning('Admin deleteUser last account', ['user_id' => $id]);
                return response()->json(['error' => '최소 하나의 관리자 계정이 필요합니다.'], 400);
            }
            $user->delete();
            return response()->json(['success' => true, 'message' => '계정이 삭제되었습니다.']);
        } catch (\Throwable $e) {
            Log::error('Admin deleteUser exception', ['id' => $id, 'message' => $e->getMessage(), 'exception' => $e]);
            return response()->json(['error' => '계정 삭제 중 오류가 발생했습니다.'], 500);
        }
    }
}
