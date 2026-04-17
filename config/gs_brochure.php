<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GS Brochure database boundary
    |--------------------------------------------------------------------------
    |
    | 기본은 현재 애플리케이션 DB 연결을 재사용하고, 테이블 이름에 prefix를 붙여
    | 기존 모카 도메인 테이블과 충돌하지 않도록 분리합니다.
    |
    */
    'connection' => env('GS_BROCHURE_DB_CONNECTION', env('DB_CONNECTION', 'sqlite')),
    'table_prefix' => env('GS_BROCHURE_TABLE_PREFIX', 'gsb_'),

];
