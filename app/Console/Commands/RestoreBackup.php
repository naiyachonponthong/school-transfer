<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * กู้คืนจากไฟล์สำรองใน storage/app/backups (หรือระบุ path เต็ม)
 * - .sql    → นำเข้า MySQL (ทับข้อมูลปัจจุบันทั้งหมด)
 * - .sqlite → แทนที่ไฟล์ฐานข้อมูล SQLite
 * - .zip    → แตกไฟล์อัปโหลดกลับไปที่ storage/app (ทับไฟล์ชื่อเดียวกัน)
 * ก่อนกู้ฐานข้อมูล ระบบสำรองข้อมูลปัจจุบันไว้ก่อนเสมอ เผื่อกู้ผิดไฟล์
 *
 *   php artisan backup:restore                 (เลือกจากรายการ)
 *   php artisan backup:restore school-20261003-020000.sql
 */
class RestoreBackup extends Command
{
    protected $signature = 'backup:restore {file? : ชื่อไฟล์ใน storage/app/backups หรือ path เต็ม} {--force : ไม่ต้องถามยืนยัน}';

    protected $description = 'กู้คืนฐานข้อมูลหรือไฟล์อัปโหลดจากไฟล์สำรอง';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        $file = $this->argument('file');
        if (! $file) {
            $files = collect(['sql', 'sqlite', 'zip'])->flatMap(fn ($ext) => File::glob($dir.'/*.'.$ext))->sortByDesc(fn ($f) => File::lastModified($f))->map(fn ($f) => basename($f))->values();
            if ($files->isEmpty()) {
                $this->error('ไม่พบไฟล์สำรองใน '.$dir);

                return self::FAILURE;
            }
            $file = $this->choice('เลือกไฟล์ที่จะกู้คืน', $files->all(), 0);
        }
        $path = File::exists($file) ? $file : $dir.'/'.basename($file);
        if (! File::exists($path)) {
            $this->error('ไม่พบไฟล์: '.$file);

            return self::FAILURE;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $what = $ext === 'zip' ? 'ไฟล์อัปโหลด (ทับไฟล์ชื่อเดียวกัน)' : 'ฐานข้อมูลทั้งหมด (ข้อมูลปัจจุบันจะถูกแทนที่)';
        if (! $this->option('force') && ! $this->confirm("กู้คืน{$what} จาก ".basename($path).' ?')) {
            return self::FAILURE;
        }

        return match ($ext) {
            'zip' => $this->restoreFiles($path),
            'sql', 'sqlite' => $this->restoreDatabase($path, $ext),
            default => tap(self::FAILURE, fn () => $this->error('รองรับเฉพาะไฟล์ .sql .sqlite .zip')),
        };
    }

    private function restoreDatabase(string $path, string $ext): int
    {
        $conn = config('database.default');
        if (($ext === 'sql') !== ($conn === 'mysql')) {
            $this->error("ไฟล์ .{$ext} ใช้กับฐานข้อมูล \"{$conn}\" ปัจจุบันไม่ได้");

            return self::FAILURE;
        }

        $this->comment('สำรองข้อมูลปัจจุบันไว้ก่อน...');
        if (Artisan::call('backup:database', ['--keep-days' => 3650]) !== self::SUCCESS) {
            $this->error('สำรองข้อมูลปัจจุบันไม่สำเร็จ จึงยังไม่กู้คืน: '.trim(Artisan::output()));

            return self::FAILURE;
        }

        if ($ext === 'sqlite') {
            $target = config('database.connections.sqlite.database');
            DB::disconnect('sqlite');
            File::copy($path, $target);
        } else {
            $db = config('database.connections.mysql');
            $mysql = BackupDatabase::findBinary('mysql');
            if (! $mysql) {
                $this->error('ไม่พบโปรแกรม mysql กรุณาติดตั้ง MySQL client หรือระบุ path ด้วย env MYSQL_PATH');

                return self::FAILURE;
            }
            $process = new Process([$mysql, '--host='.$db['host'], '--port='.(string) $db['port'], '--user='.$db['username'], '--default-character-set=utf8mb4', $db['database']],
                null, $db['password'] !== '' ? ['MYSQL_PWD' => $db['password']] : null);
            $process->setInput(fopen($path, 'r'));
            $process->setTimeout(1800);
            $process->run();
            if (! $process->isSuccessful()) {
                $this->error('กู้คืนไม่สำเร็จ: '.trim($process->getErrorOutput()));

                return self::FAILURE;
            }
        }
        Artisan::call('optimize:clear');
        $this->info('กู้คืนฐานข้อมูลเรียบร้อย จาก '.basename($path));

        return self::SUCCESS;
    }

    private function restoreFiles(string $path): int
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            $this->error('เปิดไฟล์ zip ไม่ได้');

            return self::FAILURE;
        }
        $count = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // แตกเฉพาะโฟลเดอร์ที่สำรองไว้ และกันชื่อไฟล์ที่พยายามออกนอกโฟลเดอร์ (../)
            if (str_contains($name, '..') || ! in_array(strtok($name, '/'), BackupFiles::SOURCES, true) || str_ends_with($name, '/')) {
                continue;
            }
            $target = storage_path('app/'.$name);
            File::ensureDirectoryExists(dirname($target));
            File::put($target, $zip->getFromIndex($i));
            $count++;
        }
        $zip->close();
        $this->info("กู้คืนไฟล์อัปโหลดแล้ว {$count} ไฟล์");

        return self::SUCCESS;
    }
}
