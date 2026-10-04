<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ข้อความแจ้งเตือนที่รอให้อุปกรณ์ของผู้ใช้มารับ (ดู App\Services\WebPush) */
class PushMessage extends Model
{
    protected $fillable = ['user_id', 'text', 'url', 'delivered_at'];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime'];
    }
}
