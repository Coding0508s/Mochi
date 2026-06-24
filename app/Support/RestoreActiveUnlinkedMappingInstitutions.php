<?php

namespace App\Support;

use App\Models\AccountInformation;
use Illuminate\Support\Facades\DB;

class RestoreActiveUnlinkedMappingInstitutions
{
    /**
     * 미연결 매핑 일괄 해지 처리 예외: 지원·교사 활동이 확인된 기관 SKcode.
     *
     * @var list<string>
     */
    public const EXCEPTION_SK_CODES = [
        'SK2664',
        'SK1548',
        'SK2798',
        'SK2355',
        'SK2650',
        'SK2502',
        'SK2832',
        'SK2708',
        'SK1809',
        'SK2528',
        'SK1665',
    ];

    /**
     * @return array{
     *   target:int,
     *   restored:int,
     *   already_active:int,
     *   missing:int
     * }
     */
    public function execute(bool $apply = false): array
    {
        $stats = [
            'target' => count(self::EXCEPTION_SK_CODES),
            'restored' => 0,
            'already_active' => 0,
            'missing' => 0,
        ];

        foreach (self::EXCEPTION_SK_CODES as $skCode) {
            $accountInfo = AccountInformation::query()
                ->where('SK_Code', $skCode)
                ->first();

            if ($accountInfo === null) {
                $stats['missing']++;

                continue;
            }

            $customerType = (string) ($accountInfo->Customer_Type ?? '');
            if (! str_contains($customerType, '해지')) {
                $stats['already_active']++;

                continue;
            }

            if (! $apply) {
                $stats['restored']++;

                continue;
            }

            DB::transaction(function () use ($accountInfo): void {
                $accountInfo->update(['Customer_Type' => null]);
            });

            $stats['restored']++;
        }

        return $stats;
    }
}
