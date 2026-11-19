<?php

namespace App\Support;

use Cron\CronExpression;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

/**
 * งานตั้งเวลาแบบผ่านเว็บ สำหรับโฮสต์ที่ไม่มี cron
 * ทำงานหลังส่งหน้าเว็บให้ผู้ใช้แล้ว (terminate) จึงไม่ทำให้หน้าเว็บช้า
 * - ถ้า cron ของโฮสต์ทำงานอยู่ (heartbeat ไม่เกิน 5 นาที) จะไม่ทำอะไร
 * - ทำงานในโปรเซสเดียวกัน ไม่สั่ง php ตัวใหม่ (shared hosting มักปิด proc_open) จึงไม่เรียก schedule:run
 * - ใช้งานใน routes/console.php ชุดเดียวกับ cron จริง งานที่เลยเวลาไปเพราะไม่มีคนเปิดเว็บ ทำย้อนให้ภายใน 20 ชั่วโมง
 */
class WebCron
{
    /** งานที่เลยเวลานานกว่านี้ไม่ทำย้อน (กันแจ้งเตือนเก่าค้างส่งทีหลัง) */
    private const CATCH_UP_HOURS = 20;

    public static function realCronAlive(): bool
    {
        $last = Cache::get('school.scheduler_last');

        return $last && now()->diffInSeconds(Carbon::parse($last), true) < 300;
    }

    public static function maybeRun(): void
    {
        // เช็กด้วยเวลาไฟล์ก่อน ไม่แตะฐานข้อมูล (หน้าจอ POS / จอหน้าประตูเรียกบ่อย)
        $stamp = storage_path('framework/webcron');
        if (is_file($stamp) && time() - filemtime($stamp) < 60) {
            return;
        }
        @touch($stamp);
        if (! config('school.web_cron') || ! Installer::isInstalled() || Installer::needsUpdate() || static::realCronAlive()) {
            return;
        }
        $lock = Cache::lock('school.webcron', 180);
        if (! $lock->get()) {
            return;
        }
        try {
            @set_time_limit(170);
            Cache::forever('school.webcron_last', now()->toDateTimeString());
            static::runDueEvents();
        } catch (\Throwable $e) {
            report($e);
        } finally {
            $lock->release();
        }
    }

    /** รันงานใน routes/console.php ที่ถึงเวลาแล้วแต่ยังไม่ได้ทำ (แต่ละงานจำเวลารอบล่าสุดไว้ใน cache) */
    public static function runDueEvents(): int
    {
        app(ConsoleKernel::class)->bootstrap(); // โหลด routes/console.php ให้มีรายการงาน
        $app = app();
        $now = now();
        $ran = 0;

        foreach (app(Schedule::class)->events() as $event) {
            if ($event->description === 'school-heartbeat' || ! $event->runsInEnvironment($app->environment())) {
                continue; // heartbeat มีไว้บอกว่ามี cron จริง ห้ามรันจากทางนี้
            }
            $due = Carbon::instance((new CronExpression($event->expression))->getPreviousRunDate($now->copy()->setTimezone($event->timezone ?? config('app.timezone')), 0, true));
            $key = 'school.webcron.ran.'.sha1($event->expression.'|'.$event->command.'|'.$event->description);
            $last = Cache::get($key);
            if ($last === null) {
                Cache::forever($key, $due->timestamp); // เห็นงานนี้ครั้งแรก (เพิ่งติดตั้ง) เริ่มนับรอบถัดไป ไม่ทำย้อน

                continue;
            }
            if ($due->timestamp <= (int) $last || $now->diffInHours($due, true) > self::CATCH_UP_HOURS || ! $event->filtersPass($app)) {
                continue;
            }
            Cache::forever($key, $due->timestamp);
            try {
                static::runEvent($event);
                $ran++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $ran;
    }

    private static function runEvent(Event $event): void
    {
        if ($event instanceof CallbackEvent) {
            $event->run(app());

            return;
        }
        // "php" "artisan" backup:database --keep-days=14  >  backup:database --keep-days=14
        if (preg_match('/artisan[\'"]?\s+(.+)$/s', (string) $event->command, $m)) {
            Artisan::call(trim($m[1]));
        }
    }
}
