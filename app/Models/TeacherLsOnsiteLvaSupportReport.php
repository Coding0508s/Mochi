<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherLsOnsiteLvaSupportReport extends Model
{
    protected $fillable = [
        'teacher_id',
        'sk_code',
        'coach_name',
        'institution_name',
        'teacher_name',
        'support_date',
        'observe_set',
        'observe_day',
        'observe_summary_extra',
        'observe_class',
        'observe_age',
        'teacher_experience',
        'session_number',
        'semester_label',
        'interview_date',
        'interview_time',
        'method',
        'other_notes',
        'lesson_length_minutes',
        'procedures',
        'teacher_strengths',
        'areas_of_concerns',
        'next_step',
        'status',
        'support_record_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'support_date' => 'date',
            'interview_date' => 'date',
            'procedures' => 'array',
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
