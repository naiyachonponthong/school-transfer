<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Student extends Model
{
    public const STATUSES = [
        'active' => 'กำลังศึกษา',
        'graduated' => 'จบการศึกษา',
        'moved' => 'ย้ายโรงเรียน',
        'dropped' => 'พ้นสภาพ',
    ];

    public const BASE_BEHAVIOR = 100;

    protected $fillable = [
        'student_code', 'citizen_id', 'prefix', 'first_name', 'last_name', 'nickname', 'gender', 'birthdate',
        'classroom_id', 'number', 'status', 'photo', 'blood_type', 'medical_note', 'address', 'phone', 'qr_token', 'user_id',
    ];

    protected static function booted(): void
    {
        // โทเคนสุ่มสำหรับ QR บนบัตรนักเรียน (ไม่ใช้รหัสนักเรียนตรง ๆ เพื่อกันปลอมบัตร)
        static::creating(fn (Student $s) => $s->qr_token ??= self::newQrToken());
    }

    public static function newQrToken(): string
    {
        return 'S'.\Illuminate\Support\Str::upper(\Illuminate\Support\Str::random(15));
    }

    public function qrPayload(): string
    {
        if (! $this->qr_token) {
            $this->forceFill(['qr_token' => self::newQrToken()])->save();
        }

        return $this->qr_token;
    }

    public function healthVisits(): HasMany
    {
        return $this->hasMany(HealthVisit::class)->latest('visited_at');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(HealthMeasurement::class)->latest('measured_on');
    }

    public function works(): HasMany
    {
        return $this->hasMany(StudentWork::class)->latest('date')->latest('id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function bookLoans(): HasMany
    {
        return $this->hasMany(BookLoan::class)->latest('borrowed_on');
    }

    /** ผู้ปกครองที่เชื่อม LINE แล้ว */
    public function lineGuardians()
    {
        return $this->guardians()->whereNotNull('line_user_id')->get();
    }

    protected function casts(): array
    {
        return ['birthdate' => DateOnly::class];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('status', 'active');
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }

        return $q->where(function ($q) use ($term) {
            $q->where('student_code', 'like', "%{$term}%")
                ->orWhere('first_name', 'like', "%{$term}%")
                ->orWhere('last_name', 'like', "%{$term}%")
                ->orWhere('nickname', 'like', "%{$term}%")
                ->orWhere('citizen_id', 'like', "%{$term}%");
        });
    }

    public function fullName(): string
    {
        return trim("{$this->prefix}{$this->first_name} {$this->last_name}");
    }

    public function getFullNameAttribute(): string
    {
        return $this->fullName();
    }

    public function initials(): string
    {
        return mb_substr($this->nickname ?: $this->first_name, 0, 1);
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? asset('storage/'.$this->photo) : null;
    }

    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'guardian_student')->withPivot('relation');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function behaviorRecords(): HasMany
    {
        return $this->hasMany(BehaviorRecord::class)->latest('date')->latest('id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class)->latest();
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest();
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public function behaviorScore(): int
    {
        $sum = $this->relationLoaded('behaviorRecords')
            ? $this->behaviorRecords->sum('points')
            : $this->behaviorRecords()->sum('points');

        return self::BASE_BEHAVIOR + (int) $sum;
    }

    public function age(): ?int
    {
        return $this->birthdate?->age;
    }

    public function isGuardedBy(User $user): bool
    {
        return $this->guardians()->whereKey($user->id)->exists();
    }

    /** บัญชีผู้ใช้ของนักเรียนเอง (role = student) */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $user->isStudent() && $this->user_id === $user->id;
    }

    /** ครู/ผู้ดูแล, ผู้ปกครองของนักเรียนคนนี้ หรือนักเรียนเจ้าของข้อมูลเอง */
    public function canBeViewedBy(User $user): bool
    {
        return $user->isStaff() || $this->isOwnedBy($user) || $this->isGuardedBy($user);
    }

    public function periodAttendances(): HasMany
    {
        return $this->hasMany(PeriodAttendance::class);
    }
}
