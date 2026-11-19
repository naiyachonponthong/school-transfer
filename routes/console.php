<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// สำรองฐานข้อมูลทุกวันตอนตี 2 (ต้องรัน `php artisan schedule:run` ทุกนาทีผ่าน cron/Task Scheduler จึงจะทำงาน — ดู README)
Schedule::command('backup:database')->dailyAt('02:00')->withoutOverlapping();
// สำรองไฟล์ที่อัปโหลด (รูป สลิป ใบรับรองแพทย์ ภาพกระดาษคำตอบ) ตามหลังฐานข้อมูล
Schedule::command('backup:files')->dailyAt('02:15')->withoutOverlapping();

// ให้บัญชีที่ยังใช้รหัสผ่านเริ่มต้นต้องตั้งรหัสใหม่ตอนเข้าระบบครั้งถัดไป (ใช้ตอนเริ่มเปิดระบบบังคับเปลี่ยนรหัส)
Artisan::command('users:require-password-change {--role=* : เฉพาะบทบาท เช่น parent student (เว้นว่าง = ทุกบัญชี)}', function () {
    $roles = $this->option('role');
    $count = User::query()->when($roles, fn ($q) => $q->whereIn('role', $roles))->update(['must_change_password' => true]);
    $this->info("ตั้งให้ {$count} บัญชีต้องเปลี่ยนรหัสผ่านเมื่อเข้าระบบครั้งถัดไป");
})->purpose('บังคับให้บัญชีตั้งรหัสผ่านใหม่ตอนเข้าระบบครั้งถัดไป');

// แจ้งเตือนตามเวลา
Schedule::command('attendance:remind-unchecked')->weekdays()->at('09:00');
Schedule::command('fees:remind')->dailyAt('10:00');
Schedule::command('library:remind-overdue')->mondays()->at('10:30');
Schedule::command('staff:license-remind')->dailyAt('08:30');
// โหมดทดลองใช้: คืนข้อมูลเป็นต้นแบบทุกคืน (ทำเฉพาะเมื่อเปิดไว้ในหน้าตั้งค่า)
Schedule::command('demo:reset --scheduled')->dailyAt('03:30')->withoutOverlapping();

// ลบประวัติเหตุการณ์จากเครื่องสแกนที่ประตูที่เก่าเกินกำหนด (ข้อมูลการมาเรียนไม่ถูกลบ)
Schedule::call(fn () => \App\Models\GateEvent::where('occurred_at', '<', now()->subDays(\App\Models\GateEvent::KEEP_DAYS))->delete())
    ->dailyAt('03:10')->name('gate-events:prune');

// ส่งข้อความ LINE ที่ค้างในคิว (งานจำนวนมาก/การส่งซ้ำ) — ถ้ารัน `queue:work` ค้างไว้อยู่แล้ว บรรทัดนี้ไม่มีผลเสีย
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')->everyMinute()->withoutOverlapping(5);

// heartbeat ของ cron จริง: เขียนทุกนาทีเมื่อโฮสต์รัน schedule:run ให้ WebCron (งานตั้งเวลาผ่านเว็บ) รู้ว่าไม่ต้องทำแทน
Schedule::call(fn () => Cache::forever('school.scheduler_last', now()->toDateTimeString()))
    ->everyMinute()->name('school-heartbeat');


// License: ตรวจกับศูนย์ License + สแกนไฟล์ระบบแล้วรายงานไฟล์ที่ถูกแก้ (App\Support\License\Daily)
Artisan::command('license:daily', function () {
    \App\Support\License\Daily::run();
    $this->info('ตรวจ License และไฟล์ระบบแล้ว');
})->purpose('ตรวจ License กับศูนย์ License และรายงานไฟล์ระบบที่ถูกแก้');
Schedule::command('license:daily')->dailyAt('04:20')->withoutOverlapping();

// รายการไฟล์ของชุดติดตั้ง: --write ใช้ตอน build (make_release.py) ไม่ใส่ = ตรวจไฟล์ในเครื่องนี้
Artisan::command('school:integrity
    {--write : สร้างรายการไฟล์ใหม่ (ใช้ตอน build)}
    {--root= : โฟลเดอร์ที่จะไล่หาไฟล์ ว่างไว้คือโปรเจกต์นี้}
    {--out= : ไฟล์ปลายทางของรายการไฟล์}', function () {
    if ($this->option('write')) {
        $root = rtrim((string) ($this->option('root') ?: base_path()), '/\\');
        $manifest = \App\Support\License\Integrity::build($root);
        $path = (string) ($this->option('out') ?: $root.'/'.\App\Support\License\Integrity::MANIFEST);
        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->info('เขียนรายการไฟล์ '.count($manifest['files']).' ไฟล์ลง '.$path);
        $this->line('ขั้นต่อไป เซ็นไฟล์นี้ที่ร้าน: php artisan license:sign '.$path);

        return 0;
    }

    \App\Support\License\Integrity::flush();
    $status = \App\Support\License\Integrity::status();
    $this->line(match ($status['state']) {
        'ok' => '<fg=green>ไฟล์ครบและตรงกับชุดที่ผู้พัฒนาส่งมา ('.$status['checked'].' ไฟล์)</>',
        'changed' => '<fg=yellow>มีไฟล์ถูกแก้ '.count($status['changed']).' ไฟล์ (ไม่ใช่ไฟล์วิกฤต ระบบยังใช้งานได้)</>',
        'tampered' => '<fg=red>ไฟล์วิกฤตถูกแก้ '.count($status['critical']).' ไฟล์ ระบบจะถูกล็อก</>',
        default => '<fg=yellow>ตรวจไม่ได้: '.$status['reason'].'</>',
    });
    foreach (array_merge($status['critical'], $status['changed']) as $f) {
        $this->line('  แก้ '.$f);
    }

    return $status['state'] === 'tampered' ? 1 : 0;
})->purpose('ตรวจว่าไฟล์ของระบบยังตรงกับชุดที่ผู้พัฒนาส่งมา');
