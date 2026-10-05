<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Notifier;
use App\Support\WalletReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * กระทบยอดกระเป๋าเงินทุกคืน: ยอดคงเหลือ สมุดรายการ รายการขาย รายการเติมเงิน และใบจ่ายเงินร้านค้าต้องตรงกัน
 * ไม่ตรง = ลง log และแจ้งผู้จัดการกระเป๋าเงินทาง LINE ผลล่าสุดแสดงบนหน้ากระเป๋าเงิน
 *
 *   php artisan wallet:reconcile
 */
class ReconcileWallets extends Command
{
    protected $signature = 'wallet:reconcile';

    protected $description = 'ตรวจว่ายอดคงเหลือของกระเป๋าเงินตรงกับสมุดรายการ รายการขาย และใบจ่ายเงินร้านค้า';

    public function handle(): int
    {
        $issues = WalletReconciler::run();
        if (! $issues) {
            $this->info('กระทบยอดแล้ว ตรงกันทั้งหมด');

            return self::SUCCESS;
        }

        foreach ($issues as $issue) {
            $this->error($issue);
        }
        Log::error('กระทบยอดกระเป๋าเงินไม่ตรง '.count($issues).' จุด', ['issues' => array_slice($issues, 0, 30)]);

        Notifier::viaQueue();
        $managers = User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->hasPermission('wallet.manage'));
        Notifier::users($managers, '⚠️ กระทบยอดกระเป๋าเงินพบ '.count($issues)." จุดที่ไม่ตรง เช่น\n".implode("\n", array_slice($issues, 0, 3)), route('wallets.index'));

        return self::FAILURE;
    }
}
