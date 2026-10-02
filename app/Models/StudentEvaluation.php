<?php

namespace App\Models;

use App\Support\Evaluation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ผลประเมินคุณลักษณะอันพึงประสงค์ + อ่าน คิดวิเคราะห์ และเขียน ของนักเรียนหนึ่งคนในภาคเรียนหนึ่ง */
class StudentEvaluation extends Model
{
    protected $fillable = ['term_id', 'student_id', 'traits', 'rtw', 'recorded_by'];

    protected function casts(): array
    {
        return ['traits' => 'array', 'rtw' => 'integer'];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function trait(int $no): ?int
    {
        $v = $this->traits[$no] ?? $this->traits[(string) $no] ?? null;

        return $v === null ? null : (int) $v;
    }

    /** ผลสรุปคุณลักษณะ (null = ยังประเมินไม่ครบ 8 ข้อ) */
    public function traitsSummary(): ?int
    {
        return Evaluation::summarize($this->traits ?? []);
    }
}
