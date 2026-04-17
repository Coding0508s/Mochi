<?php

namespace App\GsBrochure\Http\Controllers\Api;

use App\GsBrochure\Services\SolapiService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class VerificationController extends Controller
{
    private const CACHE_PREFIX = 'gsb_phone_verify:';

    private const VERIFIED_PREFIX = 'gsb_phone_verified:';

    private function normalizePhone(string $phone): string
    {
        return (string) preg_replace('/\D/', '', $phone);
    }

    public function sendCode(Request $request): JsonResponse
    {
        $phone = trim((string) $request->input('phone', ''));
        if ($phone === '') {
            return response()->json(['error' => '전화번호를 입력해 주세요.'], 400);
        }

        $normalized = $this->normalizePhone($phone);
        if (strlen($normalized) < 10) {
            return response()->json(['error' => '올바른 전화번호를 입력해 주세요.'], 400);
        }

        $code = (string) random_int(100000, 999999);
        Cache::put(self::CACHE_PREFIX.$normalized, $code, now()->addMinutes(5));

        $result = SolapiService::sendVerificationCode($normalized, $code);
        if (! $result['success']) {
            return response()->json(['error' => $result['error'] ?? '인증번호 발송에 실패했습니다.'], 502);
        }

        return response()->json(['success' => true, 'message' => '인증번호가 발송되었습니다.']);
    }

    public function verify(Request $request): JsonResponse
    {
        $phone = trim((string) $request->input('phone', ''));
        $code = trim((string) $request->input('code', ''));
        if ($phone === '' || $code === '') {
            return response()->json(['error' => '전화번호와 인증번호를 입력해 주세요.'], 400);
        }

        $normalized = $this->normalizePhone($phone);
        $cacheKey = self::CACHE_PREFIX.$normalized;
        $stored = Cache::get($cacheKey);

        if ($stored === null) {
            return response()->json(['error' => '인증번호가 만료되었습니다. 다시 발송해 주세요.'], 400);
        }
        if ($stored !== $code) {
            return response()->json(['error' => '인증번호가 일치하지 않습니다.'], 400);
        }

        Cache::forget($cacheKey);
        Cache::put(self::VERIFIED_PREFIX.$normalized, true, now()->addMinutes(10));

        return response()->json(['success' => true, 'message' => '인증이 완료되었습니다.']);
    }
}
