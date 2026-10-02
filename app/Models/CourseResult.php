<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ผลการเรียนพิเศษ (ร, มส, มผ) และผลการแก้ตัวของนักเรียนหนึ่งคนในรายวิชาหนึ่ง */
class CourseResult extends Model
{
    protected $fillable = ['course_id', 'student_id', 'special', 'remedial_grade', 'remedied_on', 'note', 'recorded_by'];

    protected function casts(): array
    {
        return ['remedied_on' => DateOnly::class];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
