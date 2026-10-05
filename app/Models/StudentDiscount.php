<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/** ส่วนลดประจำตัวนักเรียน: ทุน พี่น้อง บุตรบุคลากร */
class StudentDiscount extends Model
{
    public const TYPES = ['percent' => 'ร้อยละ', 'amount' => 'บาท'];

    protected $fillable = ['student_id', 'name', 'type', 'value', 'fee_item_id', 'year', 'is_active', 'created_by', 'scholarship_award_id'];

    protected function casts(): array
    {
        return ['value' => 'float', 'is_active' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }

    public function scopeApplicable(Builder $q, int $year): Builder
    {
        return $q->where('is_active', true)->where(fn ($w) => $w->whereNull('year')->orWhere('year', $year));
    }

    public function valueLabel(): string
    {
        return rtrim(rtrim(number_format($this->value, 2), '0'), '.').($this->type === 'percent' ? '%' : ' บาท');
    }

    /**
     * ยอดส่วนลดรวมของนักเรียนหนึ่งคนสำหรับรายการในแผน
     * ส่วนลดที่ผูกกับรายการ คิดจากยอดของรายการนั้น · ไม่ผูก คิดจากยอดรวม · รวมกันไม่เกินยอดรวม
     *
     * @param  Collection<int, StudentDiscount>  $discounts
     * @param  Collection<int, FeePlanItem>  $items
     * @return array{0: float, 1: string|null}
     */
    public static function calculate(Collection $discounts, Collection $items): array
    {
        $total = (float) $items->sum('amount');
        $sum = 0.0;
        $names = [];
        foreach ($discounts as $d) {
            $base = $d->fee_item_id ? (float) $items->where('fee_item_id', $d->fee_item_id)->sum('amount') : $total;
            if ($base <= 0) {
                continue;
            }
            $sum += $d->type === 'percent' ? round($base * min(100, $d->value) / 100, 2) : min($d->value, $base);
            $names[] = $d->name;
        }

        return [round(min($sum, $total), 2), $names ? implode(', ', $names) : null];
    }

}
