<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Student;
use App\Models\User;
use App\Support\Settings;

use function Illuminate\Support\defer;

/**
 * จุดเดียวสำหรับส่งแจ้งเตือนออกนอกระบบ (ตอนนี้คือ LINE)
 * ส่งหลังตอบหน้าเว็บแล้ว (defer) ผู้ใช้จึงไม่ต้องรอ API ภายนอก
 */
class Notifier
{
    /** ส่งถึงผู้ปกครองของนักเรียน */
    public static function parents(Student $student, string $text, ?string $url = null): void
    {
        $guardians = $student->lineGuardians();
        if ($guardians->isEmpty()) {
            return;
        }
        self::dispatch($guardians, $text, $url);
    }

    public static function users(iterable $users, string $text, ?string $url = null): void
    {
        self::dispatch(collect($users), $text, $url);
    }

    public static function announcement(Announcement $a): void
    {
        $q = User::whereNotNull('line_user_id')->where('is_active', true);
        match ($a->audience) {
            'parents' => $q->where('role', 'parent'),
            'staff' => $q->whereIn('role', ['admin', 'teacher']),
            'classroom' => $q->where(fn ($w) => $w->whereHas('children', fn ($c) => $c->where('classroom_id', $a->classroom_id))
                ->orWhereIn('id', array_filter([$a->classroom?->homeroom_teacher_id, $a->classroom?->co_teacher_id]))),
            default => null,
        };
        self::dispatch($q->get(), "📢 {$a->title}\n\n".\Illuminate\Support\Str::limit($a->body, 300), route('announcements.show', $a));
    }

    private static function dispatch($users, string $text, ?string $url): void
    {
        $prefix = '['.(Settings::get('school_short') ?: Settings::get('school_name')).'] ';
        $message = $prefix.$text.($url ? "\n\n".$url : '');
        defer(fn () => Line::send($users, $message));
    }
}
