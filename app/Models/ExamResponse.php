<?php

namespace App\Models;

use App\Support\ExamTaker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** กระดาษคำตอบ 1 แผ่นที่สแกนแล้ว */
class ExamResponse extends Model
{
    public const STATUSES = ['ok' => ['ตรวจแล้ว', 'success'], 'review' => ['รอตรวจทาน', 'warning'], 'void' => ['ยกเลิก', 'secondary']];

    protected $fillable = ['exam_id', 'student_id', 'application_id', 'request_id', 'answers', 'score', 'max_score', 'status', 'flags', 'confidence',
        'code_read', 'seat_read', 'image', 'source', 'key_version', 'scanned_by', 'scanned_at', 'reviewed_by', 'reviewed_at', 'edits'];

    protected function casts(): array
    {
        return [
            'flags' => 'array', 'edits' => 'array', 'score' => 'float', 'max_score' => 'float', 'confidence' => 'float',
            'scanned_at' => 'datetime', 'reviewed_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** ผู้สมัคร (ชุดข้อสอบคัดเลือก) */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Admission::class, 'application_id');
    }

    public function takerId(): ?int
    {
        return $this->application_id ?? $this->student_id;
    }

    public function taker(): ?ExamTaker
    {
        if ($this->application_id) {
            return $this->application ? ExamTaker::fromApplication($this->application) : null;
        }

        return $this->student ? ExamTaker::fromStudent($this->student) : null;
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function reasons(): array
    {
        return Exam::reasons($this->flags ?? []);
    }

    /** เก็บประวัติการแก้ (ไม่เกิน 30 รายการล่าสุด) */
    public function logEdit(User $user, string $what): void
    {
        $edits = $this->edits ?? [];
        $edits[] = ['by' => $user->name, 'at' => now()->toDateTimeString(), 'what' => $what];
        $this->edits = array_slice($edits, -30);
    }
}
