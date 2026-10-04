<?php

namespace App\Console\Commands;

use App\Support\Demo;
use Illuminate\Console\Command;

class DemoReset extends Command
{
    protected $signature = 'demo:reset {--snapshot : บันทึกข้อมูลปัจจุบันเป็นต้นแบบ แทนการคืนค่า} {--scheduled : รันตามเวลา (ทำเฉพาะเมื่อเปิดโหมดทดลองใช้และเปิดคืนค่าอัตโนมัติ)}';

    protected $description = 'โหมดทดลองใช้: คืนข้อมูลทั้งระบบกลับเป็นต้นแบบที่บันทึกไว้';

    public function handle(): int
    {
        if ($this->option('snapshot')) {
            $this->info('บันทึกต้นแบบแล้ว '.Demo::snapshot().' แถว');

            return self::SUCCESS;
        }
        if ($this->option('scheduled') && (! Demo::enabled() || \App\Support\Settings::get('demo_reset') !== '1' || ! Demo::snapshotAt())) {
            return self::SUCCESS;
        }

        try {
            $this->info('คืนข้อมูลเป็นต้นแบบแล้ว '.Demo::reset().' แถว');
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
