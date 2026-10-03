<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Notifier;
use Illuminate\Console\Command;

/**
 * เตือนค่าธรรมเนียมทาง LINE: ก่อนครบกำหนด 3 วัน 1 ครั้ง และเมื่อเลยกำหนดทุก 7 วัน
 * ตั้งเวลาไว้ทุกวันใน routes/console.php — รันซ้ำในวันเดียวกันไม่ส่งซ้ำ
 */
class RemindFees extends Command
{
    public const DAYS_BEFORE = 3;

    public const REPEAT_DAYS = 7;

    protected $signature = 'fees:remind {--dry-run : แสดงจำนวนที่จะส่งโดยไม่ส่งจริง}';

    protected $description = 'เตือนผู้ปกครองเรื่องใบแจ้งหนี้ใกล้ครบกำหนด/เลยกำหนด';

    public function handle(): int
    {
        Notifier::viaQueue();
        $today = today();
        $sent = 0;

        $invoices = Invoice::with('student')
            ->whereIn('status', ['unpaid', 'partial'])->whereNotNull('due_date')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereDate('due_date', $today->copy()->addDays(self::DAYS_BEFORE))->whereNull('last_reminded_at'))
                ->orWhere(fn ($w) => $w->whereDate('due_date', '<', $today)
                    ->where(fn ($r) => $r->whereNull('last_reminded_at')->orWhere('last_reminded_at', '<=', $today->copy()->subDays(self::REPEAT_DAYS)->endOfDay()))))
            ->get();

        foreach ($invoices as $inv) {
            if ($inv->student->status !== 'active') {
                continue;
            }
            $sent++;
            if ($this->option('dry-run')) {
                continue;
            }
            $nick = 'น้อง'.($inv->student->nickname ?: $inv->student->first_name);
            Notifier::parents($inv->student, ($inv->isOverdue()
                ? "⏰ เลยกำหนดชำระ: {$inv->title} ของ{$nick} ค้าง ".baht($inv->balance()).' บาท (กำหนด '.thai_date($inv->due_date).')'
                : "🧾 ใกล้ครบกำหนดชำระ: {$inv->title} ของ{$nick} ยอด ".baht($inv->balance()).' บาท ภายใน '.thai_date($inv->due_date))
                .' ชำระผ่าน QR พร้อมเพย์และแนบสลิปได้ในระบบ', route('invoices.show', $inv));
            // ไม่ผ่าน model event: การเตือนไม่ใช่การแก้ไขใบแจ้งหนี้ ไม่ต้องลงประวัติการใช้งาน
            Invoice::whereKey($inv->id)->update(['last_reminded_at' => now()]);
        }

        $this->info(($this->option('dry-run') ? 'จะเตือน ' : 'เตือนแล้ว ')."{$sent} ใบ (ส่งถึงเฉพาะผู้ปกครองที่เชื่อม LINE)");

        return self::SUCCESS;
    }
}
