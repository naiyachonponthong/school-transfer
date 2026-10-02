<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ใบแจ้งซ่อม */
class RepairRequest extends Model
{
    public const STATUSES = [
        'pending' => ['รอรับเรื่อง', 'secondary'],
        'in_progress' => ['กำลังซ่อม', 'warning'],
        'external' => ['ส่งซ่อมภายนอก', 'info'],
        'done' => ['ซ่อมเสร็จ', 'success'],
        'unrepairable' => ['ซ่อมไม่ได้', 'danger'],
        'cancelled' => ['ยกเลิก', 'light'],
    ];

    /** สถานะที่ยังต้องดำเนินการ */
    public const OPEN = ['pending', 'in_progress', 'external'];

    public const PRIORITIES = ['normal' => 'ปกติ', 'urgent' => 'ด่วน'];

    protected $fillable = ['ticket_no', 'asset_id', 'location', 'title', 'detail', 'photo', 'priority', 'status',
        'reporter_id', 'assignee_id', 'cost', 'result_note', 'finished_at'];

    protected function casts(): array
    {
        return ['cost' => 'float', 'finished_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(RepairUpdate::class)->latest('id');
    }

    /** เลขที่ใบแจ้งซ่อมรายปี พ.ศ. เช่น R2569-0001 (เรียกภายใน transaction) */
    public static function nextTicket(): string
    {
        $prefix = 'R'.(today()->year + 543).'-';
        $last = self::where('ticket_no', 'like', $prefix.'%')->lockForUpdate()->max('ticket_no');

        return $prefix.str_pad((string) ((int) substr((string) $last, strlen($prefix)) + 1), 4, '0', STR_PAD_LEFT);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? asset('storage/'.$this->photo) : null;
    }
}
