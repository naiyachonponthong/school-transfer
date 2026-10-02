<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    protected $fillable = ['year', 'term', 'start_date', 'end_date', 'is_current'];

    protected function casts(): array
    {
        return [
            'start_date' => DateOnly::class,
            'end_date' => DateOnly::class,
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

    public function label(): string
    {
        return "ภาคเรียนที่ {$this->term}/{$this->year}";
    }

    public function shortLabel(): string
    {
        return "{$this->term}/{$this->year}";
    }
}
