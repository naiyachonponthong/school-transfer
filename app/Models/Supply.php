<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** วัสดุสิ้นเปลือง (ยอดคงคลังเปลี่ยนผ่าน move() เท่านั้น เพื่อให้บัญชีวัสดุครบทุกรายการ) */
class Supply extends Model
{
    protected $fillable = ['name', 'unit', 'category', 'stock', 'min_stock', 'is_active'];

    protected function casts(): array
    {
        return ['stock' => 'integer', 'min_stock' => 'integer', 'is_active' => 'boolean'];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SupplyTransaction::class)->latest('id');
    }

    public function isLow(): bool
    {
        return $this->min_stock > 0 && $this->stock <= $this->min_stock;
    }

    /** รับเข้า (+) / จ่ายออก (−) / ปรับยอด แล้วบันทึกบัญชีวัสดุพร้อมยอดคงเหลือ */
    public function move(string $type, int $quantity, ?string $note = null, ?int $requisitionId = null): SupplyTransaction
    {
        return DB::transaction(function () use ($type, $quantity, $note, $requisitionId) {
            $fresh = self::lockForUpdate()->find($this->id);
            $fresh->stock += $quantity;
            $fresh->save();
            $this->stock = $fresh->stock;

            return $this->transactions()->create([
                'type' => $type, 'quantity' => $quantity, 'balance' => $fresh->stock,
                'requisition_id' => $requisitionId, 'user_id' => auth()->id(), 'note' => $note,
            ]);
        });
    }
}
