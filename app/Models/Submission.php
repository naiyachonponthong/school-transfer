<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Submission extends Model
{
    protected $fillable = ['assignment_id', 'student_id', 'text', 'file', 'submitted_at', 'channel', 'score', 'feedback', 'graded_at', 'submitted_by'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'graded_at' => 'datetime', 'score' => 'float'];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function fileUrl(): ?string
    {
        return $this->file ? route('files.show', ['submission', $this->id]) : null;
    }

    public function isLate(): bool
    {
        $due = $this->assignment->due_at;

        return $due && $this->submitted_at && $this->submitted_at->gt($due);
    }

    /** [ป้าย, สี] */
    public function state(): array
    {
        return match (true) {
            $this->score !== null => ['ตรวจแล้ว', 'success'],
            (bool) $this->submitted_at => [$this->isLate() ? 'ส่งช้า' : 'ส่งแล้ว', $this->isLate() ? 'warning' : 'primary'],
            default => ['ยังไม่ส่ง', 'secondary'],
        };
    }
}
