<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProfile extends Model
{
    /** เตือนใบอนุญาตประกอบวิชาชีพก่อนหมดอายุกี่วัน */
    public const LICENSE_WARN_DAYS = 90;

    protected $fillable = ['user_id', 'citizen_id', 'birthdate', 'rank', 'hired_on', 'education', 'major',
        'license_no', 'license_expires_on', 'license_reminded_on', 'address', 'emergency_contact'];

    protected function casts(): array
    {
        return ['birthdate' => DateOnly::class, 'hired_on' => DateOnly::class,
            'license_expires_on' => DateOnly::class, 'license_reminded_on' => DateOnly::class];
    }


    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** จำนวนวันที่เหลือก่อนใบอนุญาตหมดอายุ (ติดลบ = หมดแล้ว, null = ไม่ได้กรอก) */
    public function licenseDaysLeft(): ?int
    {
        return $this->license_expires_on ? (int) today()->diffInDays($this->license_expires_on, false) : null;
    }

    public function licenseExpiring(): bool
    {
        $days = $this->licenseDaysLeft();

        return $days !== null && $days <= self::LICENSE_WARN_DAYS;
    }

    public function yearsOfService(): ?int
    {
        return $this->hired_on ? (int) $this->hired_on->diffInYears(today()) : null;
    }

}
