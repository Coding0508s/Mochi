<?php

namespace App\Console\Commands;

use App\Models\AccountInformation;
use App\Models\Institution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class LinkSchoolStaff extends Command
{
    protected $signature = 'import:link-school-staff {file : School MST Excel 파일 경로}';

    protected $description = '신규 기관에만 S_Account_Information(TR/CS/CO)를 생성합니다. 기존 데이터는 절대 건드리지 않습니다.';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("파일을 찾을 수 없습니다: {$filePath}");

            return self::FAILURE;
        }

        // ── lookup 테이블 미리 로드 ──────────────────────────────────
        $consultants = DB::table('school_consultants')->pluck('name', 'consultant_id')->toArray();
        $coaches = DB::table('school_coaches')->pluck('name', 'coach_id')->toArray();
        $csStaff = DB::table('school_cs_staff')->pluck('name', 'cs_id')->toArray();

        // ── 이미 S_Account_Information이 있는 SK_Code 목록 ───────────
        $existingSkCodes = AccountInformation::pluck('SK_Code')->flip()->toArray();

        // ── Excel 로드 ───────────────────────────────────────────────
        $this->info('Excel 로딩 중...');
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        $headers = array_map('trim', array_shift($rows));

        $inserted = 0;
        $skipped = 0;
        $noInstitution = 0;

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            $data = array_combine($headers, $row);

            $skCode = trim((string) ($data['SchoolCode'] ?? ''));
            $consultantId = (int) ($data['ConsultantID'] ?? 0);
            $coachId = (int) ($data['CoachID'] ?? 0);
            $csId = (int) ($data['CsID'] ?? 0);

            if ($skCode === '') {
                $bar->advance();

                continue;
            }

            // ① 이미 S_Account_Information이 있으면 건너뜀
            if (isset($existingSkCodes[$skCode])) {
                $skipped++;
                $bar->advance();

                continue;
            }

            // ② 기관이 S_AccountName에 없으면 건너뜀 (혹시 모를 안전장치)
            $institution = Institution::where('SKcode', $skCode)->first();
            if (! $institution) {
                $noInstitution++;
                $bar->advance();

                continue;
            }

            // ③ 이름 해석 (ID → 이름, 없으면 빈 문자열)
            $coName = $consultants[$consultantId] ?? '';
            $trName = $coaches[$coachId] ?? '';
            $csName = $csStaff[$csId] ?? '';

            AccountInformation::create([
                'SK_Code' => $skCode,
                'Account_Name' => $institution->AccountName,
                'CO' => $coName,  // Consultant
                'TR' => $trName,  // Coach (Trainer)
                'CS' => $csName,
                'Customer_Type' => trim((string) ($data['CustomerType'] ?? '')),
                'Address' => $institution->Address ?? '',
            ]);

            $existingSkCodes[$skCode] = true; // 중복 방지
            $inserted++;

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['결과', '건수'],
            [
                ['신규 S_Account_Information 생성', $inserted],
                ['건너뜀 (이미 기록 존재)',         $skipped],
                ['건너뜀 (기관 없음)',              $noInstitution],
            ]
        );

        return self::SUCCESS;
    }
}
