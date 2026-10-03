<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeVisit extends Model
{
    public const HOUSING = ['own' => 'บ้านตนเอง', 'rent' => 'บ้านเช่า', 'relative' => 'อาศัยกับญาติ', 'other' => 'อื่น ๆ'];

    public const FAMILY = ['together' => 'อยู่ร่วมกัน', 'separated' => 'แยกกันอยู่/หย่าร้าง', 'deceased' => 'บิดาหรือมารดาเสียชีวิต', 'other' => 'อื่น ๆ'];

    public const RISKS = [
        'economic' => 'เศรษฐกิจ/รายได้', 'family' => 'ครอบครัว', 'health' => 'สุขภาพ',
        'safety' => 'ความปลอดภัย', 'substance' => 'สารเสพติด', 'travel' => 'การเดินทางมาโรงเรียน',
    ];

    protected $fillable = ['student_id', 'term_id', 'visited_on', 'visitor_id', 'guardian_met', 'housing', 'family_status', 'risks', 'note', 'photo', 'photo_inside', 'form', 'lat', 'lng'];

    protected function casts(): array
    {
        return ['visited_on' => DateOnly::class, 'risks' => 'array', 'form' => 'array'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visitor_id');
    }

    public function riskLabels(): array
    {
        return array_values(array_intersect_key(self::RISKS, array_flip($this->risks ?? [])));
    }
}
