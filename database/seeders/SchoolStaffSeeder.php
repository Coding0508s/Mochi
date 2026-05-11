<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolStaffSeeder extends Seeder
{
    public function run(): void
    {
        // ── CS 담당자 (CsID) ──────────────────────────────────────────
        $csStaff = [
            ['cs_id' => 1000,  'name' => 'Geena Yoon'],
            ['cs_id' => 6000,  'name' => 'Grace Kwon'],
            ['cs_id' => 9900,  'name' => 'Aileen Kim'],
            ['cs_id' => 13000, 'name' => 'Sunny Choi'],
            ['cs_id' => 12000, 'name' => 'Ellen Joo'],
            ['cs_id' => 9000,  'name' => 'Bella Joo'],
        ];

        foreach ($csStaff as $row) {
            DB::table('school_cs_staff')->insertOrIgnore($row);
        }

        // ── Consultant 담당자 (ConsultantID) ────────────────────────
        $consultants = [
            ['consultant_id' => 1,  'name' => 'Addy Kim'],
            ['consultant_id' => 2,  'name' => 'Danial Kim'],
            ['consultant_id' => 3,  'name' => 'Luna Oh'],
            ['consultant_id' => 4,  'name' => 'Megan Yang'],
            ['consultant_id' => 5,  'name' => 'Peter Kim'],
            ['consultant_id' => 6,  'name' => 'Ryan Koh'],
            ['consultant_id' => 7,  'name' => 'Samuel Yun'],
            ['consultant_id' => 8,  'name' => 'David Bang'],
            ['consultant_id' => 9,  'name' => 'Derek Kwon'],
            ['consultant_id' => 10, 'name' => 'James Park'],
            ['consultant_id' => 11, 'name' => 'Jay Kim'],
            ['consultant_id' => 12, 'name' => 'Mark Kim'],
            ['consultant_id' => 13, 'name' => 'Neo Choi'],
            ['consultant_id' => 14, 'name' => 'Simon Choi'],
            ['consultant_id' => 15, 'name' => 'Sunny Jung'],
            ['consultant_id' => 16, 'name' => 'Tiffany Kim'],
            ['consultant_id' => 17, 'name' => 'Ashley Choi'],
            ['consultant_id' => 18, 'name' => 'Lyla Kim'],
            ['consultant_id' => 19, 'name' => 'Ron Shin'],
            ['consultant_id' => 20, 'name' => 'James Kwak'],
            ['consultant_id' => 21, 'name' => 'Thomas Go'],
        ];

        foreach ($consultants as $row) {
            DB::table('school_consultants')->insertOrIgnore($row);
        }

        // ── Coach 담당자 (CoachID / TR) ──────────────────────────────
        $coaches = [
            ['coach_id' => 1000,  'name' => 'Ashley Choi'],
            ['coach_id' => 2000,  'name' => 'Annie Choi'],
            ['coach_id' => 3000,  'name' => 'Becky Choi'],
            ['coach_id' => 4000,  'name' => 'Christie Jung'],
            ['coach_id' => 5000,  'name' => 'Jessie Yoon'],
            ['coach_id' => 6000,  'name' => 'Lyla Kim'],
            ['coach_id' => 7000,  'name' => 'Leah Lee'],
            ['coach_id' => 8000,  'name' => 'Julien Lee'],
            ['coach_id' => 9000,  'name' => 'Linda Cho'],
            ['coach_id' => 10000, 'name' => 'Sophie Kim'],
            ['coach_id' => 9901,  'name' => 'Chai Kim'],
            ['coach_id' => 9902,  'name' => 'Christina Park'],
            ['coach_id' => 9903,  'name' => 'Curtis Kim'],
            ['coach_id' => 9904,  'name' => 'Eli Jang'],
            ['coach_id' => 9905,  'name' => 'Haley Oh'],
            ['coach_id' => 9906,  'name' => 'Hannah Chang'],
            ['coach_id' => 9907,  'name' => 'Helen Kim'],
            ['coach_id' => 9908,  'name' => 'Jeanie Park'],
            ['coach_id' => 9909,  'name' => 'Jessica Cho'],
            ['coach_id' => 9910,  'name' => 'Julia Kim'],
            ['coach_id' => 9911,  'name' => 'Stacey Seo'],
            ['coach_id' => 9912,  'name' => 'Stella Yun'],
            ['coach_id' => 11000, 'name' => 'Abby Lee'],
            ['coach_id' => 12000, 'name' => 'Tiffany Kim'],
            ['coach_id' => 13000, 'name' => 'Lydia An'],
            ['coach_id' => 14000, 'name' => 'Sara Jung'],
            ['coach_id' => 15000, 'name' => 'Julia Kang'],
            ['coach_id' => 30000, 'name' => 'Danial Kim'],
        ];

        foreach ($coaches as $row) {
            DB::table('school_coaches')->insertOrIgnore($row);
        }

        $this->command->info('✓ CS 6명, Consultant 21명, Coach 28명 삽입 완료 (중복 자동 건너뜀)');
    }
}
