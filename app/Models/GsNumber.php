<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 레거시 GrapeSEED 번호·담당 스냅샷 테이블 S_GSNumber.
 *
 * @property int $ID
 * @property string $SKCode
 * @property string|null $AccountName
 * @property string|null $GSnumber
 * @property string|null $CO
 * @property string|null $TR
 * @property string|null $CS
 */
class GsNumber extends Model
{
    protected $table = 'S_GSNumber';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'SKCode',
        'AccountName',
        'GSnumber',
        'CO',
        'TR',
        'CS',
    ];

    protected function casts(): array
    {
        return [
            'GSnumber' => 'string',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'SKCode', 'SKcode');
    }
}
