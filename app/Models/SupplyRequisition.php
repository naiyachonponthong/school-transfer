<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ใบเบิกวัสดุ */
class SupplyRequisition extends Model
{
    public const STATUSES = [
        'pending' => ['รอจ่าย', 'warning'],
        'issued' => ['จ่ายแล้ว', 'success'],
        'rejected' => ['ไม่อนุมัติ', 'danger'],
        'cancelled' => ['ยกเลิก', 'secondary'],
    ];

    protected $fillable = ['req_no', 'requester_id', 'department', 'purpose', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplyRequisitionItem::class, 'requisition_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** เลขที่ใบเบิกรายปี พ.ศ. เช่น S2569-0001 (เรียกภายใน transaction) */
    public static function nextNumber(): string
    {
        $prefix = 'S'.(today()->year + 543).'-';
        $last = self::where('req_no', 'like', $prefix.'%')->lockForUpdate()->max('req_no');

        return $prefix.str_pad((string) ((int) substr((string) $last, strlen($prefix)) + 1), 4, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }
}
