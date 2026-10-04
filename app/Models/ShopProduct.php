<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopProduct extends Model
{
    protected $fillable = ['shop_id', 'name', 'category', 'price', 'is_active', 'sort', 'image', 'description', 'barcode', 'unit', 'cost', 'stock'];

    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'cost' => 'decimal:2', 'is_active' => 'boolean', 'stock' => 'integer'];
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    /** นับสต็อกและหมดแล้ว */
    public function soldOut(): bool
    {
        return $this->stock !== null && $this->stock <= 0;
    }
}
