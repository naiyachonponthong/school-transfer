<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Substitution extends Model
{
    protected $fillable = ['date', 'timetable_slot_id', 'course_id', 'period', 'absent_teacher_id', 'substitute_id', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class];
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(TimetableSlot::class, 'timetable_slot_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function absentTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'absent_teacher_id');
    }

    public function substitute(): BelongsTo
    {
        return $this->belongsTo(User::class, 'substitute_id');
    }

    /** ครูคนนี้ได้รับมอบให้สอนแทนรายวิชานี้ในวันนั้นหรือไม่ */
    public static function covers(User $user, Course $course, string $date): bool
    {
        return self::where('substitute_id', $user->id)->where('course_id', $course->id)->where('date', $date)->exists();
    }
}
