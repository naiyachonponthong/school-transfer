<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** การขายหนึ่งครั้งที่หน้าจอขาย */
class WalletSale extends Model
{
    protected $fillable = ['shop_id', 'wallet_id', 'total', 'items', 'cashier_id', 'client_key', 'voided_at', 'voided_by', 'void_reason', 'payment'];

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

    /** ผู้ซื้อ: เจ้าของกระเป๋า หรือลูกค้าที่สแกนจ่ายด้วย QR เอง */
    public function customerName(): string
    {
        return $this->wallet_id ? ($this->wallet?->ownerName() ?? '-') : 'ลูกค้าสแกนจ่าย QR';
    }

    public function customerSub(): ?string
    {
        return $this->wallet_id ? $this->wallet?->ownerSub() : null;
    }

    public function itemsLabel(): string
    {
        return collect($this->items)->map(fn ($i) => $i['name'].(($i['qty'] ?? 1) > 1 ? ' ×'.$i['qty'] : ''))->implode(', ');
    }
}
