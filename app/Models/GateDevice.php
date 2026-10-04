<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** เครื่องสแกนใบหน้า/บัตรที่ประตู ซึ่งส่งผลการสแกนเข้าระบบเอง (เช่น Hikvision ที่รองรับ ISAPI) */
class GateDevice extends Model
{
    public const MODES = ['auto' => 'เข้า/ออกตามเวลา', 'in' => 'ขาเข้าเท่านั้น', 'out' => 'ขาออกเท่านั้น'];

    /** ไม่ได้ข้อมูลจากเครื่องเกินกี่นาทีจึงถือว่าขาดการติดต่อ */
    public const ONLINE_MINUTES = 10;

    protected $fillable = ['name', 'location', 'mode', 'token', 'is_active', 'last_seen_at', 'created_by'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_seen_at' => 'datetime'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(GateEvent::class);
    }

    public function modeLabel(): string
    {
        return self::MODES[$this->mode] ?? $this->mode;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subMinutes(self::ONLINE_MINUTES));
    }

    public function hookUrl(): string
    {
        return route('gate.hook', $this->token);
    }
}
