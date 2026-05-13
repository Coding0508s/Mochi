<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 기관 지원 보고서 저장 알림 수신 주소
    |--------------------------------------------------------------------------
    |
    | SUPPORT_REPORT_NOTIFY_ADDRESSES 에 쉼표로 구분해 여러 주소를 넣을 수 있습니다.
    | (그룹 메일 하나만 넣어도 됩니다.) 비어 있으면 저장 시 메일을 보내지 않습니다.
    |
    */

    'notify_addresses' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SUPPORT_REPORT_NOTIFY_ADDRESSES', '')),
    ))),

    /*
    | 알림 메일 본문 기본 글자 크기(px). 메일 클라이언트 호환을 위해 10~22 범위로 제한합니다.
    */
    'email_font_size_px' => min(22, max(10, (int) env('SUPPORT_REPORT_MAIL_FONT_PX', 14))),
];
