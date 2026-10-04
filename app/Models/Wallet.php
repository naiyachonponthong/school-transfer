<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** กระเป๋าเงินของนักเรียน ยอดคงเหลือเปลี่ยนผ่าน WalletService เท่านั้น */
class Wallet extends Model
{
    protected $fillable = ['student_id', 'balance', 'daily_limit', 'is_frozen', 'low_notified_on'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2', 'daily_limit' => 'decimal:2', 'is_frozen' => 'boolean', 'low_notified_on' => DateOnly::class];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(WalletSale::class);
    }

    public function topups(): HasMany
    {
        return $this->hasMany(WalletTopup::class);
    }

    /** ยอดซื้อของวันนี้ (ไม่นับรายการที่ยกเลิก) */
    public function spentToday(): float
    {
        return (float) $this->sales()->whereNull('voided_at')->where('created_at', '>=', today())->sum('total');
    }
}
