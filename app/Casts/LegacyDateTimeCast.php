<?php

namespace App\Casts;

use App\Support\ExcelSerialDate;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<?CarbonInterface, mixed>
 */
final class LegacyDateTimeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?CarbonInterface
    {
        return ExcelSerialDate::parse($attributes[$key] ?? $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return ExcelSerialDate::toStorageString($value);
    }
}
