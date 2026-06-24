<?php

namespace App\Console\Commands;

use App\Support\InstitutionExternalMappingImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportInstitutionExternalMappings extends Command
{
    protected $signature = 'institutions:import-external-mappings
        {file : TSV 파일 경로}
        {--dry-run : DB 저장 없이 검증만 수행}
        {--update : 기존 sk_code 행이 있으면 갱신}';

    protected $description = '기관 외부 매핑 TSV를 institution_external_mappings 테이블에 가져옵니다.';

    public function handle(InstitutionExternalMappingImporter $importer): int
    {
        $rawPath = trim((string) $this->argument('file'));
        $filePath = $this->resolveFilePath($rawPath);
        if ($filePath === null) {
            $this->error("파일을 찾을 수 없습니다: {$rawPath}");

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $allowUpdate = (bool) $this->option('update');

        $this->info('기관 외부 매핑 import를 시작합니다.');
        $this->line('파일: '.$filePath);
        $this->line('모드: '.($isDryRun ? 'dry-run' : 'save').', update='.($allowUpdate ? 'on' : 'off'));

        try {
            $result = $importer->importFromFile($filePath, $isDryRun, $allowUpdate);
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('가져오기 실패: '.$e->getMessage());

            return self::FAILURE;
        }

        $errorCount = count($result['errors']);
        if ($errorCount > 0) {
            $this->newLine();
            $this->error("치명적 오류 {$errorCount}건이 있어 저장을 시작하지 않았습니다.");
            foreach (array_slice($result['errors'], 0, 20) as $error) {
                $this->line('- '.$error);
            }
            if ($errorCount > 20) {
                $this->line('... (추가 오류 '.($errorCount - 20).'건)');
            }
        }

        $this->newLine();
        $this->table(
            ['결과', '건수'],
            [
                ['전체 행 수', $result['total_rows']],
                ['신규 저장 수', $result['created']],
                ['업데이트 수', $result['updated']],
                ['변경 없음 수', $result['unchanged']],
                ['기존 institution 연결 수', $result['linked']],
                ['institution 미연결 수', $result['unlinked']],
                ['PortalCampusID 누락 수', $result['portal_missing']],
                ['오류 수', $errorCount],
            ]
        );

        if ($errorCount > 0) {
            return self::FAILURE;
        }

        $this->info($isDryRun ? 'dry-run 검증이 완료되었습니다.' : 'import가 완료되었습니다.');

        return self::SUCCESS;
    }

    private function resolveFilePath(string $rawPath): ?string
    {
        if ($rawPath === '') {
            return null;
        }

        $candidates = [$rawPath];
        if (! str_starts_with($rawPath, DIRECTORY_SEPARATOR)) {
            $candidates[] = base_path($rawPath);
            $candidates[] = storage_path($rawPath);
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
