<?php

namespace App\Actions;

use App\Models\Institution;
use App\Support\InstitutionPersistenceSupport;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateInstitutionDetail
{
    public function __construct(
        private InstitutionPersistenceSupport $persistence,
    ) {}

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
    public function execute(Institution $institution, array $payload): Institution
    {
        if (trim($payload['sk_code']) === '' || trim($payload['institution_name']) === '') {
            throw new AuthorizationException('기관 식별 정보가 올바르지 않습니다.');
        }

        return $this->persistence->updateInstitutionDetail($institution, $payload);
    }
}
