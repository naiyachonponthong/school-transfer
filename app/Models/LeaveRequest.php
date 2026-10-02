<?php

namespace App\Models;

use App\Casts\DateOnly;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    public const TYPES = ['sick' => 'ลาป่วย', 'personal' => 'ลากิจ'];

    public const STATUSES = [
        'pending' => ['รออนุมัติ', 'warning'],
        'approved' => ['อนุมัติแล้ว', 'success'],
        'rejected' => ['ไม่อนุมัติ', 'danger'],
    ];

    protected $fillable = [
        'student_id', 'requested_by', 'type', 'start_date', 'end_date', 'reason', 'attachment',
        'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => DateOnly::class,
            'end_date' => DateOnly::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function days(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /** อนุมัติแล้วลงเช็คชื่อให้อัตโนมัติทุกวันทำการในช่วงลา */
    public function approve(User $reviewer, ?string $note = null): void
    {
        $this->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        $status = $this->type === 'sick' ? 'sick' : 'leave';
        foreach (CarbonPeriod::create($this->start_date, $this->end_date) as $day) {
            if ($day->isWeekend()) {
                continue;
            }
            Attendance::updateOrCreate(
                ['student_id' => $this->student_id, 'date' => $day->toDateString()],
                [
                    'classroom_id' => $this->student->classroom_id,
                    'status' => $status,
                    'note' => 'ใบลา #'.$this->id,
                    'recorded_by' => $reviewer->id,
                ]
            );
        }
    }
}
