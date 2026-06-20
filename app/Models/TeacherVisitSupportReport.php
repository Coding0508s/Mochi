<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherVisitSupportReport extends Model
{
    protected $fillable = [
        'teacher_id',
        'sk_code',
        'coach_name',
        'institution_name',
        'teacher_name',
        'support_date',
        'support_location',
        'support_purpose',
        'observe_unit',
        'observe_lesson',
        'observe_summary_extra',
        'observe_class',
        'observe_age',
        'session_number',
        'semester_label',
        'interview_date',
        'interview_time',
        'meeting_type',
        'pre_request_notes',
        'monitoring_feedback',
        'interview_and_action_plan',
        'special_notes',
        'status',
        'support_record_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'support_date' => 'date',
            'interview_date' => 'date',
        ];
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'teacher_id', 'ID');
    }

    public function supportRecord(): BelongsTo
    {
        return $this->belongsTo(SupportRecord::class, 'support_record_id', 'ID');
    }
}
