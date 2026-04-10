<?php

namespace App\Actions;

use App\Models\AccountInformation;
use App\Models\Institution;
use Illuminate\Support\Facades\DB;

class CreateInstitution
{
    /**
     * @param  array{
     *     sk_code: string,
     *     institution_name: string,
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
     *     possibility?: string|null,
     * }  $data
     */
    public function execute(array $data): Institution
    {
        $sk = trim($data['sk_code']);
        $name = trim($data['institution_name']);

        return DB::transaction(function () use ($data, $sk, $name): Institution {
            $institution = Institution::query()->create([
                'SKcode' => $sk,
                'AccountName' => $name,
                'Director' => isset($data['director']) ? trim((string) $data['director']) ?: null : null,
                'Phone' => isset($data['phone']) ? trim((string) $data['phone']) ?: null : null,
                'AccountTel' => isset($data['account_tel']) ? trim((string) $data['account_tel']) ?: null : null,
                'Address' => isset($data['address']) ? trim((string) $data['address']) ?: null : null,
                'Gubun' => isset($data['gubun']) ? trim((string) $data['gubun']) ?: null : null,
                'GSno' => isset($data['gs_no']) ? trim((string) $data['gs_no']) ?: null : null,
                'Possibility' => isset($data['possibility']) && filled($data['possibility'])
                    ? trim((string) $data['possibility'])
                    : null,
            ]);

            AccountInformation::query()->updateOrCreate(
                ['SK_Code' => $sk],
                [
                    'Account_Name' => $name,
                    'CO' => isset($data['co']) ? trim((string) $data['co']) ?: null : null,
                    'TR' => isset($data['tr']) ? trim((string) $data['tr']) ?: null : null,
                    'CS' => isset($data['cs']) ? trim((string) $data['cs']) ?: null : null,
                    'Customer_Type' => isset($data['customer_type']) ? trim((string) $data['customer_type']) ?: null : null,
                    'Address' => isset($data['address']) ? trim((string) $data['address']) ?: null : null,
                ]
            );

            return $institution;
        });
    }
}
