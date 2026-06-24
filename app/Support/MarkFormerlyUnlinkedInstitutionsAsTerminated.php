<?php

namespace App\Support;

use App\Models\AccountInformation;
use App\Models\Institution;
use App\Models\InstitutionExternalMapping;
use Illuminate\Support\Facades\DB;

class MarkFormerlyUnlinkedInstitutionsAsTerminated
{
    /**
     * 외부 매핑 import 시 마스터에 없어 신규 생성된 기관(FGC_CreateDate null)을 해지로 표시한다.
     *
     * @return array{
     *   target:int,
     *   already_terminated:int,
     *   would_create:int,
     *   would_update:int,
     *   created:int,
     *   updated:int
     * }
     */
    public function execute(bool $apply = false): array
    {
        $stats = [
            'target' => 0,
            'already_terminated' => 0,
            'would_create' => 0,
            'would_update' => 0,
            'created' => 0,
            'updated' => 0,
        ];

        InstitutionExternalMapping::query()
            ->whereNotNull('institution_id')
            ->whereHas('institution', function ($query): void {
                $query->whereNull('FGC_CreateDate');
            })
            ->with(['institution.accountInfo'])
            ->orderBy('id')
            ->chunkById(200, function ($mappings) use (&$stats, $apply): void {
                foreach ($mappings as $mapping) {
                    /** @var InstitutionExternalMapping $mapping */
                    $institution = $mapping->institution;
                    if (! $institution instanceof Institution) {
                        continue;
                    }

                    $stats['target']++;

                    $customerType = (string) ($institution->accountInfo?->Customer_Type ?? '');
                    if (str_contains($customerType, '해지')) {
                        $stats['already_terminated']++;

                        continue;
                    }

                    $skCode = trim((string) $institution->SKcode);
                    if ($skCode === '') {
                        continue;
                    }

                    $accountName = trim((string) ($mapping->institution_name ?? ''));
                    if ($accountName === '') {
                        $accountName = trim((string) ($institution->AccountName ?? ''));
                    }

                    if ($institution->accountInfo === null) {
                        $stats['would_create']++;
                    } else {
                        $stats['would_update']++;
                    }

                    if (! $apply) {
                        continue;
                    }

                    DB::transaction(function () use ($institution, $skCode, $accountName, &$stats): void {
                        $hadAccountInfo = $institution->accountInfo !== null;

                        $payload = ['Customer_Type' => '해지'];
                        if ($accountName !== '') {
                            $payload['Account_Name'] = $accountName;
                        }

                        AccountInformation::query()->updateOrCreate(
                            ['SK_Code' => $skCode],
                            $payload
                        );

                        if ($hadAccountInfo) {
                            $stats['updated']++;
                        } else {
                            $stats['created']++;
                        }
                    });
                }
            });

        return $stats;
    }
}
