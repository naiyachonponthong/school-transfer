<?php

namespace App\Console\Commands;

use App\Support\DbBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

/**
 * สำรองฐานข้อมูลด้วย mysqldump (ไม่มี mysqldump = PHP ล้วนเป็น .sql.gz, SQLite = คัดลอกไฟล์ฐานข้อมูล) เก็บไว้ที่ storage/app/backups
 * เก็บย้อนหลังตามจำนวนวันที่กำหนด (ค่าเริ่มต้น 14 วัน) ไฟล์เก่ากว่านั้นลบทิ้งอัตโนมัติ
 *
 *   php artisan backup:database
 *   php artisan backup:database --keep-days=30
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep-days=14 : ลบไฟล์สำรองที่เก่ากว่านี้ (วัน)}';

    protected $description = 'สำรองฐานข้อมูล (MySQL เป็น .sql หรือ .sql.gz / SQLite เป็นสำเนาไฟล์)';

    public function handle(): int
    {
        $conn = config('database.default');
        $dir = DbBackup::dir();

        if ($conn === 'sqlite') {
            return $this->backupSqlite($dir);
        }
        if ($conn !== 'mysql') {
            $this->warn("การเชื่อมต่อฐานข้อมูลปัจจุบันคือ \"{$conn}\" คำสั่งนี้รองรับเฉพาะ MySQL และ SQLite");

            return self::FAILURE;
        }
        $db = config('database.connections.mysql');

        // shared hosting มักไม่มี mysqldump หรือปิดการสั่งโปรแกรมภายนอก: สำรองด้วย PHP ล้วนเป็น .sql.gz แทน
        $mysqldump = self::canRunPrograms() ? self::findBinary('mysqldump') : null;
        if (! $mysqldump) {
            return $this->backupWithPhp($dir, $db['database']);
        }

        $file = self::freshName($dir, $db['database'], 'sql');
        $process = new Process([
            $mysqldump,
            '--host='.$db['host'], '--port='.(string) $db['port'], '--user='.$db['username'],
            '--single-transaction', '--quick', '--default-character-set=utf8mb4',
            $db['database'],
        ], null, $db['password'] !== '' ? ['MYSQL_PWD' => $db['password']] : null);
        $process->setTimeout(300);
        try {
            $process->run(function ($type, $buffer) use ($file) {
                if ($type === Process::OUT) {
                    File::append($file, $buffer);
                }
            });
        } catch (\Throwable) {
            // สั่งโปรแกรมไม่ได้ (เช่น proc_open ถูกปิด) ไปใช้ทาง PHP
        }

        if (! $process->isSuccessful() || ! File::exists($file) || File::size($file) === 0) {
            File::exists($file) && File::delete($file);
            $this->warn('mysqldump ใช้ไม่ได้ ('.trim((string) $process->getErrorOutput()).') สำรองด้วย PHP แทน');

            return $this->backupWithPhp($dir, $db['database']);
        }

        $this->info('สำรองข้อมูลแล้ว: '.$file.' ('.number_format(File::size($file) / 1024, 0).' KB)');
        $this->pruneDatabaseBackups($dir);

        return self::SUCCESS;
    }

    private function backupWithPhp(string $dir, string $database): int
    {
        $file = self::freshName($dir, $database, 'sql.gz');
        try {
            DbBackup::create($file);
        } catch (\Throwable $e) {
            $this->error('สำรองข้อมูลไม่สำเร็จ: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->info('สำรองข้อมูลแล้ว: '.$file.' ('.number_format(File::size($file) / 1024, 0).' KB)');
        $this->pruneDatabaseBackups($dir);

        return self::SUCCESS;
    }

    private function pruneDatabaseBackups(string $dir): void
    {
        foreach (['*.sql', '*.sql.gz'] as $pattern) {
            self::prune($this, $dir.'/'.$pattern, (int) $this->option('keep-days'));
        }
    }

    /** โฮสต์อนุญาตให้สั่งโปรแกรมภายนอก (proc_open) หรือไม่ */
    public static function canRunPrograms(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        return function_exists('proc_open') && ! in_array('proc_open', $disabled, true);
    }

    private function backupSqlite(string $dir): int
    {
        $source = config('database.connections.sqlite.database');
        if (! $source || $source === ':memory:' || ! File::exists($source)) {
            $this->error('ไม่พบไฟล์ฐานข้อมูล SQLite');

            return self::FAILURE;
        }
        $file = self::freshName($dir, pathinfo($source, PATHINFO_FILENAME), 'sqlite');
        // ใช้ VACUUM INTO ได้สำเนาที่สอดคล้องกันแม้มีคนใช้งานอยู่ (ถ้าไม่รองรับค่อยคัดลอกไฟล์ตรง ๆ)
        try {
            DB::connection('sqlite')->statement('VACUUM INTO ?', [$file]);
        } catch (\Throwable) {
            File::copy($source, $file);
        }
        $this->info('สำรองข้อมูลแล้ว: '.$file.' ('.number_format(File::size($file) / 1024, 0).' KB)');
        self::prune($this, $dir.'/*.sqlite', (int) $this->option('keep-days'));

        return self::SUCCESS;
    }

    /** ชื่อไฟล์สำรองที่ยังไม่มีอยู่ (สำรองซ้ำในวินาทีเดียวกัน เช่น ก่อนกู้คืน ต้องไม่ทับไฟล์เดิม) */
    public static function freshName(string $dir, string $prefix, string $ext): string
    {
        $base = $dir.'/'.$prefix.'-'.now()->format('Ymd-His');
        $file = "{$base}.{$ext}";
        for ($i = 2; File::exists($file); $i++) {
            $file = "{$base}-{$i}.{$ext}";
        }

        return $file;
    }

    /** ลบไฟล์สำรองที่เก่ากว่าจำนวนวันที่กำหนด */
    public static function prune(Command $cmd, string $pattern, int $keepDays): void
    {
        $cutoff = now()->subDays($keepDays)->timestamp;
        $removed = 0;
        foreach (File::glob($pattern) as $old) {
            if (File::lastModified($old) < $cutoff) {
                File::delete($old);
                $removed++;
            }
        }
        if ($removed) {
            $cmd->comment("ลบไฟล์สำรองเก่ากว่า {$keepDays} วันแล้ว {$removed} ไฟล์");
        }
    }

    public static function findBinary(string $name): ?string
    {
        $envKey = strtoupper($name).'_PATH';
        if ($path = env($envKey)) {
            return $path;
        }
        // Laragon เก็บ mysqldump ไว้ในโฟลเดอร์เดียวกับ mysql server ไม่ได้อยู่ใน PATH เสมอไป
        foreach (glob('C:/laragon/bin/mysql/*/bin/'.$name.'.exe') as $candidate) {
            return $candidate;
        }
        if (! function_exists('shell_exec')) {
            return null;
        }
        $which = trim((string) @shell_exec((str_starts_with(PHP_OS, 'WIN') ? 'where ' : 'which ').$name.' 2>NUL'));

        return $which !== '' ? strtok($which, "\r\n") : null;
    }
}
