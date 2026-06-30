<?php

namespace App\Console\Commands;

use App\Support\SalesforceFilesImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportSalesforceFiles extends Command
{
    protected $signature = 'salesforce:import-files
        {directory=storage/app/salesforce-import/raw : raw Salesforce 파일 디렉터리}
        {--dry-run : DB/스토리지 저장 없이 검증만 수행}
        {--force : original_filename 이 이미 있어도 다시 가져오기}
        {--no-sf-files : SF_Files 메타데이터 생성 생략}
        {--limit= : 처리할 최대 파일 수 (테스트용)}';

    protected $description = 'salesforce-import/raw 디렉터리의 Salesforce 파일을 contract_documents(및 SF_Files)로 가져옵니다.';

    public function handle(SalesforceFilesImporter $importer): int
    {
        $directory = $this->resolveDirectory((string) $this->argument('directory'));
        if ($directory === null) {
            $this->error('디렉터리를 찾을 수 없습니다: '.$this->argument('directory'));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $skipExisting = ! (bool) $this->option('force');
        $createSfFiles = ! (bool) $this->option('no-sf-files');
        $limit = $this->option('limit');
        $limitValue = is_numeric($limit) ? max(1, (int) $limit) : null;

        $this->info('Salesforce 파일 import를 시작합니다.');
        $this->line('디렉터리: '.$directory);
        $this->line('모드: '.($dryRun ? 'dry-run' : 'save'));
        $this->line('기존 파일명 건너뛰기: '.($skipExisting ? 'on' : 'off'));
        $this->line('SF_Files 생성: '.($createSfFiles ? 'on' : 'off'));
        if ($limitValue !== null) {
            $this->line('limit: '.$limitValue);
        }

        if (! $dryRun && ! $this->option('no-interaction') && ! $this->confirm('import를 실행할까요?', true)) {
            $this->info('취소했습니다.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');

        try {
            $result = $importer->importFromDirectory(
                directory: $directory,
                dryRun: $dryRun,
                skipExisting: $skipExisting,
                createSfFiles: $createSfFiles,
                limit: $limitValue,
                onProgress: function (int $processed, int $total) use ($bar): void {
                    if ($bar->getMaxSteps() !== $total) {
                        $bar->setMaxSteps($total);
                        $bar->start();
                    }

                    $bar->setProgress($processed);
                },
            );
        } catch (Throwable $e) {
            $bar->finish();
            $this->newLine(2);
            $this->error('import 실패: '.$e->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $errorCount = count($result['errors']);
        $this->table(
            ['결과', '건수'],
            [
                ['스캔 파일 수', $result['scanned']],
                ['import 대상 처리', $result['imported']],
                ['기존 파일명 건너뜀', $result['skipped_existing']],
                ['읽기/파일명 오류', $result['skipped_unreadable']],
                ['SF-UNLINKED SK 사용', $result['unlinked_sk_code']],
                ['SF_Files 생성', $result['sf_files_created']],
                ['오류', $errorCount],
            ]
        );

        if ($errorCount > 0) {
            $this->warn('일부 파일 처리 중 오류가 발생했습니다.');
            foreach (array_slice($result['errors'], 0, 20) as $error) {
                $this->line('- '.$error);
            }
            if ($errorCount > 20) {
                $this->line('... (추가 오류 '.($errorCount - 20).'건)');
            }
        }

        if ($dryRun) {
            $this->info('dry-run 완료. 저장하려면 --dry-run 없이 다시 실행하세요.');
        } else {
            $this->info('import가 완료되었습니다.');
        }

        return $errorCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveDirectory(string $rawPath): ?string
    {
        $rawPath = trim($rawPath);
        if ($rawPath === '') {
            return null;
        }

        $candidates = [$rawPath];
        if (! str_starts_with($rawPath, DIRECTORY_SEPARATOR)) {
            $candidates[] = base_path($rawPath);
            $candidates[] = storage_path('app/'.ltrim(str_replace('storage/app/', '', $rawPath), '/'));
            $candidates[] = storage_path($rawPath);
        }

        foreach ($candidates as $candidate) {
            if (is_dir($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        return null;
    }
}
