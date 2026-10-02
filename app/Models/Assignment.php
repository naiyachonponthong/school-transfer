<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    protected $fillable = ['course_id', 'assessment_id', 'title', 'description', 'attachment', 'due_at', 'max_score', 'created_by'];

    protected function casts(): array
    {
        return ['due_at' => 'datetime', 'max_score' => 'float'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment ? asset('storage/'.$this->attachment) : null;
    }

    public function isClosed(): bool
    {
        return $this->due_at && $this->due_at->isPast();
    }
}
