<?php

namespace App\Console\Commands;

use App\Http\Controllers\FileController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * ย้ายไฟล์แนบที่อัปโหลดไว้ก่อนหน้านี้ (สลิป ใบรับรองแพทย์ รูปแชท งานที่ส่ง)
 * จากดิสก์ public ไปดิสก์ส่วนตัว ลิงก์ /storage/... เดิมจะเปิดไม่ได้อีก
 *
 *   php artisan files:privatize
 *   php artisan files:privatize --dry-run
 */
class PrivatizeFiles extends Command
{
    protected $signature = 'files:privatize {--dry-run : แสดงจำนวนไฟล์ที่จะย้ายโดยไม่ย้ายจริง}';

    protected $description = 'ย้ายไฟล์แนบที่มีข้อมูลส่วนบุคคลออกจากดิสก์ public';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $local = Storage::disk('local');
        $moved = 0;

        foreach (FileController::TYPES as $type => [$class, $column]) {
            $count = 0;
            foreach ($class::whereNotNull($column)->pluck($column) as $path) {
                if (! $public->exists($path)) {
                    continue;
                }
                if (! $this->option('dry-run')) {
                    $local->put($path, $public->readStream($path));
                    $public->delete($path);
                }
                $count++;
            }
            $this->line(sprintf('%-12s %d ไฟล์', $type, $count));
            $moved += $count;
        }

        $this->info(($this->option('dry-run') ? 'จะย้าย ' : 'ย้ายแล้ว ')."{$moved} ไฟล์");

        return self::SUCCESS;
    }
}
