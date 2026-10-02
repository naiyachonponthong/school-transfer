<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedPost extends Model
{
    public const REACTIONS = ['👍', '❤️', '👏', '🎉'];

    public const TYPES = [
        'post' => 'ข่าวสาร',
        'achievement' => 'ประกาศเกียรติคุณ',
        'announcement' => 'ประกาศ',
    ];

    protected $fillable = [
        'type', 'author_id', 'student_id', 'headline', 'title', 'body', 'image', 'icon',
        'audience', 'classroom_id', 'announcement_id', 'created_at',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(FeedReaction::class);
    }

    /** กลุ่มเป้าหมายเดียวกับประกาศ: ผู้ปกครองเห็นเฉพาะทุกคน/ผู้ปกครอง/ห้องของลูก */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        if ($user->isStaff()) {
            return $q;
        }
        [$audiences, $classroomIds] = $user->isStudent()
            ? [['all'], array_filter([$user->studentProfile?->classroom_id])]
            : [['all', 'parents'], $user->children()->pluck('classroom_id')->filter()->all()];

        return $q->where(function ($q) use ($audiences, $classroomIds) {
            $q->whereIn('audience', $audiences)
                ->orWhere(fn ($q) => $q->where('audience', 'classroom')->whereIn('classroom_id', $classroomIds));
        });
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    /** ชื่อผู้ที่เป็นเจ้าของเรื่อง (นักเรียนหรือผู้โพสต์) */
    public function actorName(): string
    {
        return $this->student?->fullName() ?? $this->author?->name ?? school('school_short');
    }

    /** @return array<string, array{count:int, mine:bool}> */
    public function reactionSummary(?int $userId): array
    {
        $out = [];
        foreach ($this->reactions as $r) {
            $out[$r->emoji] ??= ['count' => 0, 'mine' => false];
            $out[$r->emoji]['count']++;
            $out[$r->emoji]['mine'] = $out[$r->emoji]['mine'] || $r->user_id === $userId;
        }

        return $out;
    }

    public function canDelete(User $user): bool
    {
        return $user->isAdmin() || $this->author_id === $user->id;
    }
}
