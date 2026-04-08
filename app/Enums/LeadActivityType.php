<?php

namespace App\Enums;

enum LeadActivityType: string
{
    case Call = 'call';
    case Email = 'email';
    case Visit = 'visit';
    case Note = 'note';
    case StageChange = 'stage_change';
    case StatusChange = 'status_change';
    case Task = 'task';

    public function label(): string
    {
        return match ($this) {
            self::Call => __('lead.activity.call'),
            self::Email => __('lead.activity.email'),
            self::Visit => __('lead.activity.visit'),
            self::Note => __('lead.activity.note'),
            self::StageChange => __('lead.activity.stage_change'),
            self::StatusChange => __('lead.activity.status_change'),
            self::Task => __('lead.activity.task'),
        };
    }
}
