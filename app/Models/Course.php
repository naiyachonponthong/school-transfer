<?php

namespace App\Models;

use App\Support\Grade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = ['term_id', 'classroom_id', 'subject_id', 'teacher_id', 'locked'];

    protected function casts(): array
    {
        return ['locked' => 'boolean'];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class)->orderBy('sort')->orderBy('id');
    }

    public function canEdit(User $user): bool
    {
        return $user->isAdmin() || $this->teacher_id === $user->id;
    }

    public function maxTotal(): float
    {
        return (float) $this->assessments->sum('max_score');
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(CourseResult::class);
    }

    /** กิจกรรมพัฒนาผู้เรียน: ผลเป็น ผ/มผ แทนเกรด */
    public function isActivity(): bool
    {
        return $this->subject?->type === 'activity';
    }

    /**
     * คะแนนรวม + เกรดของนักเรียนทุกคนในวิชานี้
     * computed = เกรดจากคะแนน · original = ผลก่อนแก้ตัว (ร/มส/มผ ที่ครูกำหนดมาก่อนคะแนน) · grade = ผลสุดท้าย (รวมผลแก้ตัว)
     *
     * @return array<int, array{total: float|null, percent: float|null, computed: string|null, original: string|null, grade: string|null, special: string|null, remedial: string|null, complete: bool}>
     */
    public function results(): array
    {
        $this->loadMissing(['assessments', 'subject']);
        $ids = $this->assessments->pluck('id');
        $max = $this->maxTotal();
        $count = $ids->count();
        $activity = $this->isActivity();

        $rows = Score::whereIn('assessment_id', $ids)->whereNotNull('score')->get()->groupBy('student_id');
        $outcomes = CourseResult::where('course_id', $this->id)->get()->keyBy('student_id');

        $out = [];
        foreach ($rows->keys()->merge($outcomes->keys())->unique() as $studentId) {
            $scores = $rows[$studentId] ?? collect();
            $total = $scores->isEmpty() ? null : (float) $scores->sum('score');
            $percent = $total !== null && $max > 0 ? $total / $max * 100 : null;
            $complete = $scores->count() >= $count && $count > 0;
            $computed = $complete && $percent !== null
                ? ($activity ? Grade::activityFromPercent($percent) : Grade::fromPercent($percent))
                : null;

            $o = $outcomes[$studentId] ?? null;
            $original = $o?->special ?? $computed;
            $out[$studentId] = [
                'total' => $total,
                'percent' => $percent,
                'computed' => $computed,
                'original' => $original,
                'grade' => $o?->remedial_grade ?? $original,
                'special' => $o?->special,
                'remedial' => $o?->remedial_grade,
                'complete' => $complete,
            ];
        }

        return $out;
    }
}
