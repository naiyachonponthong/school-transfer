<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * สำรองฐานข้อมูลด้วย mysqldump เก็บไว้ที่ storage/app/backups
 * เก็บย้อนหลังตามจำนวนวันที่กำหนด (ค่าเริ่มต้น 14 วัน) ไฟล์เก่ากว่านั้นลบทิ้งอัตโนมัติ
 *
 *   php artisan backup:database
 *   php artisan backup:database --keep-days=30
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep-days=14 : ลบไฟล์สำรองที่เก่ากว่านี้ (วัน)}';

    protected $description = 'สำรองฐานข้อมูล MySQL เป็นไฟล์ .sql';

    public function handle(): int
    {
        $conn = config('database.default');
        if ($conn !== 'mysql') {
            $this->warn("การเชื่อมต่อฐานข้อมูลปัจจุบันคือ \"{$conn}\" คำสั่งนี้รองรับเฉพาะ MySQL");

            return self::FAILURE;
        }
        $db = config('database.connections.mysql');

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $file = $dir.'/'.$db['database'].'-'.now()->format('Ymd-His').'.sql';

        $mysqldump = $this->findBinary('mysqldump');
        if (! $mysqldump) {
            $this->error('ไม่พบโปรแกรม mysqldump กรุณาติดตั้ง MySQL client หรือระบุ path ด้วย env MYSQLDUMP_PATH');

            return self::FAILURE;
        }

        $process = new Process([
            $mysqldump,
            '--host='.$db['host'], '--port='.(string) $db['port'], '--user='.$db['username'],
            '--single-transaction', '--quick', '--default-character-set=utf8mb4',
            $db['database'],
        ], null, $db['password'] !== '' ? ['MYSQL_PWD' => $db['password']] : null);
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) use ($file) {
            if ($type === Process::OUT) {
                File::append($file, $buffer);
            }
        });

        if (! $process->isSuccessful() || ! File::exists($file) || File::size($file) === 0) {
            File::exists($file) && File::delete($file);
            $this->error('สำรองข้อมูลไม่สำเร็จ: '.trim($process->getErrorOutput()));

            return self::FAILURE;
        }

        $this->info('สำรองข้อมูลแล้ว: '.$file.' ('.number_format(File::size($file) / 1024, 0).' KB)');

        $cutoff = now()->subDays((int) $this->option('keep-days'));
        $removed = 0;
        foreach (File::glob($dir.'/*.sql') as $old) {
            if (File::lastModified($old) < $cutoff->timestamp) {
                File::delete($old);
                $removed++;
            }
        }
        if ($removed) {
            $this->comment("ลบไฟล์สำรองเก่ากว่า {$this->option('keep-days')} วันแล้ว {$removed} ไฟล์");
        }

        return self::SUCCESS;
    }

    private function findBinary(string $name): ?string
    {
        $envKey = strtoupper($name).'_PATH';
        if ($path = env($envKey)) {
            return $path;
        }
        // Laragon เก็บ mysqldump ไว้ในโฟลเดอร์เดียวกับ mysql server ไม่ได้อยู่ใน PATH เสมอไป
        foreach (glob('C:/laragon/bin/mysql/*/bin/'.$name.'.exe') as $candidate) {
            return $candidate;
        }
        $which = trim((string) shell_exec((str_starts_with(PHP_OS, 'WIN') ? 'where ' : 'which ').$name.' 2>NUL'));

        return $which !== '' ? strtok($which, "\r\n") : null;
    }
}
