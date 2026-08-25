<?php

use App\Support\TeamMenuContext;

return [
    'team_lead_jobs' => [
        'Department Manager',
    ],

    'job_aliases' => [
        'DepartmentManager',
    ],

    'coach_work_depts' => [
        TeamMenuContext::DEPT_COACH,
    ],

    /*
    |--------------------------------------------------------------------------
    | 유형 × 월 매트릭스 KPI
    |--------------------------------------------------------------------------
    |
    | On-Site: LS On-Site & LVA 합산 (화면 행에 LS 단독이 없음)
    | 기관지원: Status=완료만 (교사 라벨 동기화 행은 institution_types로 제외)
    |
    */

    'onsite_includes_ls' => true,

    'institution_completed_only' => true,

    /*
    | 연도 「전체」 = 올해 포함 최근 N+1년 (lookback=3 → 올해~3년 전 = 4년)
    */
    'all_years_lookback' => 3,

    /*
    | 특정 연도 선택 시 업무 연도: Y-01-01 ~ (Y+1)-MM-DD (아래 월까지 spillover 열)
    | 「전체」에는 spillover 열을 두지 않는다.
    */
    'spillover_months' => [1, 2, 3],

    'institution_types' => [
        '전화',
        '대면',
        '화상',
    ],

    'matrix_rows' => [
        [
            'key' => 'inst_phone',
            'group' => 'institution',
            'label' => '전화',
            'match_types' => ['전화'],
        ],
        [
            'key' => 'inst_visit',
            'group' => 'institution',
            'label' => '대면',
            'match_types' => ['대면'],
        ],
        [
            'key' => 'inst_video',
            'group' => 'institution',
            'label' => '화상',
            'match_types' => ['화상'],
        ],
        [
            'key' => 'teacher_demo',
            'group' => 'teacher',
            'label' => '신규교사 시연수업',
            'match_types' => [
                '신규교사 시연수업',
                '교사 지원(신규교사)',
            ],
        ],
        [
            'key' => 'teacher_lva',
            'group' => 'teacher',
            'label' => 'LVA+FR/FB',
            'match_types' => [
                'LVA + FR',
                'LVA + FB',
                'LVA+FR',
                'LVA+FB',
                '교사 지원 LVA FR',
                '교사 지원 LVA FB',
                '교사 지원 LVA',
            ],
        ],
        [
            'key' => 'teacher_onsite',
            'group' => 'teacher',
            'label' => 'On-Site',
            'match_types' => [
                'On-Site',
                'On-site',
                '교사 지원 On-Site',
                // onsite_includes_ls=true 일 때 Aggregator가 LS 라벨을 추가
            ],
        ],
        [
            'key' => 'teacher_pro_con',
            'group' => 'teacher',
            'label' => 'Pro Con',
            'match_types' => [
                'Pro Con',
            ],
        ],
        [
            'key' => 'teacher_open_class',
            'group' => 'teacher',
            'label' => 'Open-Class',
            'match_types' => [
                'Open-Class',
                'Open Class',
            ],
        ],
        [
            'key' => 'teacher_unit',
            'group' => 'teacher',
            'label' => 'Unit 21+/31+',
            'match_types' => [
                'Unit 21+',
                'Unit 31+',
            ],
        ],
    ],

    'ls_onsite_type_labels' => [
        'LS On-Site & LVA',
        'LS On-Site and LVA',
    ],
];
