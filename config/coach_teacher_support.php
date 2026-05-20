<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Teachers 테이블 지원 관련 컬럼 매핑
    |--------------------------------------------------------------------------
    | 실제 운영 DB 컬럼명과 앱 내 의미를 연결합니다.
    | 컬럼명이 변경되면 여기만 수정하면 됩니다.
    */

    'columns' => [
        'plan_1st' => 'Plan_1st_Support_Date',
        'plan_2nd' => 'Plan_2nd_Support_Date',
        'completed_1st' => '_1st_Support_Date',
        'completed_2nd' => '_2nd_Support_Date',
        'completed_3rd' => '_3rd_Support_Date',
        'completed_4th' => '_4th_Support_Date',
        'type_1st' => '_1st_Support_Type',
        'type_2nd' => '_2nd_Support_Type',
        'type_3rd' => '_3rd_Support_Type',
        'type_4th' => '_4th_Support_Type',
        'plan_type_1st' => 'Plan_1st_Support_Type',
        'plan_type_2nd' => 'Plan_2nd_Support_Type',
        'essentials_gs' => 'GrapeSEEDEssentials',
        'essentials_ls' => 'LittleSEEDEssentials',
    ],

    /*
    |--------------------------------------------------------------------------
    | 지원 타입 옵션
    |--------------------------------------------------------------------------
    */

    'support_types' => [
        '방문',
        '전화',
        '화상',
        'Observe',
        'Co-teach',
    ],

    /*
    |--------------------------------------------------------------------------
    | 기본 필터 연도
    |--------------------------------------------------------------------------
    */

    'default_year' => null, // null이면 현재 연도 사용

    /*
    |--------------------------------------------------------------------------
    | 교사 프로필 편집 가능 필드 매핑
    |--------------------------------------------------------------------------
    */

    'profile_columns' => [
        'name' => 'Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'position' => 'Position',
        'description' => 'Description',
        'class_in_out' => 'ClassInOut',
        'gs_essentials' => 'GrapeSEEDEssentials',
        'ls_essentials' => 'LittleSEEDEssentials',
        'unit_21' => 'Unit_21_',
        'unit_31' => 'Unit_31_',
        'gs_connect' => 'GrapeSEED_Connect_Training',
        'nexus' => 'Nexus_Training',
        'certi_gs' => 'Certi_Delivery',
        'certi_ls' => 'Certi_Delivery_LS',
        'ls_support' => 'LittleSEED_Support',
    ],

];
