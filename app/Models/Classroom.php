<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classroom extends Model
{
    /** ลำดับชั้นเรียน ใช้เรียงห้องให้ถูก (อ.1 → ม.6) */
    public const LEVELS = [
        'อ.1', 'อ.2', 'อ.3',
        'ป.1', 'ป.2', 'ป.3', 'ป.4', 'ป.5', 'ป.6',
        'ม.1', 'ม.2', 'ม.3', 'ม.4', 'ม.5', 'ม.6',
    ];

    protected $fillable = ['year', 'level', 'room', 'level_order', 'homeroom_teacher_id', 'co_teacher_id'];

    protected static function booted(): void
    {
        static::saving(function (Classroom $c) {
            $idx = array_search($c->level, self::LEVELS, true);
            $c->level_order = $idx === false ? 99 : $idx;
        });
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('level_order')->orderBy('room');
    }

    public function scopeCurrentYear(Builder $q): Builder
    {
        $year = Term::current()?->year;

        return $year ? $q->where('year', $year) : $q;
    }

    public function name(): string
    {
        return "{$this->level}/{$this->room}";
    }

    public function getNameAttribute(): string
    {
        return $this->name();
    }

    public function homeroomTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'homeroom_teacher_id');
    }

    public function coTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'co_teacher_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class)->where('status', 'active')->orderBy('number')->orderBy('student_code');
    }

    public function allStudents(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function isManagedBy(User $user): bool
    {
        return $user->isAdmin() || in_array($user->id, [$this->homeroom_teacher_id, $this->co_teacher_id], true);
    }
}
