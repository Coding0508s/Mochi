<?php

namespace App\Support;

use App\Models\InstitutionExternalMapping;
use InvalidArgumentException;

final class StoreReturnEcountCustResolver
{
    /**
     * @return array{cust: string, cust_des: string}
     */
    public function resolve(?string $institutionSkCode, string $fallbackInstitutionName): array
    {
        $sk = trim((string) $institutionSkCode);
        if ($sk === '') {
            throw new InvalidArgumentException('기관 SK 코드가 없어 Ecount 거래처를 찾을 수 없습니다.');
        }

        $mapping = InstitutionExternalMapping::query()
            ->whereRaw('LOWER(sk_code) = ?', [mb_strtolower($sk)])
            ->first();

        $cust = trim((string) ($mapping?->erp_account_no ?? ''));
        if ($mapping === null || $cust === '') {
            throw new InvalidArgumentException('ERP 거래처코드(erp_account_no) 매핑이 없습니다.');
        }

        $custDes = trim((string) ($mapping->erp_institution_name ?? ''));
        if ($custDes === '') {
            $custDes = trim($fallbackInstitutionName);
        }

        return [
            'cust' => $cust,
            'cust_des' => $custDes !== '' ? $custDes : $cust,
        ];
    }
}
