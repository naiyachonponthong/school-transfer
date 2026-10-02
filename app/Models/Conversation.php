<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['student_id', 'topic', 'created_by', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('last_read_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function scopeFor(Builder $q, User $user): Builder
    {
        return $q->whereHas('participants', fn ($p) => $p->whereKey($user->id));
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants->contains('id', $user->id);
    }

    /** คู่สนทนา (คนที่ไม่ใช่เรา) */
    public function others(User $me)
    {
        return $this->participants->where('id', '!=', $me->id)->values();
    }

    public function unreadFor(User $user): int
    {
        $read = $this->participants->firstWhere('id', $user->id)?->pivot->last_read_at;

        return $this->messages()->where('user_id', '!=', $user->id)
            ->when($read, fn ($q) => $q->where('created_at', '>', $read))->count();
    }

    /** จำนวนข้อความที่ยังไม่อ่านทั้งหมดของผู้ใช้ (ใช้แสดง badge) */
    public static function unreadTotal(User $user): int
    {
        return Message::query()
            ->join('conversation_user as cu', function ($j) use ($user) {
                $j->on('cu.conversation_id', '=', 'messages.conversation_id')->where('cu.user_id', $user->id);
            })
            ->where('messages.user_id', '!=', $user->id)
            ->where(fn ($q) => $q->whereNull('cu.last_read_at')->orWhereColumn('messages.created_at', '>', 'cu.last_read_at'))
            ->count();
    }
}
