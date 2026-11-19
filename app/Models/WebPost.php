<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** ข่าวบนเว็บไซต์โรงเรียน */
class WebPost extends Model
{
    /** key => [ชื่อ, ไอคอน] */
    public const CATEGORIES = [
        'news' => ['ข่าวประชาสัมพันธ์', 'megaphone'],
        'activity' => ['ข่าวกิจกรรม', 'camera'],
        'procurement' => ['ข่าวจัดซื้อจัดจ้าง', 'file-earmark-text'],
        'admission' => ['ข่าวรับสมัคร', 'person-plus'],
    ];

    protected $fillable = ['category', 'title', 'body', 'cover', 'images', 'attachments', 'pinned', 'published_at', 'announcement_id', 'author_id'];

    protected function casts(): array
    {
        return ['images' => 'array', 'attachments' => 'array', 'pinned' => 'boolean', 'published_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** เผยแพร่แล้ว (ไม่ใช่ร่าง และถึงเวลาที่ตั้งไว้) */
    public function scopePublished(Builder $q): Builder
    {
        return $q->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeLatestFirst(Builder $q): Builder
    {
        return $q->orderByDesc('pinned')->orderByDesc('published_at')->orderByDesc('id');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->published_at->lte(now());
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category][0] ?? $this->category;
    }

    public function coverUrl(): ?string
    {
        $path = $this->cover ?: ($this->images[0] ?? null);

        return $path ? asset('storage/'.(string) $path) : null;
    }

    public function excerpt(int $limit = 140): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $this->body)), $limit);
    }
}
