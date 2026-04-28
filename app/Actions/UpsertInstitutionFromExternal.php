<?php

namespace App\Actions;

use App\Models\AccountInformation;
use App\Models\GsNumber;
use App\Models\Institution;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * 외부 플랫폼 연동: S_AccountName(마스터) + S_Account_Information + S_GSNumber 를 SK 기준으로 upsert.
 * 페이로드에 포함된 키만 갱신(PATCH). 키가 없으면 해당 컬럼은 유지.
 */
class UpsertInstitutionFromExternal
{
    /**
     * @param  array<string, mixed>  $patch  API 스네이크 케이스 키만 허용 (검증된 값)
     * @return array{created: bool, institution: Institution}
     */
    public function execute(string $sk, array $patch): array
    {
        $sk = trim($sk);
        if ($sk === '') {
            throw ValidationException::withMessages(['sk' => 'SK 코드가 비어 있습니다.']);
        }

        $existed = Institution::query()->where('SKcode', $sk)->exists();

        $institution = DB::transaction(function () use ($sk, $patch, $existed): Institution {
            $institutionAttrs = $this->buildInstitutionAttributes($sk, $patch, $existed);

            /** @var Institution $institution */
            $institution = Institution::query()->updateOrCreate(
                ['SKcode' => $sk],
                $institutionAttrs
            );

            $this->syncAccountInformation($sk, $institution, $patch);
            $this->syncGsNumber($sk, $institution, $patch);

            return $institution;
        });

        if (config('features.external_institution_ingest_clears_hidden')) {
            $this->clearVisibilityOverrideIfPresent($sk);
        }

        return [
            'created' => ! $existed,
            'institution' => $institution->fresh(['accountInfo', 'gsNumber']),
        ];
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function buildInstitutionAttributes(string $sk, array $patch, bool $existed): array
    {
        $map = [
            'institution_name' => 'AccountName',
            'english_name' => 'EnglishName',
            'portal_account_name' => 'PortalAccountName',
            'account_no' => 'AccountNo',
            'gs_no' => 'GSno',
            'director' => 'Director',
            'phone' => 'Phone',
            'account_tel' => 'AccountTel',
            'address' => 'Address',
            'gubun' => 'Gubun',
            'possibility' => 'Possibility',
        ];

        $attrs = ['SKcode' => $sk];

        foreach ($map as $apiKey => $column) {
            if (! array_key_exists($apiKey, $patch)) {
                continue;
            }
            $attrs[$column] = $this->normalizeStringOrNull($patch[$apiKey]);
        }

        foreach (['ls' => 'LS', 'gs_k' => 'GS_K', 'gs_e' => 'GS_E'] as $apiKey => $column) {
            if (! array_key_exists($apiKey, $patch)) {
                continue;
            }
            $attrs[$column] = max(0, (int) $patch[$apiKey]);
        }

        if (! $existed) {
            $attrs['LS'] = $attrs['LS'] ?? 0;
            $attrs['GS_K'] = $attrs['GS_K'] ?? 0;
            $attrs['GS_E'] = $attrs['GS_E'] ?? 0;
        }

        return $attrs;
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function syncAccountInformation(string $sk, Institution $institution, array $patch): void
    {
        $accountKeys = ['co', 'tr', 'cs', 'customer_type', 'address', 'institution_name'];
        $hasAny = false;
        foreach ($accountKeys as $k) {
            if (array_key_exists($k, $patch)) {
                $hasAny = true;
                break;
            }
        }

        if (! $hasAny) {
            return;
        }

        $name = array_key_exists('institution_name', $patch)
            ? $this->normalizeStringOrNull($patch['institution_name'])
            : $institution->AccountName;

        $row = [
            'Account_Name' => $name,
        ];

        if (array_key_exists('co', $patch)) {
            $row['CO'] = $this->normalizeStringOrNull($patch['co']);
        }
        if (array_key_exists('tr', $patch)) {
            $row['TR'] = $this->normalizeStringOrNull($patch['tr']);
        }
        if (array_key_exists('cs', $patch)) {
            $row['CS'] = $this->normalizeStringOrNull($patch['cs']);
        }
        if (array_key_exists('customer_type', $patch)) {
            $row['Customer_Type'] = $this->normalizeStringOrNull($patch['customer_type']);
        }
        if (array_key_exists('address', $patch)) {
            $row['Address'] = $this->normalizeStringOrNull($patch['address']);
        }

        AccountInformation::query()->updateOrCreate(
            ['SK_Code' => $sk],
            $row
        );
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    private function syncGsNumber(string $sk, Institution $institution, array $patch): void
    {
        if (! Schema::hasTable('S_GSNumber')) {
            return;
        }

        if (! array_key_exists('gs_no', $patch)) {
            return;
        }

        $gs = $this->normalizeStringOrNull($patch['gs_no']);
        $name = $institution->AccountName;

        GsNumber::query()->updateOrCreate(
            ['SKCode' => $sk],
            [
                'AccountName' => $name,
                'GSnumber' => $gs,
            ]
        );
    }

    private function clearVisibilityOverrideIfPresent(string $sk): void
    {
        if (! Schema::hasTable('institution_visibility_overrides')) {
            return;
        }

        DB::table('institution_visibility_overrides')->where('sk_code', $sk)->delete();
    }

    private function normalizeStringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
