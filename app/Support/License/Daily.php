<?php

namespace App\Support\License;

use App\Support\Settings;
use Illuminate\Support\Facades\Log;

/**
 * งาน License รายวัน (php artisan license:daily ตั้งเวลาไว้ใน routes/console.php ใช้ได้ทั้ง cron จริงและ WebCron)
 * - ตรวจ License กับศูนย์ล่วงหน้า ไม่ต้องรอให้หน้าเว็บตรวจเอง
 * - สแกนไฟล์ทั้งชุด ถ้ามีไฟล์ถูกแก้ แจ้งศูนย์ License (แจ้งซ้ำเฉพาะเมื่อรายการไฟล์เปลี่ยน)
 */
class Daily
{
    public static function run(): void
    {
        try {
            if ((string) Settings::get('license.key', '') !== '') {
                Client::refresh();
            }
            Guard::flush();
        } catch (\Throwable $e) {
            Log::warning('daily license check failed: '.$e->getMessage());
        }

        try {
            self::reportChangedFiles();
        } catch (\Throwable $e) {
            Log::warning('daily integrity check failed: '.$e->getMessage());
        }
    }

    public static function reportChangedFiles(): void
    {
        Integrity::flush();
        $status = Integrity::status();
        $files = array_merge($status['critical'], $status['changed']);

        if (! $files) {
            Settings::forget(['license.reported_files']);

            return;
        }

        // รายการเดิมที่เคยแจ้งไปแล้ว ไม่ต้องแจ้งซ้ำทุกวัน
        $fingerprint = md5(implode('|', $files));
        if (Settings::get('license.reported_files') === $fingerprint) {
            return;
        }

        if (Client::report($files)) {
            Settings::set(['license.reported_files' => $fingerprint]);
        }

        Log::warning('ไฟล์ของระบบถูกแก้ '.count($files).' ไฟล์: '.implode(', ', array_slice($files, 0, 10)));
    }
}
