<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ร้านค้าในโรงเรียนที่รับชำระด้วยกระเป๋าเงินนักเรียน */
class Shop extends Model
{
    protected $fillable = ['name', 'location', 'is_active', 'show_balance', 'fee_percent', 'payout_account'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'show_balance' => 'boolean', 'fee_percent' => 'decimal:2'];
    }

    /** ร้อยละแบบไม่มีศูนย์ต่อท้าย เช่น 5% หรือ 2.5% */
    public static function percentLabel(mixed $percent): string
    {
        return rtrim(rtrim(number_format((float) $percent, 2, '.', ''), '0'), '.').'%';
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(ShopSettlement::class);
    }

    /**
     * ยอดขายที่โรงเรียนยังไม่ได้จ่ายให้ร้าน: ตัดจากกระเป๋าแล้ว ไม่ถูกยกเลิก และยังไม่อยู่ในใบจ่ายเงินใด
     * (รายการที่ลูกค้าโอนพร้อมเพย์เข้าบัญชีโดยตรงในระบบรุ่นก่อนไม่ผ่านมือโรงเรียน จึงไม่นับ)
     */
    public function unsettledSales(): HasMany
    {
        return $this->sales()->whereNotNull('wallet_id')->whereNull('voided_at')->whereNull('settlement_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(ShopProduct::class)->orderBy('sort')->orderBy('name');
    }

    public function cashiers(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(WalletSale::class);
    }

    /** ขายที่ร้านนี้ได้: ผู้จัดการกระเป๋าเงินขายได้ทุกร้าน คนขายขายได้เฉพาะร้านที่ถูกกำหนด */
    public function canBeUsedBy(User $user): bool
    {
        return $user->hasPermission('wallet.manage')
            || ($user->hasPermission('pos.use') && $this->cashiers()->whereKey($user->id)->exists());
    }
}
