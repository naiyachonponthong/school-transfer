<?php

namespace App\Services;

use App\Jobs\SendLineMessage;
use App\Jobs\SendWebPush;
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
    /** ส่งทันทีได้ไม่เกินกี่ชุดต่อ 1 request ที่เหลือเข้าคิว */
    public const INLINE_LIMIT = 20;

    private static int $inline = 0;

    /** คำสั่งตามเวลา (ไม่มีหน้าเว็บให้รอ): ส่งทุกข้อความผ่านคิว */
    public static function viaQueue(): void
    {
        self::$inline = self::INLINE_LIMIT;
    }

    public static function resetInlineCount(): void
    {
        self::$inline = 0;
    }

    /** ส่งถึงผู้ปกครองของนักเรียน */
    public static function parents(Student $student, string $text, ?string $url = null): void
    {
        self::dispatch($student->guardians()->get(), $text, $url);
    }

    public static function users(iterable $users, string $text, ?string $url = null): void
    {
        self::dispatch(collect($users), $text, $url);
    }

    public static function announcement(Announcement $a): void
    {
        $q = User::where('is_active', true)->where(fn ($w) => $w->whereNotNull('line_user_id')->orWhereHas('pushSubscriptions'));
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
        // ผู้ที่เข้ามาทดลองใช้ต้องไม่ทำให้มีข้อความจริงส่งออกไป (LINE / แจ้งเตือนบนอุปกรณ์)
        if (\App\Support\Demo::role()) {
            return;
        }
        $prefix = '['.(Settings::get('school_short') ?: Settings::get('school_name')).'] ';
        $message = $prefix.$text.($url ? "\n\n".$url : '');
        // ผู้ที่เปิดรับแจ้งเตือนบนอุปกรณ์ได้ข้อความเดียวกัน (ไม่ต้องเชื่อม LINE)
        $pushIds = WebPush::notify(collect($users), $prefix.$text, $url);
        $users = collect($users)->filter(fn (User $u) => $u->line_user_id)->values();
        if ($users->isEmpty() && ! $pushIds) {
            return;
        }

        // งานจำนวนมากใน request เดียว (ออกใบแจ้งหนี้ทั้งชั้น, คำสั่งเตือนตามเวลา) เข้าคิว ไม่ถ่วง request
        if (++self::$inline > self::INLINE_LIMIT) {
            if ($users->isNotEmpty()) {
                SendLineMessage::dispatch($users->pluck('id')->all(), $message);
            }
            if ($pushIds) {
                SendWebPush::dispatch($pushIds);
            }

            return;
        }

        // งานปกติส่งทันทีหลังตอบหน้าเว็บ ผู้ปกครองจึงได้แจ้งเตือนเข้า-ออกโรงเรียนแบบไม่ต้องรอคิว
        // ส่งไม่ถึงเพราะปัญหาชั่วคราว → เข้าคิวไว้ส่งซ้ำ
        defer(function () use ($users, $message, $pushIds) {
            WebPush::send($pushIds);
            if ($users->isEmpty()) {
                return;
            }
            $failed = Line::send($users, $message);
            if ($failed->isNotEmpty()) {
                SendLineMessage::dispatch($failed->pluck('id')->all(), $message, 2)->delay(now()->addMinutes(2));
            }
        });
    }
}
