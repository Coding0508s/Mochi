<?php

namespace App\Actions;

use App\Support\InstitutionPersistenceSupport;
use Illuminate\Auth\Access\AuthorizationException;

class UpdateInstitutionManagers
{
    public function __construct(
        private InstitutionPersistenceSupport $persistence,
    ) {}

    /**
     * @param  array{
     *     sk_code: string,
     *     institution_name: string,
     *     co?: string|null,
     *     tr?: string|null,
     *     cs?: string|null,
     * }  $payload
     */
    public function execute(array $payload): void
    {
        if (trim($payload['sk_code']) === '' || trim($payload['institution_name']) === '') {
            throw new AuthorizationException('기관 식별 정보가 올바르지 않습니다.');
        }

        $this->persistence->updateInstitutionManagers($payload);
    }
}
