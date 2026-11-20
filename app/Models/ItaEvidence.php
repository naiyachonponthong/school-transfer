<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** หลักฐานของหัวข้อ OIT: ไฟล์ ลิงก์ หรือข่าวบนเว็บไซต์ */
class ItaEvidence extends Model
{
    public const KINDS = ['file' => 'ไฟล์', 'link' => 'ลิงก์', 'post' => 'ข่าวบนเว็บไซต์'];

    protected $table = 'ita_evidences';

    protected $fillable = ['ita_item_id', 'title', 'kind', 'path', 'url', 'web_post_id', 'sort'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItaItem::class, 'ita_item_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(WebPost::class, 'web_post_id');
    }

    /** ลิงก์ที่เปิดหลักฐานได้ (null = ข่าวถูกลบไปแล้ว) */
    public function href(): ?string
    {
        return match ($this->kind) {
            'file' => $this->path ? asset('storage/'.$this->path) : null,
            'post' => $this->post ? route('web.post', $this->post) : null,
            default => $this->url,
        };
    }
}
