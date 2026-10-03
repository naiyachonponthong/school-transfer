<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
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

// ส่งข้อความ LINE ที่ค้างในคิว (งานจำนวนมาก/การส่งซ้ำ) — ถ้ารัน `queue:work` ค้างไว้อยู่แล้ว บรรทัดนี้ไม่มีผลเสีย
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')->everyMinute()->withoutOverlapping(5);
