<?php

namespace App\Console\Commands;

use App\Models\BookLoan;
use App\Services\Notifier;
use Illuminate\Console\Command;

class RemindOverdueBooks extends Command
{
    protected $signature = 'library:remind-overdue';

    protected $description = 'เตือนผู้ปกครองของนักเรียนที่มีหนังสือเกินกำหนดคืนทาง LINE';

    public function handle(): int
    {
        Notifier::viaQueue();
        $this->info('ส่งแจ้งเตือนผู้ปกครอง '.BookLoan::notifyOverdue().' คน (เฉพาะคนที่เชื่อม LINE)');

        return self::SUCCESS;
    }
}
