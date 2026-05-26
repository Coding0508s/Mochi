<?php

namespace App\Support;

use App\Models\AccountInformation;
use App\Models\Institution;
use App\Models\Teacher;

/**
 * S_AccountName·S_Account_Information 조합으로 기관을 해석한다.
 * 마스터(S_AccountName) 행이 없어도 Information만으로 Institution을 구성할 수 있다.
 */
final class InstitutionResolver
{
    public static function resolveForSkCode(?string $skCode): ?Institution
    {
        if (blank($skCode)) {
            return null;
        }

        return self::resolve(SkCodeNormalizer::candidates($skCode));
    }

    public static function resolveForTeacher(Teacher $teacher): ?Institution
    {
        return self::resolveForSkCode($teacher->SK_Code);
    }

    /**
     * @param  string[]  $candidateSkCodes
     */
    public static function resolve(array $candidateSkCodes): ?Institution
    {
        if ($candidateSkCodes === []) {
            return null;
        }

        $institution = Institution::query()
            ->whereIn('SKcode', $candidateSkCodes)
            ->with('accountInfo')
            ->first();

        if ($institution !== null) {
            if ($institution->accountInfo === null) {
                $accountInfo = self::findAccountInformation($candidateSkCodes);

                if ($accountInfo !== null) {
                    $institution->setRelation('accountInfo', $accountInfo);
                }
            }

            return $institution;
        }

        $accountInfo = self::findAccountInformation($candidateSkCodes);

        if ($accountInfo === null) {
            return null;
        }

        return self::fromAccountInformation($accountInfo);
    }

    /**
     * @param  string[]  $candidateSkCodes
     */
    public static function findAccountInformation(array $candidateSkCodes): ?AccountInformation
    {
        $lookupCodes = collect($candidateSkCodes)
            ->flatMap(fn (string $code): array => SkCodeNormalizer::candidates($code))
            ->unique()
            ->values()
            ->all();

        if ($lookupCodes === []) {
            return null;
        }

        return AccountInformation::query()
            ->whereIn('SK_Code', $lookupCodes)
            ->first();
    }

    public static function fromAccountInformation(AccountInformation $accountInfo): Institution
    {
        $skCode = SkCodeNormalizer::normalize($accountInfo->SK_Code) ?? trim((string) $accountInfo->SK_Code);

        $institution = new Institution([
            'SKcode' => $skCode,
            'AccountName' => '',
            'Address' => $accountInfo->Address ?? '',
        ]);
        $institution->exists = false;
        $institution->setRelation('accountInfo', $accountInfo);

        return $institution;
    }
}
