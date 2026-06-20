<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 레거시 TR 교사지원 보고서 테이블 (TeacherId 기준)
    |--------------------------------------------------------------------------
    */

    'legacy_sources' => [
        [
            'table' => 'S_Support_NewTeacher',
            'type' => '교사 지원(신규교사)',
        ],
        [
            'table' => 'S_Support_LVA',
            'type_resolver' => 'lva',
        ],
        [
            'table' => 'S_Support_OnSite',
            'type' => '교사 지원 On-Site',
        ],
        [
            'table' => 'S_Support_OpenClass',
            'type' => 'Open-Class',
        ],
        [
            'table' => 'S_SupportLittleSEED_ONLVA',
            'type' => 'LS On-Site & LVA',
        ],
        [
            'table' => 'S_Support_U21',
            'type' => 'Unit 21+',
        ],
        [
            'table' => 'S_Support_U31',
            'type' => 'Unit 31+',
        ],
        [
            'table' => 'S_SolutionConsulting',
            'type' => 'Pro Con',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MOCHI 신규 보고서 테이블 (teacher_id 기준)
    |--------------------------------------------------------------------------
    */

    'mochi_report_tables' => [
        'teacher_demo_lesson_support_reports' => '신규교사 시연수업',
        'teacher_lva_fr_support_reports' => 'LVA + FR',
        'teacher_lva_fb_support_reports' => 'LVA + FB',
        'teacher_ls_onsite_lva_support_reports' => 'LS On-Site & LVA',
        'teacher_littleseed_con_support_reports' => 'LittleSEED Con',
        'teacher_onsite_support_reports' => 'On-Site',
        'teacher_pro_con_support_reports' => 'Pro Con',
        'teacher_open_class_support_reports' => 'Open-Class',
        'teacher_unit21_plus_support_reports' => 'Unit 21+',
        'teacher_unit31_plus_support_reports' => 'Unit 31+',
        'teacher_visit_support_reports' => '교사 지원 및 참관',
    ],

    'lva_report_types' => [
        2 => 'FR',
        3 => 'FB',
    ],

];
