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
        // auto: URL 기준 자동 (logic.azure.com → Adaptive Card, webhook.office.com → MessageCard)
        'webhook_format' => env('GS_BROCHURE_TEAMS_WEBHOOK_FORMAT', 'auto'),
        // 비우면 APP_URL 기준 관리자 대시보드 운송장 섹션으로 연결
        'logistics_url' => env('GS_BROCHURE_TEAMS_LOGISTICS_URL'),
    ],

    'store_return_teams' => [
        'webhook_url' => env('STORE_RETURN_TEAMS_WEBHOOK_URL'),
        'webhook_format' => env('STORE_RETURN_TEAMS_WEBHOOK_FORMAT', 'auto'),
        // 비우면 CS 팀 반품 등록 화면으로 연결
        'returns_url' => env('STORE_RETURN_TEAMS_RETURNS_URL'),
    ],

    /*
    | 외부 플랫폼 → 기관 마스터(S_AccountName) upsert API (Bearer)
    */
    'external_institutions' => [
        'enabled' => env('EXTERNAL_INSTITUTION_INGEST_ENABLED', false),
        'bearer_token' => env('EXTERNAL_INSTITUTION_INGEST_TOKEN'),
    ],

    /*
     | 기관 마스터(S_AccountName) → 외부 플랫폼 outbound API (Bearer)
     */
    'institution_outbound' => [
        'enabled' => env('INSTITUTION_OUTBOUND_ENABLED', false),
        'base_url' => env('INSTITUTION_OUTBOUND_BASE_URL'),
        'bearer_token' => env('INSTITUTION_OUTBOUND_BEARER_TOKEN'),
    ],

    /*
    | 담당자 변경 브릿지 테이블(assignment_change_requests) 동기화
    */
    'assignment_sync' => [
        'enabled' => env('ASSIGNMENT_SYNC_ENABLED', false),
        'schedule' => env('ASSIGNMENT_SYNC_SCHEDULE', '* * * * *'),
    ],

    /*
    | 상대 DB → 기관 마스터(S_AccountName) 직접 풀링 연동
    */
    'partner_institutions' => [
        'enabled' => env('PARTNER_INSTITUTION_SYNC_ENABLED', false),
        'connection' => env('PARTNER_INSTITUTION_DB_CONNECTION', 'partner'),
        'table' => env('PARTNER_INSTITUTION_TABLE', 'institutions'),
        'primary_key' => env('PARTNER_INSTITUTION_PRIMARY_KEY', 'id'),
        'batch_size' => (int) env('PARTNER_INSTITUTION_SYNC_BATCH_SIZE', 100),
        'schedule' => env('PARTNER_INSTITUTION_SYNC_SCHEDULE', '*/5 * * * *'),
        'changed_at_column' => env('PARTNER_INSTITUTION_CHANGED_AT_COLUMN', 'updated_at'),
        'status_column' => env('PARTNER_INSTITUTION_STATUS_COLUMN'),
        'pending_status' => env('PARTNER_INSTITUTION_PENDING_STATUS', 'pending'),
        'applied_status' => env('PARTNER_INSTITUTION_APPLIED_STATUS', 'applied'),
        'failed_status' => env('PARTNER_INSTITUTION_FAILED_STATUS', 'failed'),
        'mark_remote_rows' => env('PARTNER_INSTITUTION_MARK_REMOTE_ROWS', false),
        'state_cache_key' => env('PARTNER_INSTITUTION_STATE_CACHE_KEY', 'partner_institution_sync:last_changed_at'),
        'require_sk_with_portal_and_account' => filter_var(
            env('PARTNER_INSTITUTION_REQUIRE_SK_WITH_PORTAL_AND_ACCOUNT', 'true'),
            FILTER_VALIDATE_BOOLEAN
        ),
        // false 이면 상대 DB 풀링 시 institution_name 을 마스터에 넣지 않음(E-Ordering·sk_code_requests 쪽 기관명이 우선)
        'sync_institution_name' => filter_var(
            env('PARTNER_INSTITUTION_SYNC_INSTITUTION_NAME', 'true'),
            FILTER_VALIDATE_BOOLEAN
        ),
        'columns' => [
            'sk' => env('PARTNER_INSTITUTION_SK_COLUMN', 'sk_code'),
            'replaces_sk' => env('PARTNER_INSTITUTION_REPLACES_SK_COLUMN', 'replaces_sk'),
            'institution_name' => env('PARTNER_INSTITUTION_NAME_COLUMN', 'institution_name'),
            'english_name' => env('PARTNER_INSTITUTION_ENGLISH_NAME_COLUMN', 'english_name'),
            'portal_account_name' => env('PARTNER_INSTITUTION_PORTAL_ACCOUNT_NAME_COLUMN', 'portal_account_name'),
            'portal_campus_id' => env('PARTNER_INSTITUTION_PORTAL_CAMPUS_ID_COLUMN', 'portal_campus_id'),
            'account_no' => env('PARTNER_INSTITUTION_ACCOUNT_NO_COLUMN', 'account_no'),
            'gs_no' => env('PARTNER_INSTITUTION_GS_NO_COLUMN', 'gs_no'),
            'director' => env('PARTNER_INSTITUTION_DIRECTOR_COLUMN', 'director'),
            'phone' => env('PARTNER_INSTITUTION_PHONE_COLUMN', 'phone'),
            'account_tel' => env('PARTNER_INSTITUTION_ACCOUNT_TEL_COLUMN', 'account_tel'),
            'address' => env('PARTNER_INSTITUTION_ADDRESS_COLUMN', 'address'),
            'gubun' => env('PARTNER_INSTITUTION_GUBUN_COLUMN', 'gubun'),
            'possibility' => env('PARTNER_INSTITUTION_POSSIBILITY_COLUMN', 'possibility'),
            'ls' => env('PARTNER_INSTITUTION_LS_COLUMN', 'ls'),
            'gs_k' => env('PARTNER_INSTITUTION_GS_K_COLUMN', 'gs_k'),
            'gs_e' => env('PARTNER_INSTITUTION_GS_E_COLUMN', 'gs_e'),
            'co' => env('PARTNER_INSTITUTION_CO_COLUMN', 'co'),
            'tr' => env('PARTNER_INSTITUTION_TR_COLUMN', 'tr'),
            'cs' => env('PARTNER_INSTITUTION_CS_COLUMN', 'cs'),
            'customer_type' => env('PARTNER_INSTITUTION_CUSTOMER_TYPE_COLUMN', 'customer_type'),
        ],
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
