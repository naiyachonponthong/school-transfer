<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** การขายหนึ่งครั้งที่หน้าจอขาย */
class WalletSale extends Model
{
    protected $fillable = ['shop_id', 'wallet_id', 'total', 'items', 'cashier_id', 'client_key', 'voided_at', 'voided_by', 'void_reason'];

    protected function casts(): array
    {
        return ['total' => 'decimal:2', 'items' => 'array', 'voided_at' => 'datetime'];
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function itemsLabel(): string
    {
        return collect($this->items)->map(fn ($i) => $i['name'].(($i['qty'] ?? 1) > 1 ? ' ×'.$i['qty'] : ''))->implode(', ');
    }
}
