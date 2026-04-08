<?php

namespace App\Services;

use App\Models\AccountInformation;
use App\Models\CoNewTarget;
use App\Models\Institution;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * 잠재기관(S_CO_NewTarget)의 SK 코드(`AccountCode`) 정책.
 *
 * - 운영 계획: 최종 SK는 외부 API 응답으로 수신할 예정입니다.
 * - 현재(수동 등록): 화면 입력값이 있으면 그대로 쓰고, 없으면 기관 목록 연동용 임시 코드 `LEAD-{잠재기관ID}`(충돌 시 접미사)를 부여합니다.
 *
 * API 연동 시: 잠재기관 행이 이미 `LEAD-*` 등으로 기관과 연결된 뒤라면 {@see self::applyExternalSk()} 로 SK만 갱신하면 됩니다.
 */
final class PotentialInstitutionSkCodeService
{
    /**
     * 수동 등록 플로우: 폼에 입력된 SK가 있으면 사용, 없으면 임시 LEAD 코드 생성.
     */
    public function resolveForManualRegistration(string $trimmedFormSk, int $coNewTargetId): string
    {
        if ($trimmedFormSk !== '') {
            return $trimmedFormSk;
        }

        return $this->nextAvailableLeadInstitutionSkCode($coNewTargetId);
    }

    /**
     * SK코드 미입력 시 기관 목록용 코드 (LEAD-{잠재기관ID} … 충돌 시 접미사 증가).
     */
    public function nextAvailableLeadInstitutionSkCode(int $coNewTargetId): string
    {
        $base = 'LEAD-'.$coNewTargetId;
        $candidate = $base;
        $suffix = 1;

        while (Institution::query()->where('SKcode', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * 외부 API에서 확정된 SK 코드를 잠재기관·기관(S_AccountName)·담당정보(S_Account_Information)에 반영합니다.
     *
     * 기존에 `LEAD-*` 등으로 등록된 행의 PK(SK 컬럼)를 API에서 받은 코드로 바꿀 때 사용합니다.
     *
     * @throws InvalidArgumentException SK가 비었거나, 이미 다른 기관에 쓰이는 코드인 경우
     */
    public function applyExternalSk(CoNewTarget $lead, string $skFromApi): void
    {
        $newSk = trim($skFromApi);
        if ($newSk === '') {
            throw new InvalidArgumentException('SK 코드가 비어 있습니다.');
        }

        $oldSk = trim((string) ($lead->AccountCode ?? ''));

        if ($oldSk === $newSk) {
            return;
        }

        if (Institution::query()->where('SKcode', $newSk)->exists()) {
            throw new InvalidArgumentException('이미 기관 목록에 등록된 SK 코드입니다.');
        }

        DB::transaction(function () use ($lead, $oldSk, $newSk): void {
            if ($oldSk !== '') {
                Institution::query()->where('SKcode', $oldSk)->update(['SKcode' => $newSk]);
                AccountInformation::query()->where('SK_Code', $oldSk)->update(['SK_Code' => $newSk]);
            }

            $lead->update(['AccountCode' => $newSk]);
        });
    }
}
