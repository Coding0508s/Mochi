<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 신규 기관 등록 (/institutions/create)
    |--------------------------------------------------------------------------
    |
    | false: 등록 페이지 접근 시 기관리스트로 리다이렉트·안내
    | true: /institutions/create 에서 신규 기관 등록 가능 (.env: INSTITUTION_CREATE_ENABLED=true)
    |
    */
    'institution_create_enabled' => (bool) env('INSTITUTION_CREATE_ENABLED', false),

    /*
    | 외부 연동 upsert 성공 시 institution_visibility_overrides 행 삭제(숨김 해제)
    | false: 숨김 유지 (기본)
    */
    'external_institution_ingest_clears_hidden' => (bool) env('EXTERNAL_INSTITUTION_INGEST_CLEARS_HIDDEN', false),

    /*
    |--------------------------------------------------------------------------
    | People-Account 분리/운영 플래그
    |--------------------------------------------------------------------------
    */
    'people_use_account_link' => (bool) env('PEOPLE_USE_ACCOUNT_LINK', true),
    'people_account_email_fallback_enabled' => (bool) env('PEOPLE_ACCOUNT_EMAIL_FALLBACK_ENABLED', false),
    'people_modal_account_edit_enabled' => (bool) env('PEOPLE_MODAL_ACCOUNT_EDIT_ENABLED', true),

];
