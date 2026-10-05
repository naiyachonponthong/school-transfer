<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashClosing extends Model
{
    protected $fillable = ['date', 'cash', 'transfer', 'promptpay', 'receipts', 'note', 'closed_by', 'wallet_cash_in', 'wallet_cash_out', 'shop_cash_out'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class, 'cash' => 'float', 'transfer' => 'float', 'promptpay' => 'float',
            'wallet_cash_in' => 'float', 'wallet_cash_out' => 'float', 'shop_cash_out' => 'float'];
    }

    /** เงินสดที่ต้องนำส่ง: ค่าธรรมเนียมที่รับเป็นเงินสด + เงินสดเติมกระเป๋า − เงินสดที่จ่ายออก (ถอนคืน จ่ายร้านค้า) */
    public function cashToRemit(): float
    {
        return round($this->cash + $this->wallet_cash_in - $this->wallet_cash_out - $this->shop_cash_out, 2);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function total(): float
    {
        return $this->cash + $this->transfer + $this->promptpay;
    }

    public static function isClosed($date): bool
    {
        return self::whereDate('date', $date)->exists();
    }

}
