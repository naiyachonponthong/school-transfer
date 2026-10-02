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

    protected $fillable = ['code', 'name', 'credit', 'type', 'group'];

    protected function casts(): array
    {
        return ['credit' => 'decimal:1'];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
