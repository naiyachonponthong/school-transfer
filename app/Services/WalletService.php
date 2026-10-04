<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\Student;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletSale;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * การเคลื่อนไหวของเงินในกระเป๋านักเรียนทั้งหมดผ่านที่นี่
 * ทุกครั้งล็อกแถวของกระเป๋า ตรวจยอด แล้วลงสมุดรายการในธุรกรรมเดียว ยอดคงเหลือจึงตรงกับสมุดรายการเสมอ
 */
class WalletService
{
    /** ยอดคงเหลือต่ำกว่านี้ แจ้งผู้ปกครองให้เติมเงิน */
    public const LOW_BALANCE = 20;

    public static function for(Student $student): Wallet
    {
        return Wallet::firstOrCreate(['student_id' => $student->id]);
    }

    /**
     * ลงรายการและปรับยอด (เรียกภายในธุรกรรมที่ล็อกกระเป๋าแล้วเท่านั้น)
     */
    private static function post(Wallet $wallet, string $type, float $amount, array $attributes = []): WalletTransaction
    {
        $balance = round((float) $wallet->balance + $amount, 2);
        if ($balance < 0) {
            throw new WalletException('ยอดเงินในกระเป๋าไม่พอ (คงเหลือ '.baht($wallet->balance).' บาท)');
        }
        $wallet->forceFill(['balance' => $balance])->save();

        return $wallet->transactions()->create(['type' => $type, 'amount' => $amount, 'balance_after' => $balance] + $attributes);
    }

    private static function locked(Wallet $wallet): Wallet
    {
        return Wallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * ตัดเงินค่าสินค้า
     *
     * @param  list<array{name: string, price: float, qty: int}>  $items
     * @param  string  $clientKey  รหัสของการกดขายครั้งนี้จากหน้าจอ (ส่งซ้ำได้ผลเดิม ไม่ตัดเงินซ้ำ)
     */
    public static function charge(Student $student, Shop $shop, array $items, User $cashier, string $clientKey): WalletSale
    {
        if ($existing = WalletSale::where('client_key', $clientKey)->first()) {
            return $existing;
        }
        $total = round(collect($items)->sum(fn ($i) => $i['price'] * $i['qty']), 2);
        if ($total <= 0) {
            throw new WalletException('ยอดขายต้องมากกว่า 0');
        }

        $sale = DB::transaction(function () use ($student, $shop, $items, $cashier, $clientKey, $total) {
            $wallet = self::locked(self::for($student));
            if ($wallet->is_frozen) {
                throw new WalletException('กระเป๋าเงินนี้ถูกระงับการใช้จ่าย');
            }
            if ($wallet->daily_limit !== null && $wallet->spentToday() + $total > (float) $wallet->daily_limit) {
                throw new WalletException('เกินวงเงินต่อวันที่ผู้ปกครองตั้งไว้ (ใช้ได้อีก '.baht(max(0, (float) $wallet->daily_limit - $wallet->spentToday())).' บาท)');
            }
            $sale = WalletSale::create(['shop_id' => $shop->id, 'wallet_id' => $wallet->id, 'total' => $total, 'items' => $items,
                'cashier_id' => $cashier->id, 'client_key' => $clientKey]);
            self::post($wallet, 'purchase', -$total, ['sale_id' => $sale->id, 'note' => $shop->name, 'created_by' => $cashier->id]);

            return $sale;
        });

        self::notifyPurchase($student, $sale->fresh(['wallet', 'shop']));

        return $sale;
    }

    private static function notifyPurchase(Student $student, WalletSale $sale): void
    {
        $nick = 'น้อง'.($student->nickname ?: $student->first_name);
        $wallet = $sale->wallet;
        $url = route('parent.wallet', $student);
        Notifier::parents($student, "🛒 {$nick} ซื้อ {$sale->itemsLabel()} ที่{$sale->shop->name} ".baht($sale->total).' บาท · คงเหลือ '.baht($wallet->balance).' บาท', $url);

        if ((float) $wallet->balance < self::LOW_BALANCE && ! $wallet->low_notified_on?->isToday()) {
            $wallet->forceFill(['low_notified_on' => today()])->save();
            Notifier::parents($student, "💰 เงินในกระเป๋าของ{$nick} เหลือ ".baht($wallet->balance).' บาท เติมเงินได้ในระบบ', $url);
        }
    }

    /** ยกเลิกการขาย: เงินคืนเข้ากระเป๋าเป็นรายการใหม่ (ไม่ลบรายการเดิม) */
    public static function void(WalletSale $sale, User $by, string $reason): void
    {
        DB::transaction(function () use ($sale, $by, $reason) {
            $sale = WalletSale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if ($sale->voided_at) {
                throw new WalletException('รายการนี้ถูกยกเลิกไปแล้ว');
            }
            $sale->update(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason]);
            self::post(self::locked($sale->wallet), 'void', (float) $sale->total, ['sale_id' => $sale->id, 'note' => 'ยกเลิก: '.$reason, 'created_by' => $by->id]);
        });
    }

    /** เติมเงินสดที่ห้องการเงิน เข้ากระเป๋าทันที */
    public static function topupCash(Student $student, float $amount, User $by, ?string $note = null): WalletTopup
    {
        return DB::transaction(function () use ($student, $amount, $by, $note) {
            $wallet = self::locked(self::for($student));
            $topup = $wallet->topups()->create(['amount' => $amount, 'method' => 'cash', 'status' => 'approved', 'note' => $note,
                'requested_by' => $by->id, 'reviewed_by' => $by->id, 'reviewed_at' => now()]);
            self::post($wallet, 'topup', $amount, ['topup_id' => $topup->id, 'note' => 'เงินสด', 'created_by' => $by->id]);

            return $topup;
        });
    }

    /** อนุมัติสลิปโอนเงิน เงินจึงเข้ากระเป๋า */
    public static function approve(WalletTopup $topup, User $by): void
    {
        DB::transaction(function () use ($topup, $by) {
            $topup = WalletTopup::whereKey($topup->id)->lockForUpdate()->firstOrFail();
            if ($topup->status !== 'pending') {
                throw new WalletException('รายการนี้ตรวจไปแล้ว');
            }
            $topup->update(['status' => 'approved', 'reviewed_by' => $by->id, 'reviewed_at' => now()]);
            self::post(self::locked($topup->wallet), 'topup', (float) $topup->amount, ['topup_id' => $topup->id, 'note' => 'โอน/พร้อมเพย์', 'created_by' => $by->id]);
        });
    }

    /** ปรับยอด (บวกหรือลบ) หรือถอนเงินคืนผู้ปกครอง ต้องมีเหตุผลเสมอ */
    public static function adjust(Student $student, string $type, float $amount, User $by, string $note): WalletTransaction
    {
        return DB::transaction(fn () => self::post(self::locked(self::for($student)), $type, $amount, ['note' => $note, 'created_by' => $by->id]));
    }
}
