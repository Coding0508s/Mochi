<?php

namespace App\Support;

use App\Models\Institution;
use App\Models\InstitutionExternalMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstitutionExternalMappingImporter
{
    /**
     * @return array{
     *   total_rows:int,
     *   created:int,
     *   updated:int,
     *   unchanged:int,
     *   linked:int,
     *   unlinked:int,
     *   portal_missing:int,
     *   errors:array<int, string>
     * }
     */
    public function importFromFile(string $filePath, bool $dryRun = false, bool $allowUpdate = false): array
    {
        $rows = $this->parseRows($filePath);

        $errors = [];
        $seenSkCodes = [];
        $portalMissing = 0;

        foreach ($rows as $row) {
            $data = $row['data'];
            $rowNumber = $row['row'];

            foreach (['기관명', 'AccountNo', 'SKcode', 'ERP 기관명', 'ERP AccountNo'] as $requiredHeader) {
                if (($data[$requiredHeader] ?? '') === '') {
                    $errors[] = "{$rowNumber}행: {$requiredHeader} 값이 비어 있습니다.";
                }
            }

            $skCode = (string) ($data['SKcode'] ?? '');
            if ($skCode !== '' && ! preg_match('/^SK[A-Za-z0-9-]+$/', $skCode)) {
                $errors[] = "{$rowNumber}행: SKcode 형식이 올바르지 않습니다 ({$skCode}).";
            }

            if ($skCode !== '') {
                $normalizedSk = mb_strtolower($skCode);
                if (isset($seenSkCodes[$normalizedSk])) {
                    $errors[] = "{$rowNumber}행: 파일 내 SKcode 중복입니다 ({$skCode}).";
                }
                $seenSkCodes[$normalizedSk] = true;
            }

            $portalCampusId = $data['PortalCampusID'] ?? null;
            if ($portalCampusId === null) {
                $portalMissing++;
            } elseif (! Str::isUuid($portalCampusId)) {
                $errors[] = "{$rowNumber}행: PortalCampusID UUID 형식이 올바르지 않습니다 ({$portalCampusId}).";
            }
        }

        if ($errors !== []) {
            return [
                'total_rows' => count($rows),
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'linked' => 0,
                'unlinked' => 0,
                'portal_missing' => $portalMissing,
                'errors' => $errors,
            ];
        }

        $institutionIdBySk = Institution::query()
            ->select(['ID', 'SKcode'])
            ->get()
            ->mapWithKeys(function (Institution $institution): array {
                $normalizedSk = $this->normalizeSkCode((string) $institution->SKcode);
                if ($normalizedSk === null) {
                    return [];
                }

                return [$normalizedSk => (int) $institution->ID];
            })
            ->all();

        $existingBySk = InstitutionExternalMapping::query()
            ->get()
            ->keyBy(fn (InstitutionExternalMapping $mapping): string => mb_strtolower((string) $mapping->sk_code));

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $linked = 0;
        $unlinked = 0;

        /** @var array<int, array{type:string,id?:int,payload:array<string,mixed>}> $actions */
        $actions = [];

        foreach ($rows as $row) {
            $data = $row['data'];

            $skCode = (string) $data['SKcode'];
            $normalizedSk = mb_strtolower($skCode);
            $institutionId = $institutionIdBySk[$normalizedSk] ?? null;
            if ($institutionId !== null) {
                $linked++;
            } else {
                $unlinked++;
            }

            $payload = [
                'institution_id' => $institutionId,
                'institution_name' => (string) $data['기관명'],
                'account_no' => (string) $data['AccountNo'],
                'sk_code' => $skCode,
                'erp_institution_name' => (string) $data['ERP 기관명'],
                'erp_account_no' => (string) $data['ERP AccountNo'],
                'portal_campus_id' => $data['PortalCampusID'],
            ];

            /** @var InstitutionExternalMapping|null $existing */
            $existing = $existingBySk->get($normalizedSk);
            if (! $existing) {
                $created++;
                $actions[] = [
                    'type' => 'create',
                    'payload' => $payload,
                ];

                continue;
            }

            $isChanged = $this->hasChanged($existing, $payload);
            if (! $isChanged) {
                $unchanged++;

                continue;
            }

            if (! $allowUpdate) {
                $unchanged++;

                continue;
            }

            $updated++;
            $actions[] = [
                'type' => 'update',
                'id' => (int) $existing->id,
                'payload' => $payload,
            ];
        }

        if (! $dryRun && $actions !== []) {
            DB::transaction(function () use ($actions): void {
                foreach ($actions as $action) {
                    if ($action['type'] === 'create') {
                        InstitutionExternalMapping::query()->create($action['payload']);

                        continue;
                    }

                    InstitutionExternalMapping::query()
                        ->whereKey((int) $action['id'])
                        ->update($action['payload']);
                }
            });
        }

        return [
            'total_rows' => count($rows),
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'linked' => $linked,
            'unlinked' => $unlinked,
            'portal_missing' => $portalMissing,
            'errors' => [],
        ];
    }

    /**
     * @return array<int, array{row:int,data:array<string, string|null>}>
     */
    private function parseRows(string $filePath): array
    {
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("파일을 열 수 없습니다: {$filePath}");
        }

        try {
            $header = fgetcsv($handle, 0, "\t");
            if (! is_array($header)) {
                throw new \RuntimeException('TSV 헤더를 읽을 수 없습니다.');
            }

            $header = array_map(fn (mixed $value): string => trim((string) $value), $header);
            if (isset($header[0])) {
                $header[0] = ltrim($header[0], "\xEF\xBB\xBF");
                $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]) ?? $header[0];
            }

            $expectedHeader = ['기관명', 'AccountNo', 'SKcode', 'ERP 기관명', 'ERP AccountNo', 'PortalCampusID'];
            if ($header !== $expectedHeader) {
                throw new \RuntimeException('TSV 헤더가 예상 형식과 다릅니다.');
            }

            $rows = [];
            $rowNumber = 1;
            while (($row = fgetcsv($handle, 0, "\t")) !== false) {
                $rowNumber++;

                if (! is_array($row)) {
                    continue;
                }

                if ($row === [null] || $row === []) {
                    continue;
                }

                if (count($row) < count($header)) {
                    $row = array_pad($row, count($header), '');
                } elseif (count($row) > count($header)) {
                    $row = array_slice($row, 0, count($header));
                }

                /** @var array<string, string|null> $data */
                $data = [];
                foreach ($header as $index => $column) {
                    $value = trim((string) ($row[$index] ?? ''));
                    if ($column === 'PortalCampusID' && $value === '') {
                        $data[$column] = null;

                        continue;
                    }

                    $data[$column] = $value;
                }

                $isEmpty = collect($data)->every(fn (?string $value): bool => $value === null || $value === '');
                if ($isEmpty) {
                    continue;
                }

                $rows[] = [
                    'row' => $rowNumber,
                    'data' => $data,
                ];
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasChanged(InstitutionExternalMapping $existing, array $payload): bool
    {
        foreach ($payload as $column => $value) {
            $existingValue = $existing->getAttribute($column);
            if ((string) ($existingValue ?? '') !== (string) ($value ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function normalizeSkCode(string $skCode): ?string
    {
        $normalized = trim(mb_strtolower($skCode));

        return $normalized === '' ? null : $normalized;
    }
}
