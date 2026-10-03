<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashClosing extends Model
{
    protected $fillable = ['date', 'cash', 'transfer', 'promptpay', 'receipts', 'note', 'closed_by'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class, 'cash' => 'float', 'transfer' => 'float', 'promptpay' => 'float'];
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
