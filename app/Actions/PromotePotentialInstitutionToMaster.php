<?php

namespace App\Actions;

use App\Models\AccountInformation;
use App\Models\CoNewTarget;
use App\Models\Institution;
use App\Models\SupportRecord;
use App\Services\PotentialInstitutionSkCodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PromotePotentialInstitutionToMaster
{
    public function __construct(
        private readonly PotentialInstitutionSkCodeService $skCodeService,
    ) {}

    public function execute(CoNewTarget $target): string
    {
        $sk = trim((string) ($target->AccountCode ?? ''));

        if ($sk === '') {
            $sk = $this->skCodeService->nextAvailableLeadInstitutionSkCode((int) $target->ID);
            $target->update(['AccountCode' => $sk]);
        }

        $name = trim((string) $target->AccountName);

        $institution = Institution::query()->firstOrNew(['SKcode' => $sk]);
        $institution->fill($this->mergePreservingExisting(
            $institution,
            [
                'AccountName' => $name !== '' ? $name : null,
                'Director' => $this->normalizeStringOrNull($target->Director),
                'Phone' => $this->normalizeStringOrNull($target->Phone),
                'Address' => $this->normalizeStringOrNull($target->Address),
                'Gubun' => $this->normalizeStringOrNull($target->Gubun),
                'Possibility' => $this->normalizeStringOrNull($target->Possibility),
            ]
        ));
        $institution->save();

        $accountInfo = AccountInformation::query()->firstOrNew(['SK_Code' => $sk]);
        $accountInfo->fill($this->mergePreservingExisting(
            $accountInfo,
            [
                'Account_Name' => $name !== '' ? $name : null,
                'Address' => $this->normalizeStringOrNull($target->Address),
            ]
        ));
        $accountInfo->Customer_Type = $this->stripTerminationFromCustomerType(
            $accountInfo->Customer_Type
        );
        $accountInfo->save();

        if (Schema::hasColumn('S_SupportInfo_Account', 'potential_target_id')) {
            SupportRecord::query()
                ->where('potential_target_id', (int) $target->ID)
                ->whereNull('SK_Code')
                ->update(['SK_Code' => $sk]);
        }

        $this->clearVisibilityOverrideIfPresent($sk);

        return $sk;
    }

    private function clearVisibilityOverrideIfPresent(string $sk): void
    {
        if (! Schema::hasTable('institution_visibility_overrides')) {
            return;
        }

        DB::table('institution_visibility_overrides')->where('sk_code', $sk)->delete();
    }

    /**
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergePreservingExisting(object $model, array $incoming): array
    {
        $merged = [];

        foreach ($incoming as $key => $value) {
            $existing = $model->{$key} ?? null;
            $merged[$key] = filled($existing) ? $existing : $value;
        }

        return $merged;
    }

    private function stripTerminationFromCustomerType(mixed $value): ?string
    {
        $customerType = trim((string) $value);

        if ($customerType === '') {
            return null;
        }

        $customerType = (string) preg_replace('/(^|\s+)해지(\s+|$)/u', ' ', $customerType);
        $customerType = trim(preg_replace('/\s+/u', ' ', $customerType) ?? '');

        return $customerType === '' ? null : $customerType;
    }

    private function normalizeStringOrNull(mixed $value): ?string
    {
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
