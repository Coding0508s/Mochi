<?php

namespace App\Services;

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
        $data = $dateTime . $salt;
        $signature = hash_hmac('sha256', $data, $apiSecret);
        return "HMAC-SHA256 apiKey={$apiKey}, date={$dateTime}, salt={$salt}, signature={$signature}";
    }

    /**
     * Send a single SMS via Solapi.
     * @param string $to Recipient phone (digits only, e.g. 01012345678)
     * @param string $text Message body
     * @return array{success: bool, error?: string}
     */
    public static function sendSms(string $to, string $text): array
    {
        $apiKey = config('services.solapi.key');
        $apiSecret = config('services.solapi.secret');
        $from = config('services.solapi.from');

        if (empty($apiKey) || empty($apiSecret) || empty($from)) {
            Log::warning('Solapi: missing config (key, secret or from)');
            return ['success' => false, 'error' => '문자 발송 설정이 되어 있지 않습니다.'];
        }

        $to = preg_replace('/\D/', '', $to);
        if (strlen($to) < 10) {
            return ['success' => false, 'error' => '올바른 전화번호를 입력해 주세요.'];
        }

        $authHeader = self::createAuthHeader($apiKey, $apiSecret);
        $body = [
            'messages' => [
                [
                    'to' => $to,
                    'from' => preg_replace('/\D/', '', $from),
                    'text' => $text,
                ],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => $authHeader,
                'Content-Type' => 'application/json',
            ])->post('https://api.solapi.com/messages/v4/send-many/detail', $body);

            if (!$response->successful()) {
                $err = $response->json();
                $message = $err['errorMessage'] ?? $err['error'] ?? '문자 발송에 실패했습니다.';
                Log::warning('Solapi send failed', ['status' => $response->status(), 'body' => $response->body()]);
                return ['success' => false, 'error' => $message];
            }
            return ['success' => true];
        } catch (\Throwable $e) {
            Log::error('Solapi send exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => '문자 발송 중 오류가 발생했습니다.'];
        }
    }

    /**
     * Send verification code: try Kakao Alimtalk first if configured, otherwise SMS.
     *
     * @param string $to   Recipient phone (digits only)
     * @param string $code 6-digit OTP code
     * @return array{success: bool, error?: string}
     */
    public static function sendVerificationCode(string $to, string $code): array
    {
        $pfId = config('services.solapi.kakao_pf_id');
        $templateId = config('services.solapi.kakao_otp_template_id');

        if (! empty($pfId) && ! empty($templateId)) {
            $result = self::sendAlimtalkOtp($to, $code);
            if ($result['success']) {
                return $result;
            }
            Log::warning('Solapi Alimtalk failed, falling back to SMS', ['error' => $result['error'] ?? '']);
        }

        $text = '[GrapeSEED 브로셔 신청] 인증번호는 [' . $code . '] 입니다. 5분 내에 입력해 주세요.';
        return self::sendSms($to, $text);
    }

    /**
     * Send OTP via Kakao Alimtalk (알림톡) using Solapi PHP SDK.
     * Uses SOLAPI_KAKAO_PF_ID, SOLAPI_KAKAO_OTP_TEMPLATE_ID.
     * Template variable for code: "#{인증번호}" (or SOLAPI_KAKAO_OTP_VARIABLE).
     *
     * @param string $to   Recipient phone (digits only)
     * @param string $code 6-digit OTP code
     * @return array{success: bool, error?: string}
     */
    public static function sendAlimtalkOtp(string $to, string $code): array
    {
        $apiKey = config('services.solapi.key');
        $apiSecret = config('services.solapi.secret');
        $from = config('services.solapi.from');
        $pfId = config('services.solapi.kakao_pf_id');
        $templateId = config('services.solapi.kakao_otp_template_id');
        $variableKey = config('services.solapi.kakao_otp_variable', '#{인증번호}');

        if (empty($apiKey) || empty($apiSecret) || empty($from) || empty($pfId) || empty($templateId)) {
            Log::warning('Solapi Alimtalk: missing config (key, secret, from, kakao_pf_id or kakao_otp_template_id)');
            return ['success' => false, 'error' => '알림톡 발송 설정이 되어 있지 않습니다.'];
        }

        $to = preg_replace('/\D/', '', $to);
        if (strlen($to) < 10) {
            return ['success' => false, 'error' => '올바른 전화번호를 입력해 주세요.'];
        }

        try {
            $messageService = new SolapiMessageService($apiKey, $apiSecret);

            $kakaoOption = new KakaoOption();
            $kakaoOption->setPfId($pfId)
                ->setTemplateId($templateId)
                ->setVariables([$variableKey => (string) $code]);

            $message = new Message();
            $message->setTo($to)
                ->setFrom(preg_replace('/\D/', '', $from))
                ->setKakaoOptions($kakaoOption);

            $messageService->send($message);

            return ['success' => true];
        } catch (MessageNotReceivedException $e) {
            $failed = $e->getFailedMessageList();
            $first = $failed[0] ?? null;
            $message = $first && isset($first->statusMessage) ? $first->statusMessage : '알림톡 접수에 실패했습니다.';
            Log::warning('Solapi Alimtalk MessageNotReceived', ['failed' => $failed]);
            return ['success' => false, 'error' => $message];
        } catch (\Throwable $e) {
            Log::error('Solapi Alimtalk send exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage() ?: '알림톡 발송 중 오류가 발생했습니다.'];
        }
    }
}
