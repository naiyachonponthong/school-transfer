<?php

namespace App\Models;

use App\Casts\DateOnly;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffLeave extends Model
{
    /** ประเภทการลา => [ชื่อ, สิทธิ์ต่อปีงบประมาณ (วันทำการ) โดยประมาณ, null = ไม่จำกัด] */
    public const TYPES = [
        'sick' => ['ลาป่วย', 60],
        'personal' => ['ลากิจส่วนตัว', 45],
        'vacation' => ['ลาพักผ่อน', 10],
        'duty' => ['ไปราชการ', null],
        'maternity' => ['ลาคลอดบุตร', 90],
    ];

    protected $fillable = ['user_id', 'type', 'start_date', 'end_date', 'reason', 'attachment', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['start_date' => DateOnly::class, 'end_date' => DateOnly::class, 'reviewed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return LeaveRequest::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return LeaveRequest::STATUSES[$this->status][1] ?? 'secondary';
    }

    /** จำนวนวันทำการ (ไม่นับเสาร์-อาทิตย์) */
    public function days(): int
    {
        return collect(CarbonPeriod::create($this->start_date, $this->end_date))->filter(fn ($d) => $d->isWeekday())->count();
    }

    /** อนุมัติแล้วลงเวลาปฏิบัติงานเป็น "ลา/ไปราชการ" ให้อัตโนมัติ */
    public function approve(User $reviewer, ?string $note = null): void
    {
        $this->update(['status' => 'approved', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now(), 'review_note' => $note]);
        foreach (CarbonPeriod::create($this->start_date, $this->end_date) as $day) {
            if ($day->isWeekday()) {
                StaffAttendance::updateOrCreate(
                    ['user_id' => $this->user_id, 'date' => $day->toDateString()],
                    ['status' => $this->type === 'duty' ? 'duty' : 'leave', 'note' => $this->typeLabel()]
                );
            }
        }
    }

    /** ปีงบประมาณไทย (1 ต.ค. - 30 ก.ย.) ที่วันนี้อยู่ */
    public static function fiscalRange(): array
    {
        $start = today()->month >= 10 ? today()->setDate(today()->year, 10, 1) : today()->setDate(today()->year - 1, 10, 1);

        return [$start->toDateString(), $start->copy()->addYear()->subDay()->toDateString()];
    }
}
