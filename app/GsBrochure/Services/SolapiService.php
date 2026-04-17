<?php

namespace App\GsBrochure\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Nurigo\Solapi\Exceptions\MessageNotReceivedException;
use Nurigo\Solapi\Models\Kakao\KakaoOption;
use Nurigo\Solapi\Models\Message;
use Nurigo\Solapi\Services\SolapiMessageService;

class SolapiService
{
    public static function createAuthHeader(string $apiKey, string $apiSecret): string
    {
        $dateTime = gmdate('Y-m-d\TH:i:s\Z');
        $salt = bin2hex(random_bytes(16));
        $signature = hash_hmac('sha256', $dateTime.$salt, $apiSecret);

        return "HMAC-SHA256 apiKey={$apiKey}, date={$dateTime}, salt={$salt}, signature={$signature}";
    }

    public static function sendSms(string $to, string $text): array
    {
        $apiKey = config('services.gs_brochure_solapi.key');
        $apiSecret = config('services.gs_brochure_solapi.secret');
        $from = config('services.gs_brochure_solapi.from');

        if (empty($apiKey) || empty($apiSecret) || empty($from)) {
            Log::warning('GS brochure Solapi config is missing.');

            return ['success' => false, 'error' => '문자 발송 설정이 되어 있지 않습니다.'];
        }

        $to = preg_replace('/\D/', '', $to);
        if (strlen((string) $to) < 10) {
            return ['success' => false, 'error' => '올바른 전화번호를 입력해 주세요.'];
        }

        $response = Http::withHeaders([
            'Authorization' => self::createAuthHeader((string) $apiKey, (string) $apiSecret),
            'Content-Type' => 'application/json',
        ])->post('https://api.solapi.com/messages/v4/send-many/detail', [
            'messages' => [[
                'to' => $to,
                'from' => preg_replace('/\D/', '', (string) $from),
                'text' => $text,
            ]],
        ]);

        if (! $response->successful()) {
            $error = $response->json();
            $message = $error['errorMessage'] ?? $error['error'] ?? '문자 발송에 실패했습니다.';
            Log::warning('GS brochure Solapi SMS failed', ['status' => $response->status(), 'body' => $response->body()]);

            return ['success' => false, 'error' => $message];
        }

        return ['success' => true];
    }

    public static function sendVerificationCode(string $to, string $code): array
    {
        $pfId = config('services.gs_brochure_solapi.kakao_pf_id');
        $templateId = config('services.gs_brochure_solapi.kakao_otp_template_id');

        if (! empty($pfId) && ! empty($templateId)) {
            $result = self::sendAlimtalkOtp($to, $code);
            if ($result['success']) {
                return $result;
            }

            Log::warning('GS brochure Alimtalk fallback to SMS', ['error' => $result['error'] ?? null]);
        }

        return self::sendSms($to, '[GrapeSEED 브로셔 신청] 인증번호는 ['.$code.'] 입니다. 5분 내에 입력해 주세요.');
    }

    public static function sendAlimtalkOtp(string $to, string $code): array
    {
        $apiKey = config('services.gs_brochure_solapi.key');
        $apiSecret = config('services.gs_brochure_solapi.secret');
        $from = config('services.gs_brochure_solapi.from');
        $pfId = config('services.gs_brochure_solapi.kakao_pf_id');
        $templateId = config('services.gs_brochure_solapi.kakao_otp_template_id');
        $variableKey = config('services.gs_brochure_solapi.kakao_otp_variable', '#{인증번호}');

        if (empty($apiKey) || empty($apiSecret) || empty($from) || empty($pfId) || empty($templateId)) {
            return ['success' => false, 'error' => '알림톡 발송 설정이 되어 있지 않습니다.'];
        }

        $to = preg_replace('/\D/', '', $to);
        if (strlen((string) $to) < 10) {
            return ['success' => false, 'error' => '올바른 전화번호를 입력해 주세요.'];
        }

        try {
            $messageService = new SolapiMessageService((string) $apiKey, (string) $apiSecret);
            $kakaoOption = (new KakaoOption)
                ->setPfId((string) $pfId)
                ->setTemplateId((string) $templateId)
                ->setVariables([(string) $variableKey => (string) $code]);

            $message = (new Message)
                ->setTo((string) $to)
                ->setFrom((string) preg_replace('/\D/', '', (string) $from))
                ->setKakaoOptions($kakaoOption);

            $messageService->send($message);

            return ['success' => true];
        } catch (MessageNotReceivedException $e) {
            $failed = $e->getFailedMessageList();
            $first = $failed[0] ?? null;
            $message = $first && isset($first->statusMessage) ? $first->statusMessage : '알림톡 접수에 실패했습니다.';

            return ['success' => false, 'error' => $message];
        } catch (\Throwable $e) {
            Log::error('GS brochure Alimtalk send exception', ['message' => $e->getMessage()]);

            return ['success' => false, 'error' => $e->getMessage() ?: '알림톡 발송 중 오류가 발생했습니다.'];
        }
    }
}
