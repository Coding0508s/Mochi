<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherDemoLessonSupportReport extends Model
{
    protected $fillable = [
        'teacher_id',
        'sk_code',
        'coach_name',
        'institution_name',
        'teacher_name',
        'support_date',
        'progress_unit',
        'progress_lesson',
        'other_notes',
        'procedures',
        'verbal_tools',
        'language_arts_tools',
        'comments_primary',
        'comments_secondary',
        'evaluations',
        'overall_comments',
        'status',
        'support_record_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'support_date' => 'date',
            'procedures' => 'array',
            'verbal_tools' => 'array',
            'language_arts_tools' => 'array',
            'evaluations' => 'array',
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
