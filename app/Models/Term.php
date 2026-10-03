<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    protected $fillable = ['year', 'term', 'start_date', 'end_date', 'is_current', 'results_announce_on'];

    protected function casts(): array
    {
        return [
            'start_date' => DateOnly::class,
            'end_date' => DateOnly::class,
            'results_announce_on' => DateOnly::class,
            'is_current' => 'boolean',
        ];
    }

    private static ?Term $current = null;

    public static function current(): ?Term
    {
        return self::$current ??= self::where('is_current', true)->first()
            ?? self::orderByDesc('year')->orderByDesc('term')->first();
    }

    public static function flushCurrent(): void
    {
        self::$current = null;
    }

    public function makeCurrent(): void
    {
        self::query()->update(['is_current' => false]);
        $this->update(['is_current' => true]);
        self::flushCurrent();
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /** ผู้ปกครอง/นักเรียนเห็นผลการเรียนของภาคนี้ได้ตั้งแต่วันประกาศผล (ไม่ตั้งวัน = เห็นได้ตลอด) บุคลากรเห็นเสมอ */
    public function resultsVisibleTo(User $user): bool
    {
        return $user->isStaff() || $this->results_announce_on === null || $this->results_announce_on->lte(today());
    }

    public function label(): string
    {
        return "ภาคเรียนที่ {$this->term}/{$this->year}";
    }

    public function shortLabel(): string
    {
        return "{$this->term}/{$this->year}";
    }
}
