<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const METHODS = ['cash' => 'เงินสด', 'transfer' => 'โอนเงิน', 'promptpay' => 'พร้อมเพย์'];

    protected $fillable = ['receipt_no', 'invoice_id', 'amount', 'method', 'paid_at', 'note', 'received_by'];

    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount' => 'float'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }

    public static function nextNumber(): string
    {
        $ym = now()->format('Ym');
        $last = self::where('receipt_no', 'like', "RC{$ym}%")->orderByDesc('receipt_no')->value('receipt_no');
        $seq = $last ? ((int) substr($last, -5)) + 1 : 1;

        return 'RC'.$ym.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }
}
