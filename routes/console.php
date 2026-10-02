<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// สำรองฐานข้อมูลทุกวันตอนตี 2 (ต้องรัน `php artisan schedule:run` ทุกนาทีผ่าน cron/Task Scheduler จึงจะทำงาน — ดู README)
Schedule::command('backup:database')->dailyAt('02:00')->withoutOverlapping();
