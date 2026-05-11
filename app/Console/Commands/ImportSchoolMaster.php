<?php

namespace App\Console\Commands;

use App\Models\Institution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportSchoolMaster extends Command
{
    protected $signature = 'import:school-master {file : Excel 파일 경로}';

    protected $description = 'School MST Excel에서 기관을 가져옵니다. 신규는 전체 행 삽입, 기존 SK는 PortalCampusID만 갱신합니다.';

    public function handle(): int
    {
        $filePath = $this->argument('file');

        if (! file_exists($filePath)) {
            $this->error("파일을 찾을 수 없습니다: {$filePath}");

            return self::FAILURE;
        }

        if (! Schema::hasTable('S_AccountName')) {
            $this->error('S_AccountName 테이블이 없습니다.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('S_AccountName', 'PortalCampusID')) {
            $this->error('PortalCampusID 컬럼이 없습니다. `php artisan migrate` 로 마이그레이션을 적용한 뒤 다시 실행하세요.');

            return self::FAILURE;
        }

        $this->info("파일 로딩 중: {$filePath}");
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        if (empty($rows)) {
            $this->error('Excel 파일이 비어 있습니다.');

            return self::FAILURE;
        }

        $headers = array_shift($rows);
        $headers = $this->normalizeHeaders($headers);

        $this->info(sprintf('총 %d개 행 처리 시작...', count($rows)));

        /** @var array<string, string> 소문자·공백 제거한 SK => DB에 저장된 원본 SKcode */
        $existingSkByNorm = $this->existingSkCodeIndex();

        $inserted = 0;
        $portalCampusUpdated = 0;
        $skipped = 0;
        $errors = 0;

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $data = $this->combineRow($headers, $row);
            if ($data === null) {
                $skipped++;
                $bar->advance();

                continue;
            }

            $cells = $this->cellsByNormalizedHeader($data);

            $skCode = $this->firstNonEmptyCell($cells, ['schoolcode', 'skcode']);
            if ($skCode === '') {
                $skipped++;
                $bar->advance();

                continue;
            }

            $portalCampusId = $this->firstNonEmptyCell($cells, ['portalcampusid']);
            $portalCampusId = $portalCampusId !== '' ? $portalCampusId : null;

            $skNorm = $this->normalizeSkKey($skCode);
            if (isset($existingSkByNorm[$skNorm])) {
                $canonicalSk = $existingSkByNorm[$skNorm];
                try {
                    Institution::query()
                        ->where('SKcode', $canonicalSk)
                        ->update(['PortalCampusID' => $portalCampusId]);
                    $portalCampusUpdated++;
                } catch (\Throwable $e) {
                    $this->newLine();
                    $this->warn("오류 [SKcode={$skCode}, PortalCampusID 갱신]: ".$e->getMessage());
                    $errors++;
                }

                $bar->advance();

                continue;
            }

            $address = trim(implode(' ', array_filter([
                $this->firstNonEmptyCell($cells, ['state']),
                $this->firstNonEmptyCell($cells, ['city']),
                $this->firstNonEmptyCell($cells, ['street']),
            ])));

            try {
                Institution::create([
                    'SKcode' => $skCode,
                    'AccountName' => $this->firstNonEmptyCell($cells, ['accountname']),
                    'EnglishName' => $this->nullableCell($cells, ['accountenglishname']),
                    'PortalAccountName' => $this->nullableCell($cells, ['accountnameinecount']),
                    'PortalCampusID' => $portalCampusId,
                    'AccountNo' => $this->nullableCell($cells, ['accountno']),
                    'Director' => $this->nullableCell($cells, ['principal']),
                    'Phone' => $this->nullableCell($cells, ['schoolphone']),
                    'AccountTel' => $this->nullableCell($cells, ['mobile']),
                    'Address' => $address !== '' ? $address : null,
                ]);

                $existingSkByNorm[$skNorm] = $skCode;
                $inserted++;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("오류 [SKcode={$skCode}]: ".$e->getMessage());
                $errors++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['결과', '건수'],
            [
                ['신규 삽입', $inserted],
                ['기존 SK — PortalCampusID만 갱신', $portalCampusUpdated],
                ['건너뜀 (SchoolCode 없음·행 형식 오류)', $skipped],
                ['오류', $errors],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<int, mixed>  $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $h) {
            $s = trim((string) $h);
            $s = ltrim($s, "\xEF\xBB\xBF");
            $s = preg_replace('/^\x{FEFF}/u', '', $s) ?? $s;
            $out[] = trim($s);
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function combineRow(array $headers, array $row): ?array
    {
        $values = array_values($row);
        $hCount = count($headers);
        if ($hCount === 0) {
            return null;
        }

        if (count($values) < $hCount) {
            $values = array_pad($values, $hCount, null);
        } elseif (count($values) > $hCount) {
            $values = array_slice($values, 0, $hCount);
        }

        try {
            return array_combine($headers, $values);
        } catch (\ValueError) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function cellsByNormalizedHeader(array $data): array
    {
        $cells = [];
        foreach ($data as $header => $value) {
            $key = $this->normalizeHeaderKey((string) $header);
            if ($key === '') {
                continue;
            }
            $cells[$key] = trim((string) $value);
        }

        return $cells;
    }

    private function normalizeHeaderKey(string $header): string
    {
        $header = trim($header);
        $header = ltrim($header, "\xEF\xBB\xBF");
        $header = preg_replace('/^\x{FEFF}/u', '', $header) ?? $header;

        return strtolower(preg_replace('/[\s_\-]+/u', '', $header) ?? '');
    }

    /**
     * @param  array<string, string>  $cells
     * @param  array<int, string>  $normalizedKeys
     */
    private function firstNonEmptyCell(array $cells, array $normalizedKeys): string
    {
        foreach ($normalizedKeys as $k) {
            $v = $cells[$k] ?? '';
            if ($v !== '') {
                return $v;
            }
        }

        return '';
    }

    /**
     * @param  array<string, string>  $cells
     * @param  array<int, string>  $normalizedKeys
     */
    private function nullableCell(array $cells, array $normalizedKeys): ?string
    {
        $v = $this->firstNonEmptyCell($cells, $normalizedKeys);

        return $v !== '' ? $v : null;
    }

    private function normalizeSkKey(string $skCode): string
    {
        return mb_strtolower(trim($skCode));
    }

    /**
     * @return array<string, string>
     */
    private function existingSkCodeIndex(): array
    {
        $map = [];
        foreach (Institution::query()->pluck('SKcode') as $sk) {
            $sk = trim((string) $sk);
            if ($sk === '') {
                continue;
            }
            $map[$this->normalizeSkKey($sk)] = $sk;
        }

        return $map;
    }
}
