<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    public const STATUSES = [
        'pending' => ['รออนุมัติ', 'warning'],
        'approved' => ['อนุมัติแล้ว', 'success'],
        'rejected' => ['ไม่อนุมัติ', 'danger'],
        'cancelled' => ['ยกเลิก', 'secondary'],
    ];

    /** สถานะที่กันเวลาไว้ (ห้ามจองซ้อน) */
    public const HOLDING = ['pending', 'approved'];

    protected $fillable = ['resource_id', 'user_id', 'title', 'starts_at', 'ends_at', 'attendees', 'destination', 'note',
        'status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(BookableResource::class, 'resource_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** ช่วงเวลาที่ทับกับ [start, end) — ชนกันเมื่อ เริ่มเดิม < จบใหม่ และ จบเดิม > เริ่มใหม่ */
    public function scopeOverlapping(Builder $q, int $resourceId, $start, $end): Builder
    {
        return $q->where('resource_id', $resourceId)->whereIn('status', self::HOLDING)
            ->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function timeRange(): string
    {
        $sameDay = $this->starts_at->isSameDay($this->ends_at);

        return $this->starts_at->format('H:i').'–'.($sameDay ? $this->ends_at->format('H:i') : thai_date($this->ends_at).' '.$this->ends_at->format('H:i')).' น.';
    }
}
