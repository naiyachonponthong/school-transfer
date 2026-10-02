<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** ครุภัณฑ์ */
class Asset extends Model
{
    public const STATUSES = [
        'normal' => ['ใช้งานได้', 'success'],
        'repairing' => ['กำลังซ่อม', 'warning'],
        'broken' => ['ชำรุด', 'danger'],
        'lost' => ['สูญหาย', 'dark'],
        'pending_disposal' => ['รอจำหน่าย', 'secondary'],
        'disposed' => ['จำหน่ายแล้ว', 'secondary'],
    ];

    /**
     * ประเภทครุภัณฑ์ → อายุการใช้งานเริ่มต้น (ปี) สำหรับคิดค่าเสื่อมราคาแบบเส้นตรง
     * เป็นค่าเริ่มต้นเท่านั้น แก้รายชิ้นได้ — ตรวจกับหลักเกณฑ์ที่หน่วยงานต้นสังกัดใช้
     */
    public const CATEGORIES = [
        'ครุภัณฑ์คอมพิวเตอร์' => 3,
        'ครุภัณฑ์สำนักงาน' => 5,
        'ครุภัณฑ์การศึกษา' => 5,
        'ครุภัณฑ์ไฟฟ้าและวิทยุ' => 5,
        'ครุภัณฑ์โฆษณาและเผยแพร่' => 5,
        'ครุภัณฑ์วิทยาศาสตร์' => 5,
        'ครุภัณฑ์กีฬา' => 5,
        'ครุภัณฑ์ดนตรีและนาฏศิลป์' => 5,
        'ครุภัณฑ์งานบ้านงานครัว' => 5,
        'ครุภัณฑ์การเกษตร' => 5,
        'ครุภัณฑ์ยานพาหนะและขนส่ง' => 8,
        'ครุภัณฑ์อื่น ๆ' => 5,
    ];

    protected $fillable = ['code', 'name', 'category', 'brand', 'serial_no', 'acquired_on', 'price', 'budget_source', 'location',
        'responsible_id', 'useful_life', 'status', 'photo', 'note', 'qr_token', 'disposed_on'];

    protected function casts(): array
    {
        return ['acquired_on' => DateOnly::class, 'disposed_on' => DateOnly::class, 'price' => 'float', 'useful_life' => 'integer'];
    }

    protected static function booted(): void
    {
        // QR บนสติกเกอร์ใช้รหัสสุ่ม (ไม่ใช่เลขครุภัณฑ์) เปิดดูได้เฉพาะคนที่เข้าระบบ
        static::creating(fn (Asset $a) => $a->qr_token ??= Str::lower(Str::random(20)));
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(RepairRequest::class)->latest('id');
    }

    public function checks(): HasMany
    {
        return $this->hasMany(AssetCheck::class)->orderByDesc('year');
    }

    /** ยังอยู่ในความดูแล (ไม่นับที่จำหน่ายแล้ว) */
    public function scopeInService(Builder $q): Builder
    {
        return $q->where('status', '!=', 'disposed');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }

    public function qrUrl(): string
    {
        return route('assets.go', $this->qr_token);
    }

    public function life(): ?int
    {
        return $this->useful_life ?: (self::CATEGORIES[$this->category] ?? null);
    }

    /** ค่าเสื่อมราคาสะสมแบบเส้นตรงถึงวันที่ระบุ (คงมูลค่าซากไว้ 1 บาท) */
    public function accumulatedDepreciation(?Carbon $at = null): ?float
    {
        $life = $this->life();
        if (! $life || ! $this->acquired_on || $this->price <= 1) {
            return null;
        }
        $days = max(0, $this->acquired_on->diffInDays($at ?? today()));
        $depreciable = $this->price - 1;

        return round(min($depreciable, $depreciable * $days / ($life * 365)), 2);
    }

    public function bookValue(?Carbon $at = null): ?float
    {
        $acc = $this->accumulatedDepreciation($at);

        return $acc === null ? null : round($this->price - $acc, 2);
    }
}
