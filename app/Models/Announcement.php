<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Announcement extends Model
{
    public const AUDIENCES = [
        'all' => 'ทุกคน',
        'parents' => 'ผู้ปกครองทั้งหมด',
        'staff' => 'ครูและบุคลากร',
        'classroom' => 'เฉพาะห้องเรียน',
    ];

    protected $fillable = ['title', 'body', 'audience', 'classroom_id', 'pinned', 'author_id'];

    protected function casts(): array
    {
        return ['pinned' => 'boolean'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function readers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'announcement_reads')->withPivot('read_at');
    }

    /** ประกาศที่ผู้ใช้คนนี้มีสิทธิ์เห็น */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->isStaff()) {
            return $user->isAdmin() ? $q : $q->whereIn('audience', ['all', 'staff', 'classroom']);
        }

        // นักเรียน: ประกาศทั่วไป + ห้องตัวเอง (ไม่เห็นประกาศที่ส่งถึงผู้ปกครองโดยเฉพาะ)
        [$audiences, $classroomIds] = $user->isStudent()
            ? [['all'], array_filter([$user->studentProfile?->classroom_id])]
            : [['all', 'parents'], $user->children()->pluck('classroom_id')->filter()->all()];

        return $q->where(function ($q) use ($audiences, $classroomIds) {
            $q->whereIn('audience', $audiences)
                ->orWhere(fn ($q) => $q->where('audience', 'classroom')->whereIn('classroom_id', $classroomIds));
        });
    }

    public function audienceLabel(): string
    {
        if ($this->audience === 'classroom' && $this->classroom) {
            return 'ห้อง '.$this->classroom->name();
        }

        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }
}
