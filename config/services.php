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

    'gs_brochure_teams' => [
        'webhook_url' => env('GS_BROCHURE_TEAMS_WEBHOOK_URL'),
        // 비우면 APP_URL 기준 관리자 대시보드 운송장 섹션으로 연결
        'logistics_url' => env('GS_BROCHURE_TEAMS_LOGISTICS_URL'),
    ],

    /*
    | 외부 플랫폼 → 기관 마스터(S_AccountName) upsert API (Bearer)
    */
    'external_institutions' => [
        'bearer_token' => env('EXTERNAL_INSTITUTION_INGEST_TOKEN'),
    ],

    'gs_brochure_solapi' => [
        'key' => env('GS_BROCHURE_SOLAPI_KEY'),
        'secret' => env('GS_BROCHURE_SOLAPI_SECRET'),
        'from' => env('GS_BROCHURE_SOLAPI_FROM'),
        'kakao_pf_id' => env('GS_BROCHURE_SOLAPI_KAKAO_PF_ID'),
        'kakao_otp_template_id' => env('GS_BROCHURE_SOLAPI_KAKAO_OTP_TEMPLATE_ID'),
        'kakao_otp_variable' => env('GS_BROCHURE_SOLAPI_KAKAO_OTP_VARIABLE', '#{인증번호}'),
    ],

];
