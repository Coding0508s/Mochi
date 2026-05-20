<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherUnit31PlusSupportReport extends Model
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
        'progress_unit',
        'progress_lesson',
        'progress_other',
        'procedures',
        'verbal_materials',
        'language_arts_materials',
        'verbal_comments',
        'language_arts_comments',
        'overall_comments',
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
            'verbal_materials' => 'array',
            'language_arts_materials' => 'array',
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
