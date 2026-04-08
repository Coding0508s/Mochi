<?php

namespace Database\Seeders;

use App\Models\AccountInformation;
use App\Models\Institution;
use Illuminate\Database\Seeder;

class InstitutionDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Institution::query()->exists()) {
            return;
        }

        $rows = [
            [
                'SKcode' => 'SK1894',
                'AccountName' => '서울○○유치원',
                'EnglishName' => 'Seoul OO Kindergarten',
                'Phone' => '02-1234-5678',
                'Address' => '서울특별시 강남구 테헤란로 123',
                'Gubun' => '유치원',
                'CO' => 'Daniel Kim',
                'TR' => 'Sarah Lee',
                'CS' => 'Jeenie Park',
                'Customer_Type' => 'GTS 13 기존',
            ],
            [
                'SKcode' => 'SK1893',
                'AccountName' => '경기△△어린이집',
                'EnglishName' => 'Gyeonggi DD Daycare',
                'Phone' => '031-987-6543',
                'Address' => '경기도 성남시 분당구 판교로 456',
                'Gubun' => '어린이집',
                'CO' => 'Daniel Kim',
                'TR' => 'Mike Choi',
                'CS' => 'Jeenie Park',
                'Customer_Type' => 'GTS 13 기존',
            ],
            [
                'SKcode' => 'SK1892',
                'AccountName' => '인천□□초등학교',
                'EnglishName' => 'Incheon Elementary',
                'Phone' => '032-111-2222',
                'Address' => '인천광역시 연수구 컨벤시아대로 789',
                'Gubun' => '초등',
                'CO' => 'Andrew Hur',
                'TR' => 'Sarah Lee',
                'CS' => 'Chris Jung',
                'Customer_Type' => 'GTS 12 신규',
            ],
        ];

        foreach ($rows as $r) {
            $inst = Institution::query()->create([
                'SKcode' => $r['SKcode'],
                'AccountName' => $r['AccountName'],
                'EnglishName' => $r['EnglishName'],
                'Phone' => $r['Phone'],
                'Address' => $r['Address'],
                'Gubun' => $r['Gubun'],
            ]);

            AccountInformation::query()->create([
                'SK_Code' => $r['SKcode'],
                'Account_Name' => $r['AccountName'],
                'TR' => $r['TR'],
                'CS' => $r['CS'],
                'CO' => $r['CO'],
                'Customer_Type' => $r['Customer_Type'],
            ]);
        }
    }
}
