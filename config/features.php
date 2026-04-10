<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 기관리스트「신규 기관 등록」버튼
    |--------------------------------------------------------------------------
    |
    | false: 버튼 비활성화, openCreate 딥링크·모달 오픈 불가
    | true: 다시 활성화 (.env: INSTITUTION_CREATE_ENABLED=true)
    |
    */
    'institution_create_enabled' => (bool) env('INSTITUTION_CREATE_ENABLED', false),

];
