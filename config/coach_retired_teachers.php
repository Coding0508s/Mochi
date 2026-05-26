<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 퇴직교사 리스트 목록 소스
    |--------------------------------------------------------------------------
    |
    | - retirement_list: S_RetirementList (기존)
    | - teacher_master: S_TeacherMasterDB (구 Mochi 기준)
    |
    */
    'list_source' => 'teacher_master',

    /*
    |--------------------------------------------------------------------------
    | S_TeacherMasterDB column map (구 Mochi 퇴직교사 SSOT)
    |--------------------------------------------------------------------------
    |
    | 실제 운영 스키마에 맞춰 컬럼명을 조정할 수 있도록 매핑을 둡니다.
    | 로컬/테스트에서 컬럼이 없을 수 있어 코드에서는 Schema::hasColumn으로
    | 안전하게 확인한 뒤 사용합니다.
    |
    */
    'teacher_master' => [
        'table' => 'S_TeacherMasterDB',
        // 구 Mochi 목록: Teachers.Status=퇴직 기준(마스터 미동기화 교사 포함). false면 마스터 행만.
        'list_from_teachers_status' => true,
        'columns' => [
            'id' => 'ID',
            // Legacy DB typo (S_RetirementList와 동일). 없으면 TeacherID로 fallback.
            'teacher_id' => 'TearcherID',
            'name' => 'Name',
            'sk_code' => 'SK_Code',
            // 구 Mochi 마스터는 Account_Name, 일부 환경은 School_Name.
            'school_name' => 'Account_Name',
            'status' => 'Status',
            'retired_at' => 'RetirementDate',
            'tr_name' => 'TR_Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'gs_essentials' => 'GrapeSEEDEssentials',
            'ls_essentials' => 'LittleSEEDEssentials',
            'description' => 'Description',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | S_RetirementList column map (legacy SSOT for retired teachers)
    |--------------------------------------------------------------------------
    |
    | Note: legacy column name is TearcherID (typo), not TeacherID.
    |
    */
    'columns' => [
        'id' => 'ID',
        'teacher_id' => 'TearcherID',
        'name' => 'Name',
        'sk_code' => 'SK_Code',
        'account_name' => 'Account_Name',
        'tr_name' => 'TR_Name',
        'retirement_date' => 'RetirementDate',
        'recommend_yn' => 'RecommendYN',
        'recommend_description' => 'RecommendDescription',
        'description' => 'Description',
        'status' => 'Status',
    ],

    'recommendation' => [
        'default_description_when_no' => '해당사항없음',
        'preset_descriptions_when_yes' => [
            '높은 GrapeSEED 이해도',
        ],
    ],

    'statuses' => [
        'retired' => '퇴직',
        'reinstated' => '복직',
        'teacher_active' => '활성화',
    ],

];
