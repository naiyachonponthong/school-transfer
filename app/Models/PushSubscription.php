<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** อุปกรณ์หนึ่งเครื่องที่ผู้ใช้เปิดรับแจ้งเตือนแบบ Web Push */
class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint', 'endpoint_hash', 'user_agent', 'last_used_at'];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
