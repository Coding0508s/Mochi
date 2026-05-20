<?php

namespace App\Actions;

use App\Models\SupportRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class DeleteSupportRecord
{
    public function __invoke(SupportRecord $record, string $scopedSkCode): void
    {
        Gate::authorize('deleteSupportRecords');

        $scopedSkCode = trim($scopedSkCode);
        $recordSkCode = trim((string) ($record->SK_Code ?? ''));

        if ($scopedSkCode === '' || $recordSkCode === '' || $recordSkCode !== $scopedSkCode) {
            throw new AuthorizationException('해당 기관의 지원 내역만 삭제할 수 있습니다.');
        }

        $record->delete();
    }
}
