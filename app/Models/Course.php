<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use App\Support\Grade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $fillable = ['term_id', 'classroom_id', 'subject_id', 'teacher_id', 'locked', 'submitted_at', 'submitted_by', 'approved_at', 'approved_by', 'return_note', 'grade_scale', 'variant', 'title'];

    protected static function booted(): void
    {
        // อนุมัติ/ล็อกผล = เก็บเกณฑ์ตัดเกรดที่ใช้ ณ ตอนนั้น · ปลดล็อก = กลับไปใช้เกณฑ์ปัจจุบัน
        static::saving(function (Course $course) {
            if ($course->isDirty('locked')) {
                $course->grade_scale = $course->locked ? Grade::scaleString() : null;
            }
        });
    }

    protected function casts(): array
    {
        return ['locked' => 'boolean', 'submitted_at' => 'datetime', 'approved_at' => 'datetime'];
    }

    /** ชื่อที่ใช้แสดง: รายวิชาของชุมนุมใช้ชื่อชุมนุม นอกนั้นใช้ชื่อวิชา */
    public function label(): string
    {
        return $this->title ?: $this->subject->name;
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class)->orderBy('sort')->orderBy('id');
    }

    /** รายชื่อเฉพาะของรายวิชา (วิชาเลือก/ชุมนุม) — ว่าง = เรียนทั้งห้อง */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'course_students');
    }

    private ?array $memberIdCache = null;

    /** @return list<int> */
    public function memberIds(): array
    {
        return $this->memberIdCache ??= $this->members()->pluck('students.id')->all();
    }

    /**
     * ผู้เรียนของรายวิชานี้: รายชื่อเฉพาะถ้ากำหนดไว้ ไม่งั้นทั้งห้อง
     * $activeOnly = false ใช้กับสมุดคะแนน/ผลการเรียน ที่ต้องเห็นคนที่เคยเรียนแม้เลื่อนชั้นหรือจบไปแล้ว
     */
    public function students(bool $activeOnly = true)
    {
        if ($ids = $this->memberIds()) {
            return Student::query()->whereIn('students.id', $ids)->when($activeOnly, fn ($q) => $q->where('status', 'active'))
                ->orderBy('student_code');
        }

        return $activeOnly ? $this->classroom->students() : $this->classroom->roster();
    }

    /** นักเรียนคนนี้เรียนรายวิชานี้หรือไม่ ($classroomId = ห้องของนักเรียนในปีการศึกษาของรายวิชา) */
    public function includesStudent(Student $student, ?int $classroomId): bool
    {
        $ids = $this->memberIds();

        return $ids ? in_array($student->id, $ids, true) : $this->classroom_id === $classroomId;
    }

    /** รายวิชาที่นักเรียนเรียน: วิชาของห้องตัวเองที่ไม่จำกัดรายชื่อ + วิชาที่มีชื่ออยู่ในรายชื่อเฉพาะ */
    public function scopeForStudent(Builder $q, Student $student, ?int $classroomId): Builder
    {
        return $q->where(fn ($w) => $w
            ->where(fn ($a) => $a->where('classroom_id', $classroomId)->whereDoesntHave('members'))
            ->orWhereHas('members', fn ($m) => $m->where('students.id', $student->id)));
    }

    /** ครูผู้สอนส่งผลแล้ว รอฝ่ายวิชาการตรวจ */
    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null && ! $this->locked;
    }

    public function canEdit(User $user): bool
    {
        return $user->isAdmin() || $this->teacher_id === $user->id;
    }

    public function maxTotal(): float
    {
        return (float) $this->assessments->sum('max_score');
    }

    public function outcomes(): HasMany
    {
        return $this->hasMany(CourseResult::class);
    }

    /** กิจกรรมพัฒนาผู้เรียน: ผลเป็น ผ/มผ แทนเกรด */
    public function isActivity(): bool
    {
        return $this->subject?->type === 'activity';
    }

    /**
     * คะแนนรวม + เกรดของนักเรียนทุกคนในวิชานี้
     * computed = เกรดจากคะแนน · original = ผลก่อนแก้ตัว (ร/มส/มผ ที่ครูกำหนดมาก่อนคะแนน) · grade = ผลสุดท้าย (รวมผลแก้ตัว)
     *
     * @return array<int, array{total: float|null, percent: float|null, computed: string|null, original: string|null, grade: string|null, special: string|null, remedial: string|null, complete: bool}>
     */
    public function results(): array
    {
        $this->loadMissing(['assessments', 'subject']);
        $ids = $this->assessments->pluck('id');
        $max = $this->maxTotal();
        $count = $ids->count();
        $activity = $this->isActivity();

        $rows = Score::whereIn('assessment_id', $ids)->whereNotNull('score')->get()->groupBy('student_id');
        $outcomes = CourseResult::where('course_id', $this->id)->get()->keyBy('student_id');

        $out = [];
        foreach ($rows->keys()->merge($outcomes->keys())->unique() as $studentId) {
            $scores = $rows[$studentId] ?? collect();
            $total = $scores->isEmpty() ? null : (float) $scores->sum('score');
            $percent = $total !== null && $max > 0 ? $total / $max * 100 : null;
            $complete = $scores->count() >= $count && $count > 0;
            $computed = $complete && $percent !== null
                ? ($activity ? Grade::activityFromPercent($percent) : Grade::fromPercent($percent, $this->grade_scale))
                : null;

            $o = $outcomes[$studentId] ?? null;
            $original = $o?->special ?? $computed;
            $out[$studentId] = [
                'total' => $total,
                'percent' => $percent,
                'computed' => $computed,
                'original' => $original,
                'grade' => $o?->remedial_grade ?? $original,
                'special' => $o?->special,
                'remedial' => $o?->remedial_grade,
                'complete' => $complete,
            ];
        }

        return $out;
    }
}
