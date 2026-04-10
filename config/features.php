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

];
