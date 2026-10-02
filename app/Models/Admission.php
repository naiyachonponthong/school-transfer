<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** ใบสมัครเข้าเรียน (รวมร่างที่ผู้ปกครองกรอกค้างไว้) */
class Admission extends Model
{
    protected $table = 'applications';

    public const STATUSES = [
        'draft' => ['กรอกค้างไว้', 'light'],
        'submitted' => ['ส่งใบสมัครแล้ว', 'secondary'],
        'reviewing' => ['กำลังตรวจสอบ', 'info'],
        'accepted' => ['ผ่านการคัดเลือก', 'success'],
        'reserve' => ['สำรอง', 'warning'],
        'rejected' => ['ไม่ผ่าน', 'danger'],
        'enrolled' => ['มอบตัวแล้ว', 'primary'],
    ];

    public const FEE_STATUSES = [
        'none' => ['ไม่มีค่าสมัคร', 'light'],
        'unpaid' => ['ยังไม่ชำระค่าสมัคร', 'warning'],
        'pending' => ['ส่งสลิปแล้ว รอตรวจ', 'info'],
        'paid' => ['ชำระค่าสมัครแล้ว', 'success'],
    ];

    protected $fillable = [
        'app_no', 'year', 'level', 'prefix', 'first_name', 'last_name', 'nickname', 'gender', 'birthdate', 'citizen_id',
        'previous_school', 'gpa', 'parent_name', 'parent_phone', 'relation', 'address', 'document', 'note', 'answers', 'steps_done',
        'submitted_at', 'fee_amount', 'fee_status', 'fee_slip', 'fee_note', 'fee_receipt_no', 'fee_paid_at', 'fee_verified_by',
        'exam_room', 'exam_seat', 'exam_no', 'exam_total', 'exam_rank', 'reserve_no', 'status', 'staff_note', 'student_id',
    ];

    protected function casts(): array
    {
        return [
            'birthdate' => DateOnly::class, 'gpa' => 'float', 'answers' => 'array', 'steps_done' => 'array',
            'submitted_at' => 'datetime', 'fee_amount' => 'float', 'fee_paid_at' => 'datetime',
            'exam_total' => 'float', 'exam_rank' => 'integer', 'reserve_no' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** รอบสอบคัดเลือกของชั้นนี้ (ถ้ามี) */
    public function round(): ?AdmissionRound
    {
        return AdmissionRound::where('year', $this->year)->where('level', $this->level)->first();
    }

    public function statusLabel(): string
    {
        $label = self::STATUSES[$this->status][0] ?? $this->status;

        return $this->status === 'reserve' && $this->reserve_no ? "{$label} ลำดับที่ {$this->reserve_no}" : $label;
    }

    public function feeVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fee_verified_by');
    }

    /** ใบสมัครที่ส่งแล้ว (ไม่นับร่าง) */
    public function scopeSubmitted(Builder $q): Builder
    {
        return $q->where('status', '!=', 'draft');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function fullName(): string
    {
        return trim("{$this->prefix}{$this->first_name} {$this->last_name}") ?: 'ยังไม่ระบุชื่อ';
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function feeLabel(): string
    {
        return self::FEE_STATUSES[$this->fee_status][0] ?? $this->fee_status;
    }

    public function feeColor(): string
    {
        return self::FEE_STATUSES[$this->fee_status][1] ?? 'secondary';
    }

    /** ต้องชำระ/แนบสลิปอยู่ */
    public function feeDue(): bool
    {
        return in_array($this->fee_status, ['unpaid', 'pending'], true) && $this->fee_amount > 0;
    }

    /** คำตอบของคำถามที่โรงเรียนเพิ่มเอง ตาม id */
    public function answer(string $id): ?array
    {
        return collect($this->answers ?? [])->firstWhere('id', $id);
    }

    /** รูปถ่ายผู้สมัคร (คำถามแนบไฟล์ที่ตั้งเป็น "รูปถ่ายในเอกสาร") */
    public function photoAnswer(): ?array
    {
        return collect($this->answers ?? [])->first(fn ($a) => $a['type'] === 'file' && ! empty($a['photo'])
            && preg_match('/\.(jpe?g|png)$/i', $a['value']['path'] ?? ''));
    }

    public static function nextNumber(int $year): string
    {
        $last = self::where('app_no', 'like', "A{$year}%")->orderByDesc('app_no')->value('app_no');

        return 'A'.$year.str_pad((string) ($last ? ((int) substr($last, -4)) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }

    public static function nextReceiptNumber(int $year): string
    {
        $prefix = "RA{$year}-";
        $last = self::where('fee_receipt_no', 'like', $prefix.'%')->orderByDesc('fee_receipt_no')->value('fee_receipt_no');

        return $prefix.str_pad((string) ($last ? ((int) substr($last, strlen($prefix))) + 1 : 1), 4, '0', STR_PAD_LEFT);
    }
}
