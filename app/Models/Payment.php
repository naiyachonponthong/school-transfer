<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Support\Sequence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const METHODS = ['cash' => 'เงินสด', 'transfer' => 'โอนเงิน', 'promptpay' => 'พร้อมเพย์'];

    protected $fillable = ['receipt_no', 'invoice_id', 'amount', 'method', 'paid_at', 'note', 'received_by', 'voided_at', 'voided_by', 'void_reason'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'voided_at' => 'datetime', 'amount' => 'float'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** ใบเสร็จที่ยังมีผล (ใบที่ยกเลิกเก็บไว้เป็นหลักฐาน แต่ไม่นับยอด) */
    public function scopeValid(Builder $q): Builder
    {
        return $q->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }

    public static function nextNumber(): string
    {
        $ym = now()->format('Ym');
        $seq = Sequence::next('RC', $ym, fn () => (int) substr((string) self::where('receipt_no', 'like', "RC{$ym}%")->max('receipt_no'), -5));

        return 'RC'.$ym.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
