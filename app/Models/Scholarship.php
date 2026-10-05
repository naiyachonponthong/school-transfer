<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ทุนการศึกษาหนึ่งทุนของปีการศึกษา: ใครให้ มูลค่าเท่าไร กี่ทุน และมอบด้วยวิธีใด */
class Scholarship extends Model
{
    public const CATEGORIES = ['need' => 'ขาดแคลนทุนทรัพย์', 'merit' => 'เรียนดี', 'conduct' => 'ความประพฤติดี/จิตอาสา', 'talent' => 'ความสามารถพิเศษ', 'other' => 'อื่น ๆ'];

    public const MODES = ['discount' => 'ลดค่าธรรมเนียม', 'cash' => 'จ่ายเป็นเงิน/สิ่งของ'];

    protected $fillable = ['name', 'category', 'donor', 'year', 'mode', 'value_type', 'value', 'fee_item_id', 'slots', 'budget', 'conditions', 'opens_on', 'closes_on', 'is_open', 'created_by'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'budget' => 'decimal:2', 'opens_on' => DateOnly::class, 'closes_on' => DateOnly::class, 'is_open' => 'boolean'];
    }

    public function awards(): HasMany
    {
        return $this->hasMany(ScholarshipAward::class);
    }

    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }

    public function isPercent(): bool
    {
        return $this->mode === 'discount' && $this->value_type === 'percent';
    }

    public function valueLabel(): string
    {
        return $this->isPercent() ? Shop::percentLabel($this->value) : baht($this->value).' บาท';
    }

    /** มูลค่าเป็นบาทของหนึ่งทุน (ทุนที่ลดเป็นร้อยละไม่รู้ยอดจนกว่าจะออกใบแจ้งหนี้) */
    public function awardAmount(): ?float
    {
        return $this->isPercent() ? null : (float) $this->value;
    }

    /** รับการเสนอชื่ออยู่: เปิดไว้และอยู่ในช่วงวันที่กำหนด */
    public function acceptsNominations(): bool
    {
        return $this->is_open && (! $this->opens_on || $this->opens_on->lte(today())) && (! $this->closes_on || $this->closes_on->gte(today()));
    }

    public function approvedCount(): int
    {
        return $this->awards()->where('status', 'approved')->count();
    }

    public function approvedAmount(): float
    {
        return (float) $this->awards()->where('status', 'approved')->sum('amount');
    }

    /**
     * อนุมัติเพิ่มอีก $count ทุนได้หรือไม่ (จำนวนทุนและงบรวม) คืนข้อความเหตุผลถ้าไม่ได้
     */
    public function roomFor(int $count): ?string
    {
        if ($this->slots !== null && $this->approvedCount() + $count > $this->slots) {
            return "เกินจำนวนทุน (กำหนดไว้ {$this->slots} ทุน อนุมัติแล้ว {$this->approvedCount()} ทุน)";
        }
        $each = $this->awardAmount();
        if ($this->budget !== null && $each !== null && $this->approvedAmount() + $each * $count > (float) $this->budget + 0.001) {
            return 'เกินงบของทุน (งบ '.baht($this->budget).' บาท ใช้ไปแล้ว '.baht($this->approvedAmount()).' บาท)';
        }

        return null;
    }
}
