<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/** วัสดุสิ้นเปลือง (ยอดคงคลังเปลี่ยนผ่าน move() เท่านั้น เพื่อให้บัญชีวัสดุครบทุกรายการ) */
class Supply extends Model
{
    protected $fillable = ['code', 'name', 'unit', 'category', 'stock', 'min_stock', 'is_active', 'photo', 'unit_price', 'storage_location', 'description'];

    protected function casts(): array
    {
        return ['stock' => 'integer', 'min_stock' => 'integer', 'is_active' => 'boolean', 'unit_price' => 'float'];
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? asset('storage/'.$this->photo) : null;
    }

    /** มูลค่าคงคลัง */
    public function value(): float
    {
        return round($this->stock * $this->unit_price, 2);
    }

    /** ร้อยละของคงคลังเทียบ 3 เท่าของขั้นต่ำ (ใช้แสดงแถบระดับคงคลัง) */
    public function level(): int
    {
        $full = max(1, $this->min_stock * 3);

        return (int) min(100, round($this->stock / $full * 100));
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
