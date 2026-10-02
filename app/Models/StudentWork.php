<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** แฟ้มสะสมผลงาน (Portfolio) */
class StudentWork extends Model
{
    public const CATEGORIES = [
        'award' => ['รางวัล/เกียรติบัตร', 'bi-trophy', 'warning'],
        'work' => ['ผลงาน/ชิ้นงาน', 'bi-palette', 'primary'],
        'activity' => ['กิจกรรม', 'bi-flag', 'success'],
        'volunteer' => ['จิตอาสา', 'bi-heart', 'danger'],
    ];

    public const LEVELS = [
        'school' => 'ระดับโรงเรียน', 'district' => 'ระดับเขตพื้นที่', 'province' => 'ระดับจังหวัด',
        'region' => 'ระดับภาค', 'national' => 'ระดับประเทศ', 'international' => 'ระดับนานาชาติ',
    ];

    protected $fillable = ['student_id', 'category', 'title', 'description', 'level', 'date', 'hours', 'image', 'recorded_by', 'verified'];

    protected function casts(): array
    {
        return ['date' => DateOnly::class, 'hours' => 'float', 'verified' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function imageUrl(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category][0] ?? $this->category;
    }

    public function categoryIcon(): string
    {
        return self::CATEGORIES[$this->category][1] ?? 'bi-star';
    }

    public function categoryColor(): string
    {
        return self::CATEGORIES[$this->category][2] ?? 'secondary';
    }
}
