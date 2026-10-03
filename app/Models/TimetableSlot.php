<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableSlot extends Model
{
    public $timestamps = false;

    public const DAYS = [1 => 'จันทร์', 2 => 'อังคาร', 3 => 'พุธ', 4 => 'พฤหัสบดี', 5 => 'ศุกร์', 6 => 'เสาร์'];

    /** วันเรียนของโรงเรียน (ตั้งค่า `school_days`: 5 = จันทร์–ศุกร์, 6 = รวมเสาร์) */
    public static function days(): array
    {
        return array_slice(self::DAYS, 0, (int) Settings::get('school_days') === 6 ? 6 : 5, true);
    }

    protected $fillable = ['term_id', 'classroom_id', 'day', 'period', 'course_id', 'label', 'room_name'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }
}
