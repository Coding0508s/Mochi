<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Microsoft Teams Incoming Webhook (물류창고 담당자 채널 알림)
    | 채널 설정 → 커넥터 → Incoming Webhook 에서 URL 복사 후 설정
    */
    'teams' => [
        'webhook_url' => env('TEAMS_WEBHOOK_URL'),
    ],

    /*
    | Solapi (문자 인증번호 발송)
    | 콘솔: https://console.solapi.com/credentials
    | from: 등록된 발신번호 (숫자만, 예: 01012345678)
    */
    'solapi' => [
        'key' => env('SOLAPI_API_KEY'),
        'secret' => env('SOLAPI_API_SECRET'),
        'from' => env('SOLAPI_FROM'),
        'kakao_pf_id' => env('SOLAPI_KAKAO_PF_ID'),
        'kakao_otp_template_id' => env('SOLAPI_KAKAO_OTP_TEMPLATE_ID'),
        // 알림톡 템플릿 내 치환변수 키 (기본: #{인증번호})
        'kakao_otp_variable' => env('SOLAPI_KAKAO_OTP_VARIABLE', '#{인증번호}'),
    ],

];
