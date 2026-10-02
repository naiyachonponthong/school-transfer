<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * สำรองไฟล์ที่ผู้ใช้อัปโหลด (รูปนักเรียน สลิป ใบรับรองแพทย์ ภาพกระดาษคำตอบ ฯลฯ)
 * จาก storage/app/public และ storage/app/private เป็นไฟล์ .zip ใน storage/app/backups
 *
 *   php artisan backup:files
 *   php artisan backup:files --keep-days=30
 */
class BackupFiles extends Command
{
    protected $signature = 'backup:files {--keep-days=14 : ลบไฟล์สำรองที่เก่ากว่านี้ (วัน)}';

    protected $description = 'สำรองไฟล์ที่อัปโหลดทั้งหมดเป็น .zip';

    /** โฟลเดอร์ใน storage/app ที่สำรอง */
    public const SOURCES = ['public', 'private'];

    public function handle(): int
    {
        if (! class_exists(ZipArchive::class)) {
            $this->error('ต้องเปิดส่วนขยาย zip ของ PHP (extension=zip ใน php.ini)');

            return self::FAILURE;
        }
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);
        $file = BackupDatabase::freshName($dir, 'files', 'zip');

        $zip = new ZipArchive;
        if ($zip->open($file, ZipArchive::CREATE) !== true) {
            $this->error('สร้างไฟล์ zip ไม่ได้: '.$file);

            return self::FAILURE;
        }
        $count = 0;
        foreach (self::SOURCES as $source) {
            $root = storage_path('app/'.$source);
            if (! File::isDirectory($root)) {
                continue;
            }
            foreach (File::allFiles($root) as $f) {
                if ($f->getFilename() === '.gitignore') {
                    continue;
                }
                $zip->addFile($f->getPathname(), $source.'/'.str_replace('\\', '/', $f->getRelativePathname()));
                $count++;
            }
        }
        // zip ว่างจะไม่ถูกเขียนลงดิสก์ ใส่ไฟล์บอกเวลาไว้เสมอ
        $zip->addFromString('BACKUP-INFO.txt', 'สำรองเมื่อ '.now()->toDateTimeString()." จำนวน {$count} ไฟล์\n");
        $zip->close();

        $this->info("สำรองไฟล์แล้ว {$count} ไฟล์: {$file} (".number_format(File::size($file) / 1024, 0).' KB)');
        BackupDatabase::prune($this, $dir.'/files-*.zip', (int) $this->option('keep-days'));

        return self::SUCCESS;
    }
}
