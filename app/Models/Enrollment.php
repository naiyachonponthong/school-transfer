<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** นักเรียนอยู่ห้องไหนในปีการศึกษาใด (ปีละ 1 แถวต่อคน) */
class Enrollment extends Model
{
    public const STATUSES = [
        'studying' => 'กำลังเรียน',
        'promoted' => 'เลื่อนชั้น',
        'retained' => 'ซ้ำชั้น',
        'graduated' => 'จบการศึกษา',
        'moved' => 'ย้ายโรงเรียน',
        'dropped' => 'พ้นสภาพ',
    ];

    /** สถานะที่นับว่าเรียนในห้องนั้นจนจบปี (ใช้แสดงรายชื่อห้องของปีเก่า) */
    public const IN_ROSTER = ['studying', 'promoted', 'retained', 'graduated'];

    /** สถานะนักเรียน → สถานะของปีที่กำลังเรียนอยู่ */
    public const FROM_STUDENT = ['active' => 'studying', 'graduated' => 'graduated', 'moved' => 'moved', 'dropped' => 'dropped'];

    protected $fillable = ['student_id', 'classroom_id', 'year', 'number', 'status'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
