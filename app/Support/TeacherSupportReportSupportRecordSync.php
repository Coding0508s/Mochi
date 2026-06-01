<?php

namespace App\Support;

use App\Models\SupportRecord;

final class TeacherSupportReportSupportRecordSync
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function sync(?int $existingSupportRecordId, bool $markCompleted, array $attributes): ?int
    {
        if ($markCompleted) {
            if ($existingSupportRecordId !== null) {
                $record = SupportRecord::query()->find($existingSupportRecordId);
                if ($record !== null) {
                    $record->update($attributes + SupportRecord::completionAttributes(
                        true,
                        $record->CompletedDate,
                    ));

                    return (int) $record->ID;
                }
            }

            $record = SupportRecord::query()->create($attributes + SupportRecord::completionAttributes(true));

            return (int) $record->ID;
        }

        if ($existingSupportRecordId === null) {
            return null;
        }

        $record = SupportRecord::query()->find($existingSupportRecordId);
        if ($record === null) {
            return null;
        }

        $record->update($attributes + SupportRecord::completionAttributes(false));

        return (int) $record->ID;
    }
}
