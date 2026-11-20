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

    protected $fillable = ['category', 'title', 'excerpt', 'tags', 'body', 'is_html', 'cover', 'images', 'attachments', 'pinned', 'urgent', 'published_at', 'announcement_id', 'author_id'];

    protected function casts(): array
    {
        return ['images' => 'array', 'attachments' => 'array', 'tags' => 'array', 'pinned' => 'boolean', 'urgent' => 'boolean', 'is_html' => 'boolean', 'published_at' => 'datetime'];
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

    /** สรุปสั้น: ใช้ที่กรอกไว้ ถ้าไม่กรอกตัดจากเนื้อหา */
    public function excerpt(int $limit = 140): string
    {
        return Str::limit(trim((string) $this->excerpt) !== '' ? trim($this->excerpt) : $this->plainText(), $limit);
    }

    /** เนื้อหาแบบข้อความล้วน (ตัดแท็ก HTML) */
    public function plainText(): string
    {
        $text = $this->is_html ? html_entity_decode(strip_tags(preg_replace('~<(br|/p|/h[2-4]|/li|/div)[^>]*>~i', ' ', (string) $this->body)), ENT_QUOTES | ENT_HTML5, 'UTF-8') : (string) $this->body;

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    /** เนื้อหาพร้อมแสดงบนเว็บ (HTML ถูกกรองตอนบันทึกแล้ว ข่าวเก่าแปลงข้อความเป็น HTML) */
    public function bodyHtml(): string
    {
        return $this->is_html ? (string) $this->body : \App\Support\Website::text($this->body);
    }

    public function scopeWithTag(Builder $q, string $tag): Builder
    {
        return $q->whereJsonContains('tags', $tag);
    }

    /** แท็กที่เคยใช้ทั้งหมด เรียงตามจำนวนข่าว ['แท็ก' => จำนวน] */
    public static function usedTags(): array
    {
        $count = [];
        foreach (self::whereNotNull('tags')->pluck('tags') as $tags) {
            foreach ((array) $tags as $t) {
                $count[$t] = ($count[$t] ?? 0) + 1;
            }
        }
        arsort($count);

        return $count;
    }
}
