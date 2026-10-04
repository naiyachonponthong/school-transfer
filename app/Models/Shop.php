<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ร้านค้าในโรงเรียนที่รับชำระด้วยกระเป๋าเงินนักเรียน */
class Shop extends Model
{
    protected $fillable = ['name', 'location', 'is_active', 'show_balance'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'show_balance' => 'boolean'];
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
