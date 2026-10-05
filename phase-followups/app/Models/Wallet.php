<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * กระเป๋าเงินของนักเรียน หรือของครู/บุคลากร (อย่างใดอย่างหนึ่ง)
 * ยอดคงเหลือเปลี่ยนผ่าน WalletService เท่านั้น
 */
class Wallet extends Model
{
    protected $fillable = ['student_id', 'user_id', 'balance', 'daily_limit', 'is_frozen', 'low_notified_on', 'blocked_categories'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2', 'daily_limit' => 'decimal:2', 'is_frozen' => 'boolean', 'blocked_categories' => 'array', 'low_notified_on' => DateOnly::class];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** เจ้าของกระเป๋าที่เป็นครู/บุคลากร */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function owner(): Student|User|null
    {
        return $this->student_id ? $this->student : $this->user;
    }

    public function isStaff(): bool
    {
        return $this->user_id !== null;
    }

    public function ownerName(): string
    {
        return $this->student_id ? ($this->student?->fullName() ?? '-') : ($this->user?->name ?? '-');
    }

    /** ห้องของนักเรียน หรือตำแหน่งของบุคลากร */
    public function ownerSub(): ?string
    {
        return $this->student_id ? $this->student?->classroom?->name() : ($this->user?->position ?: 'ครู/บุคลากร');
    }

    /** หน้ากระเป๋าของเจ้าของคนนี้ในมุมของผู้จัดการกระเป๋าเงิน */
    public function adminUrl(): string
    {
        return $this->student_id ? route('wallets.student', $this->student_id) : route('wallets.staff', $this->user_id);
    }

    /** มีเงินหรือเคยมีรายการแล้ว: ลบเจ้าของกระเป๋าไม่ได้ (กระเป๋าเปล่าที่ระบบสร้างไว้ตอนสแกนบัตรลบได้) */
    public function hasHistory(): bool
    {
        return (float) $this->balance != 0.0 || $this->transactions()->exists() || $this->topups()->exists() || $this->sales()->exists();
    }

    /** กระเป๋าที่ยังมีเงินของนักเรียนที่จบ/ย้าย/พ้นสภาพ หรือบุคลากรที่ปิดบัญชีแล้ว: ต้องคืนเงินให้เจ้าของ */
    public function scopeOfLeavers(Builder $q): Builder
    {
        return $q->where('balance', '>', 0)->where(fn (Builder $w) => $w
            ->whereHas('student', fn (Builder $s) => $s->where('status', '!=', 'active'))
            ->orWhereHas('user', fn (Builder $u) => $u->where('is_active', false)));
    }

    /** ยอดซื้อของวันนี้ (ไม่นับรายการที่ยกเลิก) */
    public function spentToday(): float
    {
        return (float) $this->sales()->whereNull('voided_at')->where('created_at', '>=', today())->sum('total');
    }
}
