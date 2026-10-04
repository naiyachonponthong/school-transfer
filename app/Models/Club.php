<?php

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * ชุมนุม/ชมรมของภาคเรียน: นักเรียนเลือกเองได้ในช่วงเปิดรับ (คนละ 1 ชุมนุมต่อภาคเรียน) จำกัดจำนวนรับและระดับชั้นได้
 * เมื่อพร้อมใช้งาน สร้างรายวิชาของชุมนุม (course) เพื่อเช็คชื่อรายคาบและประเมินผล ผ/มผ ตามระบบปกติ
 */
class Club extends Model
{
    protected $fillable = ['term_id', 'name', 'description', 'teacher_id', 'capacity', 'levels', 'location', 'course_id'];

    protected function casts(): array
    {
        return ['levels' => 'array', 'capacity' => 'integer'];
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'club_members')->withTimestamps();
    }

    public function scopeForTerm(Builder $q, ?Term $term): Builder
    {
        return $q->where('term_id', $term?->id);
    }

    public function accepts(?string $level): bool
    {
        return ! $this->levels || in_array($level, $this->levels, true);
    }

    public function isFull(?int $count = null): bool
    {
        return $this->capacity !== null && ($count ?? $this->students()->count()) >= $this->capacity;
    }

    public function levelsLabel(): string
    {
        return $this->levels ? implode(' ', $this->levels) : 'ทุกระดับชั้น';
    }

    public function canBeManagedBy(User $user): bool
    {
        return $user->hasPermission('academics.manage') || ($this->teacher_id !== null && $this->teacher_id === $user->id);
    }

    /** ช่วงที่นักเรียนเลือก/เปลี่ยนชุมนุมเองได้ (ตั้งที่หน้าชุมนุม) — ไม่ตั้ง = ปิดรับ */
    public static function signupWindow(): array
    {
        $from = Settings::get('club_signup_from');
        $until = Settings::get('club_signup_until');

        return [$from ? Carbon::parse($from) : null, $until ? Carbon::parse($until) : null];
    }

    public static function signupOpen(): bool
    {
        [$from, $until] = self::signupWindow();

        return $from !== null && $until !== null && now()->between($from, $until);
    }

    /** รายชื่อสมาชิกไปเป็นรายชื่อของรายวิชาชุมนุม (ถ้าสร้างรายวิชาไว้แล้ว) */
    public function syncCourseMembers(): void
    {
        $course = $this->course;
        if (! $course || $course->locked) {
            return;
        }
        $ids = $this->students()->pluck('students.id');
        if ($ids->isNotEmpty()) { // รายชื่อว่าง = รายวิชาจะหมายถึงทั้งห้อง จึงไม่ล้างรายชื่อเดิม
            $course->members()->sync($ids);
        }
    }
}
