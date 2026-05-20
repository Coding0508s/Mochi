<?php

return [

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

];
