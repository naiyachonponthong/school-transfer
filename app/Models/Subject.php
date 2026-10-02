<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    public const TYPES = [
        'basic' => 'พื้นฐาน',
        'extra' => 'เพิ่มเติม',
        'activity' => 'กิจกรรมพัฒนาผู้เรียน',
    ];

    public const GROUPS = [
        'ภาษาไทย', 'คณิตศาสตร์', 'วิทยาศาสตร์และเทคโนโลยี', 'สังคมศึกษา ศาสนา และวัฒนธรรม',
        'สุขศึกษาและพลศึกษา', 'ศิลปะ', 'การงานอาชีพ', 'ภาษาต่างประเทศ', 'กิจกรรมพัฒนาผู้เรียน',
    ];

    /** ประเภทกิจกรรมพัฒนาผู้เรียน (ใช้จัดกลุ่มใน ปพ.1/ปพ.6) */
    public const ACTIVITY_KINDS = [
        'guidance' => 'กิจกรรมแนะแนว',
        'scout' => 'ลูกเสือ/เนตรนารี/ยุวกาชาด/ผู้บำเพ็ญประโยชน์',
        'club' => 'ชุมนุม/ชมรม',
        'social' => 'กิจกรรมเพื่อสังคมและสาธารณประโยชน์',
    ];

    protected $fillable = ['code', 'name', 'credit', 'hours', 'type', 'activity_kind', 'group'];

    protected function casts(): array
    {
        return ['credit' => 'decimal:1', 'hours' => 'integer'];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** ลำดับในเอกสาร: พื้นฐาน → เพิ่มเติม → กิจกรรม */
    public function typeOrder(): int
    {
        $i = array_search($this->type, array_keys(self::TYPES), true);

        return $i === false ? 99 : $i;
    }

    public function activityKindLabel(): ?string
    {
        return $this->activity_kind ? (self::ACTIVITY_KINDS[$this->activity_kind] ?? $this->activity_kind) : null;
    }
}
