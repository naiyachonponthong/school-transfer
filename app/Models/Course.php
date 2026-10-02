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

    /**
     * คะแนนรวม + เกรดของนักเรียนทุกคนในวิชานี้
     *
     * @return array<int, array{total: float, percent: float|null, grade: string|null, complete: bool}>
     */
    public function results(): array
    {
        $this->loadMissing('assessments');
        $ids = $this->assessments->pluck('id');
        $max = $this->maxTotal();
        $count = $ids->count();

        $rows = Score::whereIn('assessment_id', $ids)->whereNotNull('score')->get()->groupBy('student_id');

        $out = [];
        foreach ($rows as $studentId => $scores) {
            $total = (float) $scores->sum('score');
            $percent = $max > 0 ? $total / $max * 100 : null;
            $complete = $scores->count() >= $count && $count > 0;
            $out[$studentId] = [
                'total' => $total,
                'percent' => $percent,
                'grade' => $complete && $percent !== null ? Grade::fromPercent($percent) : null,
                'complete' => $complete,
            ];
        }

        return $out;
    }
}
