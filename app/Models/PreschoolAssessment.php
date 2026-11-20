<?php

namespace App\Models;

use App\Support\Preschool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ผลประเมินพัฒนาการปฐมวัย 12 มาตรฐาน ของนักเรียนหนึ่งคนในภาคเรียนหนึ่ง */
class PreschoolAssessment extends Model
{
    protected $fillable = ['term_id', 'student_id', 'levels', 'comment', 'recorded_by'];

    protected function casts(): array
    {
        return ['levels' => 'array'];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function level(int $no): ?int
    {
        $v = $this->levels[$no] ?? $this->levels[(string) $no] ?? null;

        return $v === null ? null : (int) $v;
    }

    public function domains(): array
    {
        return Preschool::domainSummary($this->levels ?? []);
    }
}
