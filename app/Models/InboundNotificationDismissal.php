<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 외부 시스템 연동 알림(`external_assignment_inbound_logs`)을 사용자별로 "내 화면에서 숨김"
 * 처리한 흔적입니다. 실제 로그 row 는 보존하고, 이 테이블의 행이 있으면 해당 사용자에게만
 * 보이지 않게 처리합니다. (다른 사용자에는 영향이 없습니다)
 */
class InboundNotificationDismissal extends Model
{
    protected $fillable = [
        'user_id',
        'log_id',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'dismissed_at' => 'datetime',
        ];
    }
}
