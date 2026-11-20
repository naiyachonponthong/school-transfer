<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** บันทึกประจำวันเด็กปฐมวัย (ดื่มนม อาหารกลางวัน แปรงฟัน นอนกลางวัน) */
class PreschoolDailyLog extends Model
{
    protected $fillable = ['date', 'student_id', 'milk', 'meal', 'brush', 'nap', 'note', 'recorded_by'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class, 'milk' => 'boolean', 'meal' => 'boolean', 'brush' => 'boolean', 'nap' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
