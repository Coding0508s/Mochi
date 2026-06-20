<?php

namespace App\Support;

use App\Enums\SyncOrigin;
use App\Jobs\SyncInstitutionOutboundJob;
use App\Models\AccountInformation;
use App\Models\AssignmentChangeRequest;
use App\Models\GsNumber;
use App\Models\Institution;
use App\Models\SkCodeRequest;
use App\Models\SupportRecord;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstitutionPersistenceSupport
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function reverseSyncToSkCodeRequest(string $skCode, array $values): void
    {
        $request = SkCodeRequest::query()
            ->where('final_sk_code', $skCode)
            ->where('status', 'completed')
            ->orderByDesc('id')
            ->first();

        if (! $request) {
            return;
        }

        $patch = [];
        foreach ($values as $column => $value) {
            if ($value === null) {
                continue;
            }

            $trimmed = trim((string) $value);
            if ($trimmed === '') {
                continue;
            }

            $patch[$column] = $trimmed;
        }

        if ($patch === []) {
            return;
        }

        $syncedAt = now();

        $request->timestamps = false;
        $request->update(array_merge($patch, [
            'applied_at' => $syncedAt,
            'updated_at' => $syncedAt,
        ]));
        $request->timestamps = true;
    }

    /**
     * @param  array{co?: string|null, tr?: string|null, cs?: string|null}  $after
     */
    public function enqueueAssignmentChangeRequestIfNeeded(
        string $skCode,
        ?AccountInformation $before,
        array $after
    ): void {
        if (! (bool) config('services.assignment_sync.enabled', false)) {
            return;
        }

        $beforeValues = [
            'co' => $this->normalizeManagerValue($before?->CO),
            'tr' => $this->normalizeManagerValue($before?->TR),
            'cs' => $this->normalizeManagerValue($before?->CS),
        ];
        $afterValues = [
            'co' => $this->normalizeManagerValue($after['co'] ?? null),
            'tr' => $this->normalizeManagerValue($after['tr'] ?? null),
            'cs' => $this->normalizeManagerValue($after['cs'] ?? null),
        ];

        $patch = [];
        foreach (['co', 'tr', 'cs'] as $key) {
            if ($beforeValues[$key] === $afterValues[$key]) {
                continue;
            }

            $patch[$key] = $afterValues[$key];
        }

        if ($patch === []) {
            return;
        }

        AssignmentChangeRequest::query()->create([
            'sk_code' => $skCode,
            'co' => $patch['co'] ?? null,
            'tr' => $patch['tr'] ?? null,
            'cs' => $patch['cs'] ?? null,
            'changed_by' => auth()->user()?->nameForCoReports(),
            'origin' => AssignmentChangeRequest::ORIGIN_LOCAL,
            'status' => AssignmentChangeRequest::STATUS_PENDING,
            'requested_at' => now(),
        ]);
    }

    public function normalizeManagerValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  array{
     *     sk_code: string,
     *     institution_name: string,
     *     english_name?: string|null,
     *     portal_name?: string|null,
     *     portal_campus_id?: string|null,
     *     account_no?: string|null,
     *     gubun?: string|null,
     *     director?: string|null,
     *     phone?: string|null,
     *     account_tel?: string|null,
     *     address?: string|null,
     *     customer_type?: string|null,
     *     gs_no?: string|null,
     *     co?: string|null,
     *     tr?: string|null,
     *     cs?: string|null,
     * }  $payload
     */
    public function updateInstitutionDetail(Institution $institution, array $payload): Institution
    {
        $oldSk = trim((string) $institution->SKcode);
        $newSk = trim($payload['sk_code']);
        $accountName = trim($payload['institution_name']);
        $trimmedGs = trim((string) ($payload['gs_no'] ?? ''));
        $beforeAccountInfo = AccountInformation::query()
            ->where('SK_Code', $newSk)
            ->first();

        DB::transaction(function () use ($institution, $oldSk, $newSk, $accountName, $trimmedGs, $payload, $beforeAccountInfo): void {
            if ($oldSk !== $newSk) {
                if (Schema::hasTable('Teachers')) {
                    Teacher::query()->where('SK_Code', $oldSk)->update(['SK_Code' => $newSk]);
                }
                SupportRecord::query()->where('SK_Code', $oldSk)->update(['SK_Code' => $newSk]);
                if (Schema::hasTable('S_GSNumber')) {
                    GsNumber::query()->where('SKCode', $oldSk)->update(['SKCode' => $newSk]);
                }
                AccountInformation::query()->where('SK_Code', $oldSk)->update(['SK_Code' => $newSk]);
                if (Schema::hasTable('institution_visibility_overrides')) {
                    DB::table('institution_visibility_overrides')
                        ->where('sk_code', $oldSk)
                        ->update(['sk_code' => $newSk, 'updated_at' => now()]);
                }
            }

            $institution->update([
                'SKcode' => $newSk,
                'AccountName' => $accountName,
                'EnglishName' => filled($payload['english_name'] ?? null) ? trim((string) $payload['english_name']) : null,
                'PortalAccountName' => filled($payload['portal_name'] ?? null) ? trim((string) $payload['portal_name']) : null,
                'PortalCampusID' => filled($payload['portal_campus_id'] ?? null) ? trim((string) $payload['portal_campus_id']) : null,
                'AccountNo' => filled($payload['account_no'] ?? null) ? trim((string) $payload['account_no']) : null,
                'Director' => filled($payload['director'] ?? null) ? trim((string) $payload['director']) : null,
                'Phone' => filled($payload['phone'] ?? null) ? trim((string) $payload['phone']) : null,
                'AccountTel' => filled($payload['account_tel'] ?? null) ? trim((string) $payload['account_tel']) : null,
                'Address' => filled($payload['address'] ?? null) ? trim((string) $payload['address']) : null,
                'Gubun' => filled($payload['gubun'] ?? null) ? trim((string) $payload['gubun']) : null,
                'GSno' => $trimmedGs !== '' ? $trimmedGs : null,
            ]);

            AccountInformation::query()->updateOrCreate(
                ['SK_Code' => $newSk],
                [
                    'Account_Name' => $accountName,
                    'Customer_Type' => filled($payload['customer_type'] ?? null) ? trim((string) $payload['customer_type']) : null,
                    'CO' => filled($payload['co'] ?? null) ? trim((string) $payload['co']) : null,
                    'TR' => filled($payload['tr'] ?? null) ? trim((string) $payload['tr']) : null,
                    'CS' => filled($payload['cs'] ?? null) ? trim((string) $payload['cs']) : null,
                    'Address' => filled($payload['address'] ?? null) ? trim((string) $payload['address']) : null,
                ]
            );

            $this->reverseSyncToSkCodeRequest($newSk, [
                'institution_name' => $accountName,
                'portal_campus_id' => filled($payload['portal_campus_id'] ?? null) ? trim((string) $payload['portal_campus_id']) : null,
                'account_no' => filled($payload['account_no'] ?? null) ? trim((string) $payload['account_no']) : null,
                'co' => filled($payload['co'] ?? null) ? trim((string) $payload['co']) : null,
                'tr' => filled($payload['tr'] ?? null) ? trim((string) $payload['tr']) : null,
                'cs' => filled($payload['cs'] ?? null) ? trim((string) $payload['cs']) : null,
            ]);
            $this->enqueueAssignmentChangeRequestIfNeeded(
                $newSk,
                $beforeAccountInfo,
                [
                    'co' => filled($payload['co'] ?? null) ? trim((string) $payload['co']) : null,
                    'tr' => filled($payload['tr'] ?? null) ? trim((string) $payload['tr']) : null,
                    'cs' => filled($payload['cs'] ?? null) ? trim((string) $payload['cs']) : null,
                ]
            );

            if (Schema::hasTable('S_GSNumber')) {
                GsNumber::query()->updateOrCreate(
                    ['SKCode' => $newSk],
                    [
                        'AccountName' => $accountName !== '' ? $accountName : null,
                        'GSnumber' => $trimmedGs !== '' ? $trimmedGs : null,
                    ]
                );
            }

            DB::afterCommit(function () use ($newSk): void {
                SyncInstitutionOutboundJob::dispatchIf(
                    (bool) config('services.institution_outbound.enabled'),
                    $newSk,
                    SyncOrigin::Local
                );
            });
        });

        return $institution->fresh() ?? $institution;
    }

    /**
     * @param  array{
     *     sk_code: string,
     *     institution_name: string,
     *     co?: string|null,
     *     tr?: string|null,
     *     cs?: string|null,
     * }  $payload
     */
    public function updateInstitutionManagers(array $payload): void
    {
        $skCode = trim($payload['sk_code']);
        $accountName = trim($payload['institution_name']);
        $co = filled($payload['co'] ?? null) ? trim((string) $payload['co']) : null;
        $tr = filled($payload['tr'] ?? null) ? trim((string) $payload['tr']) : null;
        $cs = filled($payload['cs'] ?? null) ? trim((string) $payload['cs']) : null;

        $existing = AccountInformation::query()
            ->where('SK_Code', $skCode)
            ->first();

        DB::transaction(function () use ($skCode, $accountName, $co, $tr, $cs, $existing): void {
            Institution::query()
                ->where('SKcode', $skCode)
                ->update(['AccountName' => $accountName]);

            AccountInformation::query()->updateOrCreate(
                ['SK_Code' => $skCode],
                [
                    'Account_Name' => $accountName,
                    'CO' => $co,
                    'TR' => $tr,
                    'CS' => $cs,
                ]
            );

            if (Schema::hasTable('S_GSNumber')) {
                GsNumber::query()->updateOrCreate(
                    ['SKCode' => $skCode],
                    ['AccountName' => $accountName !== '' ? $accountName : null],
                );
            }

            $this->reverseSyncToSkCodeRequest($skCode, [
                'institution_name' => $accountName,
                'co' => $co,
                'tr' => $tr,
                'cs' => $cs,
            ]);
            $this->enqueueAssignmentChangeRequestIfNeeded(
                $skCode,
                $existing,
                ['co' => $co, 'tr' => $tr, 'cs' => $cs]
            );

            DB::afterCommit(function () use ($skCode): void {
                SyncInstitutionOutboundJob::dispatchIf(
                    (bool) config('services.institution_outbound.enabled'),
                    $skCode,
                    SyncOrigin::Local
                );
            });
        });
    }
}
