<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 교사 지원 신규 작성 타입 (Pill 버튼 표시용)
    |--------------------------------------------------------------------------
    | 레거시 TR 교사정보메인 화면 순서·라벨과 동일합니다.
    | action=demo_lesson → 시연 수업 보고서 모달, support_create → 지원 보고서 작성
    */

    'types' => [
        ['label' => '신규교사 시연수업', 'action' => 'demo_lesson'],
        ['label' => 'LVA + FR', 'action' => 'lva_fr'],
        ['label' => 'LVA + FB', 'action' => 'lva_fb'],
        ['label' => 'LS On-Site & LVA', 'action' => 'ls_onsite_lva'],
        ['label' => 'LittleSEED Con', 'action' => 'littleseed_con'],
        ['label' => 'On-Site', 'action' => 'onsite'],
        ['label' => 'Pro Con', 'action' => 'pro_con'],
        ['label' => 'Open-Class', 'action' => 'open_class'],
        ['label' => 'Unit 21+', 'action' => 'unit21_plus'],
        ['label' => 'Unit 31+', 'action' => 'unit31_plus'],
        ['label' => '교사 지원 및 참관', 'action' => 'visit'],
    ],

];
