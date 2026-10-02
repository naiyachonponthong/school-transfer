<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthVisit extends Model
{
    public const ACTIONS = [
        'rest' => ['พักที่ห้องพยาบาล', 'info'],
        'returned' => ['กลับเข้าเรียน', 'success'],
        'sent_home' => ['ผู้ปกครองรับกลับบ้าน', 'warning'],
        'hospital' => ['ส่งโรงพยาบาล', 'danger'],
    ];

    public const SYMPTOMS = ['ปวดหัว', 'ปวดท้อง', 'มีไข้', 'เป็นหวัด/ไอ', 'บาดแผล/หกล้ม', 'เวียนหัว/เป็นลม', 'ท้องเสีย', 'ปวดประจำเดือน', 'แพ้/ผื่นคัน'];

    protected $fillable = ['student_id', 'visited_at', 'symptom', 'temperature', 'treatment', 'medicine', 'action', 'recorded_by'];

    protected function casts(): array
    {
        return ['visited_at' => 'datetime', 'temperature' => 'float'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action][0] ?? $this->action;
    }

    public function actionColor(): string
    {
        return self::ACTIONS[$this->action][1] ?? 'secondary';
    }
}
