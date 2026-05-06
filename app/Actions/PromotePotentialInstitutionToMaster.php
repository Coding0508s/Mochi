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

        Institution::query()->updateOrCreate(
            ['SKcode' => $sk],
            [
                'AccountName' => $name,
                'Director' => $this->normalizeStringOrNull($target->Director),
                'Phone' => $this->normalizeStringOrNull($target->Phone),
                'Address' => $this->normalizeStringOrNull($target->Address),
                'Gubun' => $this->normalizeStringOrNull($target->Gubun),
                'Possibility' => $this->normalizeStringOrNull($target->Possibility),
            ]
        );

        AccountInformation::query()->updateOrCreate(
            ['SK_Code' => $sk],
            [
                'Account_Name' => $name,
                'Address' => $this->normalizeStringOrNull($target->Address),
            ]
        );

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

    private function normalizeStringOrNull(mixed $value): ?string
    {
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }
}
