<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherLittleseedConSupportReport extends Model
{
    protected $fillable = [
        'teacher_id',
        'sk_code',
        'coach_name',
        'institution_name',
        'teacher_name',
        'support_date',
        'teacher_experience',
        'session_number',
        'semester_label',
        'interview_date',
        'interview_time',
        'method',
        'procedures',
        'teacher_issue',
        'discussion_content',
        'solution_plan',
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
