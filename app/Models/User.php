<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLES = [
        'admin' => 'ผู้ดูแลระบบ',
        'teacher' => 'ครู/บุคลากร',
        'parent' => 'ผู้ปกครอง',
        'student' => 'นักเรียน',
    ];

    protected $fillable = [
        'name', 'username', 'email', 'phone', 'role', 'position', 'is_active', 'password', 'last_login_at',
        'avatar', 'notifications_seen_at', 'line_user_id', 'line_link_code', 'line_linked_at', 'must_change_password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'must_change_password' => 'boolean',
            'notifications_seen_at' => 'datetime',
            'line_linked_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isTeacher(): bool
    {
        return $this->role === 'teacher';
    }

    public function isParent(): bool
    {
        return $this->role === 'parent';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    /** ข้อมูลนักเรียนของบัญชีนักเรียนนี้ */
    public function studentProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Student::class);
    }

    /** งานพัสดุ/อาคารสถานที่: จัดการครุภัณฑ์ ตรวจสอบพัสดุ รับเรื่องแจ้งซ่อม (ผู้ดูแลระบบ + ครูที่ตั้งไว้ในหน้าตั้งค่า) */
    public function canManageFacilities(): bool
    {
        return $this->hasPermission('facilities.manage') || ($this->isStaff() && in_array($this->id, self::facilityManagerIds(), true));
    }

    /** @return list<int> */
    public static function facilityManagerIds(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) \App\Support\Settings::get('facility_manager_ids')))));
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    private ?array $permissionCache = null;

    /**
     * สิทธิ์ของบุคลากร = รวมสิทธิ์ของทุกตำแหน่งที่ถือ (ยังไม่กำหนดตำแหน่ง = สิทธิ์ของ "ครู")
     * ผู้ดูแลระบบได้ทุกสิทธิ์ ผู้ปกครอง/นักเรียนไม่มีสิทธิ์ชุดนี้
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        if (! $this->isStaff()) {
            return [];
        }
        if ($this->isAdmin()) {
            return Permissions::keys();
        }

        return $this->permissionCache ??= (function () {
            $roles = $this->roles()->get();
            if ($roles->isEmpty()) {
                $roles = Role::where('key', Permissions::FALLBACK_ROLE)->get();
            }

            return $roles->pluck('permissions')->flatten()->unique()->values()->all();
        })();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    public function flushPermissions(): void
    {
        $this->permissionCache = null;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'teacher'], true);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')->withPivot('relation');
    }

    public function homerooms(): HasMany
    {
        return $this->hasMany(Classroom::class, 'homeroom_teacher_id');
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    /** ห้องที่ครูคนนี้ดูแล (ประจำชั้น/ครูร่วม) ในปีปัจจุบัน */
    public function myClassrooms()
    {
        $year = Term::current()?->year;

        return Classroom::query()
            ->when($year, fn ($q) => $q->where('year', $year))
            ->where(fn ($q) => $q->where('homeroom_teacher_id', $this->id)->orWhere('co_teacher_id', $this->id))
            ->ordered()
            ->get();
    }

    /** อักษรแรกของชื่อจริง (ข้ามคำนำหน้า นาย/นาง/นางสาว) */
    public function initials(): string
    {
        $name = preg_replace('/^(นางสาว|นาย|นาง|ครู|Mr\.|Mrs\.|Ms\.)\s*/u', '', trim($this->name));

        return mb_substr($name ?: $this->name, 0, 1);
    }

    public function hasLine(): bool
    {
        return (bool) $this->line_user_id;
    }

    public function staffLeaves(): HasMany
    {
        return $this->hasMany(StaffLeave::class)->latest();
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar ? asset('storage/'.$this->avatar) : null;
    }

    public function firstName(): string
    {
        $name = preg_replace('/^(นางสาว|นาย|นาง|Mr\.|Mrs\.|Ms\.)\s*/u', '', trim($this->name));

        return explode(' ', $name)[0] ?: $this->name;
    }
}
