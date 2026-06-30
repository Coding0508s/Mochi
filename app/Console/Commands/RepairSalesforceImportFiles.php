<?php

namespace App\Console\Commands;

use App\Support\SalesforceFilesImporter;
use Illuminate\Console\Command;
use Throwable;

class RepairSalesforceImportFiles extends Command
{
    protected $signature = 'salesforce:repair-import-files
        {directory=storage/app/salesforce-import/raw : raw Salesforce 파일 디렉터리}
        {--dry-run : DB/스토리지 저장 없이 검증만 수행}';

    protected $description = 'salesforce-import로 등록됐지만 물리 파일이 없는 contract_documents 행을 raw에서 다시 복사합니다.';

    public function handle(SalesforceFilesImporter $importer): int
    {
        $directory = $this->resolveDirectory((string) $this->argument('directory'));
        if ($directory === null) {
            $this->error('디렉터리를 찾을 수 없습니다: '.$this->argument('directory'));

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->info('Salesforce import 물리 파일 복구를 시작합니다.');
        $this->line('raw 디렉터리: '.$directory);
        $this->line('모드: '.($dryRun ? 'dry-run' : 'save'));

        if (! $dryRun && ! $this->option('no-interaction') && ! $this->confirm('복구를 실행할까요?', true)) {
            $this->info('취소했습니다.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar();
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%%');

        try {
            $result = $importer->repairMissingPhysicalFiles(
                rawDirectory: $directory,
                dryRun: $dryRun,
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
            $this->error('복구 실패: '.$e->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        $errorCount = count($result['errors']);
        $this->table(
            ['결과', '건수'],
            [
                ['검사한 contract_documents', $result['scanned']],
                ['이미 파일 있음', $result['already_ok']],
                ['복구 완료', $result['repaired']],
                ['raw 원본 없음', $result['raw_missing']],
                ['오류', $errorCount],
            ]
        );

        if ($errorCount > 0) {
            $this->warn('일부 행 복구에 실패했습니다.');
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
            $this->info('복구가 완료되었습니다.');
            $this->line('권한 확인: chown -R www-data:www-data storage');
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
