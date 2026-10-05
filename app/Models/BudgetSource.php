<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** แหล่งเงินของปีงบประมาณ เช่น เงินอุดหนุนรายหัว เงินรายได้สถานศึกษา */
class BudgetSource extends Model
{
    protected $fillable = ['fiscal_year', 'name', 'amount', 'note'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(ActivityBudget::class);
    }

    /** ชื่อประเภทเงินที่โรงเรียนใช้ทั่วไป (ปุ่มสร้างชุดมาตรฐานในหน้างบประมาณ) */
    public const STANDARD = ['เงินอุดหนุน', 'เงินกิจกรรมพัฒนาผู้เรียน', 'เงินบำรุงการศึกษา', 'เงินรายได้สถานศึกษา'];

    /** จัดสรรให้กิจกรรมไปแล้วเท่าไร ($except = บรรทัดงบที่กำลังแก้ ไม่นับตัวเอง) */
    public function allocated(?int $except = null): float
    {
        return (float) $this->budgets()->when($except, fn ($q) => $q->where('id', '!=', $except))->sum('amount');
    }
}
