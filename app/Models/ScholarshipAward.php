<?php

namespace App\Models;

use App\Support\Sequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** นักเรียนหนึ่งคนกับทุนหนึ่งทุน: ถูกเสนอชื่อ → อนุมัติ/สำรอง/ไม่อนุมัติ → มอบ (หรือถูกเพิกถอน) */
class ScholarshipAward extends Model
{
    public const STATUSES = [
        'nominated' => ['รอพิจารณา', 'warning'], 'approved' => ['ได้รับทุน', 'success'], 'reserve' => ['สำรอง', 'info'],
        'rejected' => ['ไม่ได้รับ', 'secondary'], 'revoked' => ['เพิกถอน', 'danger'],
    ];

    protected $fillable = ['scholarship_id', 'student_id', 'status', 'reason', 'nominated_by', 'decided_by', 'decided_at', 'decision_note',
        'amount', 'doc_no', 'paid_at', 'paid_by', 'received_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'decided_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function scholarship(): BelongsTo
    {
        return $this->belongsTo(Scholarship::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function nominator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'nominated_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /** ส่วนลดประจำตัวที่ระบบสร้างให้จากทุนนี้ (ทุนแบบลดค่าธรรมเนียม) */
    public function discount(): HasOne
    {
        return $this->hasOne(StudentDiscount::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public static function nextNumber(): string
    {
        $ym = now()->format('Ym');
        $seq = Sequence::next('SC', $ym, fn () => (int) substr((string) self::where('doc_no', 'like', "SC{$ym}%")->max('doc_no'), -4));

        return 'SC'.$ym.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
