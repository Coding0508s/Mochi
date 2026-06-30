<?php

namespace App\Support;

use App\Models\ContractDocument;
use App\Models\Institution;
use App\Models\SalesforceAccount;
use App\Models\SalesforceFile;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Mime\MimeTypes;

class SalesforceFilesImporter
{
    public const FALLBACK_SK_CODE = 'SF-UNLINKED';

    /** @var list<string> */
    private const EXCLUDED_TOP_LEVEL_DIRS = [
        'mochi',
        'ecount',
        'shop',
        'delivery',
        'goods',
        'online',
        '.well-known',
    ];

    /**
     * @param  callable(int $processed, int $total): void|null  $onProgress
     * @return array{
     *   scanned:int,
     *   imported:int,
     *   skipped_existing:int,
     *   skipped_unreadable:int,
     *   sf_files_created:int,
     *   unlinked_sk_code:int,
     *   errors:array<int, string>
     * }
     */
    public function importFromDirectory(
        string $directory,
        bool $dryRun = false,
        bool $skipExisting = true,
        bool $createSfFiles = true,
        ?int $limit = null,
        ?callable $onProgress = null,
    ): array {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        if (! is_dir($directory)) {
            throw new \InvalidArgumentException("디렉터리를 찾을 수 없습니다: {$directory}");
        }

        $files = $this->collectImportableFiles($directory);
        $total = $limit !== null ? min($limit, count($files)) : count($files);
        $files = $limit !== null ? array_slice($files, 0, $limit) : $files;

        $indexes = $this->buildLookupIndexes();
        $existingFilenames = $skipExisting ? $this->existingContractFilenames() : [];
        $existingSfFileNames = $createSfFiles ? $this->existingSfFileNames() : [];

        $result = [
            'scanned' => 0,
            'imported' => 0,
            'skipped_existing' => 0,
            'skipped_unreadable' => 0,
            'sf_files_created' => 0,
            'unlinked_sk_code' => 0,
            'errors' => [],
        ];

        foreach ($files as $index => $absolutePath) {
            $result['scanned']++;
            $basename = basename($absolutePath);

            if ($skipExisting && isset($existingFilenames[mb_strtolower($basename)])) {
                $result['skipped_existing']++;

                if ($onProgress !== null) {
                    $onProgress($index + 1, $total);
                }

                continue;
            }

            if (! is_readable($absolutePath)) {
                $result['skipped_unreadable']++;
                $result['errors'][] = "읽을 수 없음: {$basename}";

                if ($onProgress !== null) {
                    $onProgress($index + 1, $total);
                }

                continue;
            }

            $originalFilename = $this->normalizeOriginalFilename($basename);
            if ($originalFilename === '') {
                $result['skipped_unreadable']++;
                $result['errors'][] = "파일명이 비어 있음: {$absolutePath}";

                if ($onProgress !== null) {
                    $onProgress($index + 1, $total);
                }

                continue;
            }

            $context = $this->resolveAccountContext($basename, $indexes);
            $skCode = $this->resolveSkCode($context, $indexes);
            if ($skCode === self::FALLBACK_SK_CODE) {
                $result['unlinked_sk_code']++;
            }

            $accountName = (string) ($context['account_name'] ?? '-');
            $documentDate = $this->resolveDocumentDate($basename, $absolutePath);
            $sizeBytes = (int) (@filesize($absolutePath) ?: 0);
            $mimeType = $this->guessMimeType($absolutePath, $basename);

            $storedPath = null;
            if (! $dryRun) {
                try {
                    $storedPath = $this->copyIntoContractStorage($absolutePath, $skCode, $basename);
                } catch (\Throwable $e) {
                    $result['errors'][] = "{$basename}: {$e->getMessage()}";

                    if ($onProgress !== null) {
                        $onProgress($index + 1, $total);
                    }

                    continue;
                }

                ContractDocument::query()->create([
                    'sk_code' => $skCode,
                    'account_name' => $accountName !== '' ? $accountName : '-',
                    'changed_account_name' => null,
                    'business_number' => null,
                    'document_date' => $documentDate,
                    'document_time' => '00:00:00',
                    'consultant' => null,
                    'original_filename' => $originalFilename,
                    'stored_disk' => 'local',
                    'stored_path' => $storedPath,
                    'mime_type' => $mimeType,
                    'size_bytes' => $sizeBytes > 0 ? $sizeBytes : null,
                    'uploaded_by' => 'salesforce-import',
                ]);

                $existingFilenames[mb_strtolower($originalFilename)] = true;
            }

            $result['imported']++;

            if ($createSfFiles && Schema::hasTable('SF_Files')) {
                $sfFileNameKey = mb_strtolower($originalFilename);
                if (! isset($existingSfFileNames[$sfFileNameKey])) {
                    if (! $dryRun) {
                        SalesforceFile::query()->create([
                            'fileName' => $originalFilename,
                            'download_Cnt' => 0,
                            'LastUpdate_Date' => now()->format('Y-m-d H:i:s'),
                            'User' => 'salesforce-import',
                            'created_Date' => $this->resolveCreatedDate($absolutePath),
                        ]);
                    }

                    $existingSfFileNames[$sfFileNameKey] = true;
                    $result['sf_files_created']++;
                }
            }

            if ($onProgress !== null) {
                $onProgress($index + 1, $total);
            }
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function collectImportableFiles(string $directory): array
    {
        $paths = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            if (! $fileInfo->isFile()) {
                continue;
            }

            $basename = $fileInfo->getBasename();
            if ($basename === '.DS_Store') {
                continue;
            }

            $relativePath = ltrim(str_replace($directory, '', $fileInfo->getPathname()), DIRECTORY_SEPARATOR);
            if ($this->shouldExcludeRelativePath($relativePath)) {
                continue;
            }

            $paths[] = $fileInfo->getPathname();
        }

        sort($paths);

        return $paths;
    }

    private function shouldExcludeRelativePath(string $relativePath): bool
    {
        $topLevel = explode(DIRECTORY_SEPARATOR, $relativePath)[0] ?? '';

        return in_array(mb_strtolower($topLevel), self::EXCLUDED_TOP_LEVEL_DIRS, true);
    }

    /**
     * @return array{
     *   account_by_id: array<string, array{account_id:string, account_name:string, contract_id:string}>,
     *   account_by_contract_id: array<string, array{account_id:string, account_name:string, contract_id:string}>,
     *   sk_code_by_institution_name: array<string, string>
     * }
     */
    private function buildLookupIndexes(): array
    {
        $accountById = [];
        $accountByContractId = [];

        if (Schema::hasTable('SF_Account')) {
            foreach (SalesforceAccount::query()->get(['account_ID', 'Name', 'GSKR_Contract__c']) as $account) {
                $accountId = trim((string) ($account->account_ID ?? ''));
                $accountName = trim((string) ($account->Name ?? ''));
                $contractId = trim((string) ($account->GSKR_Contract__c ?? ''));

                $payload = [
                    'account_id' => $accountId,
                    'account_name' => $accountName,
                    'contract_id' => $contractId,
                ];

                if ($accountId !== '') {
                    $accountById[mb_strtolower($accountId)] = $payload;
                }

                if ($contractId !== '') {
                    $accountByContractId[mb_strtolower($contractId)] = $payload;
                }
            }
        }

        $skCodeByInstitutionName = [];
        if (Schema::hasTable('S_AccountName')) {
            foreach (Institution::query()->get(['SKcode', 'AccountName', 'PortalAccountName']) as $institution) {
                $skCode = trim((string) ($institution->SKcode ?? ''));
                if ($skCode === '') {
                    continue;
                }

                foreach ([$institution->AccountName, $institution->PortalAccountName] as $name) {
                    $normalized = $this->normalizeInstitutionName((string) $name);
                    if ($normalized !== '' && ! isset($skCodeByInstitutionName[$normalized])) {
                        $skCodeByInstitutionName[$normalized] = $skCode;
                    }
                }
            }
        }

        return [
            'account_by_id' => $accountById,
            'account_by_contract_id' => $accountByContractId,
            'sk_code_by_institution_name' => $skCodeByInstitutionName,
        ];
    }

    /**
     * @param  array<string, mixed>  $indexes
     * @return array{account_id:string, account_name:string, contract_id:string}
     */
    private function resolveAccountContext(string $basename, array $indexes): array
    {
        $accountById = $indexes['account_by_id'];
        $accountByContractId = $indexes['account_by_contract_id'];

        $segments = explode('_', pathinfo($basename, PATHINFO_FILENAME));
        $resolved = [
            'account_id' => '',
            'account_name' => '',
            'contract_id' => '',
        ];

        foreach ($segments as $segment) {
            if (! $this->isSalesforceIdToken($segment)) {
                continue;
            }

            $key = mb_strtolower($segment);

            if (isset($accountById[$key])) {
                return $accountById[$key];
            }

            if (isset($accountByContractId[$key])) {
                return $accountByContractId[$key];
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $indexes
     */
    private function resolveSkCode(array $context, array $indexes): string
    {
        $accountName = $this->normalizeInstitutionName((string) ($context['account_name'] ?? ''));
        if ($accountName !== '') {
            $skCode = $indexes['sk_code_by_institution_name'][$accountName] ?? null;
            if (is_string($skCode) && $skCode !== '') {
                return $skCode;
            }
        }

        return self::FALLBACK_SK_CODE;
    }

    private function normalizeOriginalFilename(string $basename): string
    {
        $trimmed = trim($basename);

        return mb_strlen($trimmed) > 255 ? mb_substr($trimmed, 0, 255) : $trimmed;
    }

    private function normalizeInstitutionName(string $value): string
    {
        $normalized = preg_replace('/\s+/u', '', trim($value)) ?? '';

        return mb_strtolower($normalized);
    }

    private function isSalesforceIdToken(string $token): bool
    {
        return preg_match('/^[a-zA-Z0-9]{15,30}$/', $token) === 1;
    }

    /**
     * @return array<string, true>
     */
    private function existingContractFilenames(): array
    {
        if (! Schema::hasTable('contract_documents')) {
            return [];
        }

        $set = [];
        foreach (ContractDocument::query()->pluck('original_filename') as $filename) {
            $key = mb_strtolower((string) $filename);
            if ($key !== '') {
                $set[$key] = true;
            }
        }

        return $set;
    }

    /**
     * @return array<string, true>
     */
    private function existingSfFileNames(): array
    {
        if (! Schema::hasTable('SF_Files')) {
            return [];
        }

        $set = [];
        foreach (SalesforceFile::query()->pluck('fileName') as $filename) {
            $key = mb_strtolower((string) $filename);
            if ($key !== '') {
                $set[$key] = true;
            }
        }

        return $set;
    }

    private function resolveDocumentDate(string $basename, string $absolutePath): string
    {
        if (preg_match('/(20\d{2})(\d{2})(\d{2})/', $basename, $matches) === 1) {
            return sprintf('%s-%s-%s', $matches[1], $matches[2], $matches[3]);
        }

        if (preg_match('/(20\d{2})[-_.](\d{2})[-_.](\d{2})/', $basename, $matches) === 1) {
            return sprintf('%s-%s-%s', $matches[1], $matches[2], $matches[3]);
        }

        $mtime = @filemtime($absolutePath);
        if ($mtime !== false) {
            return Carbon::createFromTimestamp($mtime)->toDateString();
        }

        return now()->toDateString();
    }

    private function resolveCreatedDate(string $absolutePath): string
    {
        $mtime = @filemtime($absolutePath);

        return $mtime !== false
            ? Carbon::createFromTimestamp($mtime)->format('Y-m-d H:i:s')
            : now()->format('Y-m-d H:i:s');
    }

    private function guessMimeType(string $absolutePath, string $basename): ?string
    {
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($absolutePath);
            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        $extension = mb_strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        if ($extension === '') {
            return null;
        }

        $mimeTypes = MimeTypes::getDefault()->getMimeTypes($extension);

        return $mimeTypes[0] ?? null;
    }

    private function copyIntoContractStorage(string $sourcePath, string $skCode, string $basename): string
    {
        $safeOriginal = preg_replace('/[^\p{L}\p{N}._\-\s]/u', '_', $basename) ?? 'contract';
        $storedName = Str::uuid()->toString().'_'.$safeOriginal;
        $relativeDirectory = 'contract-documents/'.$skCode;
        $relativePath = $relativeDirectory.'/'.$storedName;

        Storage::disk('local')->makeDirectory($relativeDirectory);

        $readStream = @fopen($sourcePath, 'rb');
        if ($readStream === false) {
            throw new \RuntimeException('원본 파일을 열 수 없습니다.');
        }

        try {
            $written = Storage::disk('local')->writeStream($relativePath, $readStream);
        } finally {
            fclose($readStream);
        }

        if ($written === false) {
            throw new \RuntimeException('스토리지 저장에 실패했습니다.');
        }

        return $relativePath;
    }
}
