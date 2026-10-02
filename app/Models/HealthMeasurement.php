<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthMeasurement extends Model
{
    protected $fillable = ['student_id', 'measured_on', 'weight', 'height', 'note', 'recorded_by'];

    protected function casts(): array
    {
        return ['measured_on' => DateOnly::class, 'weight' => 'float', 'height' => 'float'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function bmi(): ?float
    {
        return $this->weight && $this->height ? round($this->weight / (($this->height / 100) ** 2), 1) : null;
    }

    /** แปลผล BMI เบื้องต้น (เด็กควรใช้กราฟเทียบอายุของกรมอนามัยประกอบ) */
    public function bmiLabel(): array
    {
        $b = $this->bmi();

        return match (true) {
            $b === null => ['-', 'secondary'],
            $b < 17 => ['ผอม', 'info'],
            $b < 23 => ['สมส่วน', 'success'],
            $b < 25 => ['ท้วม', 'warning'],
            default => ['อ้วน', 'danger'],
        };
    }
}
