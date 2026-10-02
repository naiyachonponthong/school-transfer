<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentSlip extends Model
{
    public const STATUSES = [
        'pending' => ['รอตรวจสอบ', 'warning'],
        'approved' => ['ยืนยันแล้ว', 'success'],
        'rejected' => ['ไม่ผ่าน', 'danger'],
    ];

    protected $fillable = ['invoice_id', 'amount', 'image', 'transferred_at', 'note', 'status', 'uploaded_by', 'reviewed_by', 'reviewed_at', 'review_note', 'payment_id'];

    protected function casts(): array
    {
        return ['amount' => 'float', 'transferred_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function imageUrl(): string
    {
        return asset('storage/'.$this->image);
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
