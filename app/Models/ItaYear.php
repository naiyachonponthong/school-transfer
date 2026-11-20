<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** ปีงบประมาณของการเปิดเผยข้อมูล ITA / OIT */
class ItaYear extends Model
{
    protected $fillable = ['year', 'note', 'is_published'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItaItem::class)->orderBy('sort')->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'year';
    }

    /** ปีงบประมาณปัจจุบัน (เริ่ม 1 ตุลาคม) เป็น พ.ศ. */
    public static function fiscalYearNow(): int
    {
        $now = now();

        return $now->year + 543 + ($now->month >= 10 ? 1 : 0);
    }
}
