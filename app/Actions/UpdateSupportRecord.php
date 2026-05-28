<?php

namespace App\Actions;

use App\Models\Institution;
use App\Models\SupportRecord;
use App\Support\ManagerNameNormalizer;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;

class UpdateSupportRecord
{
    /**
     * @param  array{
     *     support_date: string,
     *     support_time: string,
     *     support_type: string,
     *     target?: string|null,
     *     issue?: string|null,
     *     to_account?: string|null,
     *     to_depart?: string|null,
     *     others?: string|null,
     *     completed?: bool
     * }  $payload
     */
    public function __invoke(SupportRecord $record, string $scopedSkCode, array $payload): SupportRecord
    {
        $scopedSkCode = trim($scopedSkCode);
        $recordSkCode = trim((string) ($record->SK_Code ?? ''));

        if ($scopedSkCode === '' || $recordSkCode === '' || $recordSkCode !== $scopedSkCode) {
            throw new AuthorizationException('해당 기관의 지원 내역만 수정할 수 있습니다.');
        }

        if (Schema::hasTable('S_AccountName')) {
            $institution = Institution::query()->with('accountInfo')->where('SKcode', $scopedSkCode)->first();
            if ($institution !== null) {
                $customerType = (string) ($institution->accountInfo?->Customer_Type ?? '');
                if (str_contains($customerType, '해지')) {
                    throw new AuthorizationException('해지된 기관의 지원 내역은 수정할 수 없습니다.');
                }
            }
        }

        $user = auth()->user();
        if ($user === null) {
            throw new AuthorizationException('로그인이 필요합니다.');
        }

        $authorKey = ManagerNameNormalizer::normalize((string) ($record->TR_Name ?? ''));
        $userKey = ManagerNameNormalizer::normalize($user->nameForCoReports());
        if ($authorKey === '' || $userKey === '' || $authorKey !== $userKey) {
            throw new AuthorizationException('본인이 작성한 지원 보고서만 수정할 수 있습니다.');
        }

        $supportDate = Carbon::parse($payload['support_date']);
        $meetTime = trim((string) $payload['support_time']);
        $completed = (bool) ($payload['completed'] ?? false);

        $record->update(SupportRecord::filterAttributesForTable([
            'Year' => (int) $supportDate->format('Y'),
            'Support_Date' => $supportDate->format('Y-m-d'),
            'Meet_Time' => $meetTime !== '' ? $meetTime.':00' : null,
            'Support_Type' => trim((string) $payload['support_type']),
            'Target' => filled($payload['target'] ?? null) ? trim((string) $payload['target']) : null,
            'Issue' => filled($payload['issue'] ?? null) ? trim((string) $payload['issue']) : null,
            'TO_Account' => filled($payload['to_account'] ?? null) ? trim((string) $payload['to_account']) : null,
            'TO_Depart' => filled($payload['to_depart'] ?? null) ? trim((string) $payload['to_depart']) : null,
            'Others' => filled($payload['others'] ?? null) ? trim((string) $payload['others']) : null,
        ] + SupportRecord::completionAttributes($completed, $record->CompletedDate)));

        return $record->fresh() ?? $record;
    }
}
