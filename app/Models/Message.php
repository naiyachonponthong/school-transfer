<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'user_id', 'body', 'attachment', 'created_at', 'updated_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment ? route('files.show', ['chat', $this->id]) : null;
    }

    public function toChatArray(int $me): array
    {
        return [
            'id' => $this->id,
            'mine' => $this->user_id === $me,
            'name' => $this->user?->name,
            'initials' => $this->user?->initials(),
            'body' => $this->body,
            'image' => $this->attachmentUrl(),
            'time' => $this->created_at->format('H:i'),
            'date' => thai_date($this->created_at),
        ];
    }
}
