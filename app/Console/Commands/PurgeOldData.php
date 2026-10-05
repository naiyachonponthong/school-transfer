<?php

namespace App\Console\Commands;

use App\Models\Admission;
use App\Models\AuditLog;
use App\Models\ExamResponse;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * ลบข้อมูลส่วนบุคคลที่พ้นระยะเวลาเก็บ (PDPA: เก็บเท่าที่จำเป็น)
 * รันเองทุกเดือนเมื่อเปิดไว้ในหน้าตั้งค่า (ค่าเริ่มต้น = ปิด) หรือผู้ดูแลระบบรันเองได้ทุกเมื่อ ควรดู --dry-run ก่อนเสมอ
 *
 *   php artisan privacy:purge --dry-run
 *   php artisan privacy:purge --admission-years=2 --scan-years=2 --log-years=3
 */
class PurgeOldData extends Command
{
    protected $signature = 'privacy:purge
        {--admission-years=2 : ลบใบสมัครที่ไม่ได้มอบตัวและเก่ากว่านี้ (ปี) พร้อมไฟล์แนบ}
        {--scan-years=2 : ลบภาพสแกนกระดาษคำตอบที่เก่ากว่านี้ (ปี) คะแนนยังอยู่}
        {--log-years=3 : ลบประวัติการแก้ไขที่เก่ากว่านี้ (ปี)}
        {--dry-run : แสดงจำนวนที่จะลบโดยไม่ลบจริง}
        {--scheduled : รันตามเวลา (ทำเฉพาะเมื่อเปิดการลบอัตโนมัติในหน้าตั้งค่า)}';

    protected $description = 'ลบข้อมูลส่วนบุคคลที่พ้นระยะเวลาเก็บ (ใบสมัครที่ไม่มอบตัว ภาพสแกนข้อสอบ ประวัติการใช้งาน)';

    public function handle(): int
    {
        if ($this->option('scheduled') && \App\Support\Settings::get('privacy_purge_auto') !== '1') {
            return self::SUCCESS;
        }
        $dry = (bool) $this->option('dry-run');
        $disk = Storage::disk('local');

        // 1) ใบสมัครที่ไม่ได้เป็นนักเรียน
        $admissions = Admission::where('status', '!=', 'enrolled')->whereNull('student_id')
            ->where('created_at', '<', now()->subYears(max(1, (int) $this->option('admission-years'))))->get();
        if (! $dry) {
            foreach ($admissions as $a) {
                foreach (array_filter([$a->document, $a->fee_slip]) as $path) {
                    $disk->delete($path);
                }
                foreach ((array) $a->answers as $answer) {
                    if (is_array($answer) && isset($answer['value']['path'])) {
                        $disk->delete($answer['value']['path']);
                    } elseif (is_array($answer) && isset($answer['path'])) {
                        $disk->delete($answer['path']);
                    }
                }
                $a->delete();
            }
        }
        $this->line(sprintf('ใบสมัครที่ไม่ได้มอบตัว: %d ใบ', $admissions->count()));

        // 2) ภาพสแกนกระดาษคำตอบ (เก็บผลคะแนนไว้ ลบเฉพาะภาพ)
        $scans = ExamResponse::whereNotNull('image')->where('created_at', '<', now()->subYears(max(1, (int) $this->option('scan-years'))));
        $scanCount = (clone $scans)->count();
        if (! $dry) {
            (clone $scans)->pluck('image')->each(fn ($path) => $disk->delete($path));
            (clone $scans)->update(['image' => null]);
        }
        $this->line(sprintf('ภาพสแกนกระดาษคำตอบ: %d ภาพ', $scanCount));

        // 3) ประวัติการใช้งาน
        $logs = AuditLog::where('created_at', '<', now()->subYears(max(1, (int) $this->option('log-years'))));
        $logCount = (clone $logs)->count();
        if (! $dry) {
            $logs->delete();
        }
        $this->line(sprintf('ประวัติการแก้ไข: %d รายการ', $logCount));

        $this->info($dry ? 'ทดลองเท่านั้น ยังไม่ได้ลบอะไร' : 'ลบเรียบร้อย');

        return self::SUCCESS;
    }
}
