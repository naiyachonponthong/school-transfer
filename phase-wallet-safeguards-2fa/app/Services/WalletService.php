<?php

namespace App\Services;

use App\Models\Shop;
use App\Models\ShopProduct;
use App\Models\ShopSettlement;
use App\Models\Student;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletSale;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * การเคลื่อนไหวของเงินในกระเป๋า (ของนักเรียน หรือของครู/บุคลากร) ทั้งหมดผ่านที่นี่
 * ทุกครั้งล็อกแถวของกระเป๋า ตรวจยอด แล้วลงสมุดรายการในธุรกรรมเดียว ยอดคงเหลือจึงตรงกับสมุดรายการเสมอ
 */
class WalletService
{
    /** ยอดคงเหลือต่ำกว่านี้ แจ้งให้เติมเงิน */
    public const LOW_BALANCE = 20;

    public static function for(Student|User $owner): Wallet
    {
        return Wallet::firstOrCreate($owner instanceof Student ? ['student_id' => $owner->id] : ['user_id' => $owner->id]);
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

    private static function total(array $items): float
    {
        $total = round(collect($items)->sum(fn ($i) => $i['price'] * $i['qty']), 2);
        if ($total <= 0) {
            throw new WalletException('ยอดขายต้องมากกว่า 0');
        }

        return $total;
    }

    /** ตัดสต็อกของสินค้าที่นับสต็อก (เรียกภายในธุรกรรม) */
    private static function takeStock(array $items): void
    {
        foreach ($items as $item) {
            if (empty($item['product_id'])) {
                continue;
            }
            $product = ShopProduct::whereKey($item['product_id'])->lockForUpdate()->first();
            if ($product && $product->stock !== null) {
                if ($product->stock < $item['qty']) {
                    throw new WalletException("{$product->name} เหลือ {$product->stock} ".($product->unit ?: 'ชิ้น').' ไม่พอขาย');
                }
                $product->decrement('stock', $item['qty']);
            }
        }
    }

    /**
     * ตัดเงินค่าสินค้าจากกระเป๋า (และตัดสต็อกของสินค้าที่นับสต็อก)
     *
     * @param  list<array{name: string, price: float, qty: int, product_id?: int|null}>  $items
     * @param  string  $clientKey  รหัสของการกดขายครั้งนี้จากหน้าจอ (ส่งซ้ำได้ผลเดิม ไม่ตัดเงินซ้ำ)
     */
    public static function charge(Student|User $owner, Shop $shop, array $items, User $cashier, string $clientKey): WalletSale
    {
        if ($existing = WalletSale::where('client_key', $clientKey)->first()) {
            return $existing;
        }
        $total = self::total($items);

        $sale = DB::transaction(function () use ($owner, $shop, $items, $cashier, $clientKey, $total) {
            $wallet = self::locked(self::for($owner));
            if ($wallet->is_frozen) {
                throw new WalletException('กระเป๋าเงินนี้ถูกระงับการใช้จ่าย');
            }
            if ($wallet->daily_limit !== null && $wallet->spentToday() + $total > (float) $wallet->daily_limit) {
                throw new WalletException('เกินวงเงินต่อวันที่ตั้งไว้ (ใช้ได้อีก '.baht(max(0, (float) $wallet->daily_limit - $wallet->spentToday())).' บาท)');
            }
            self::takeStock($items);
            $sale = WalletSale::create(['shop_id' => $shop->id, 'wallet_id' => $wallet->id, 'total' => $total, 'items' => $items,
                'cashier_id' => $cashier->id, 'client_key' => $clientKey, 'payment' => 'wallet']);
            self::post($wallet, 'purchase', -$total, ['sale_id' => $sale->id, 'note' => $shop->name, 'created_by' => $cashier->id]);

            return $sale;
        });

        self::notifyPurchase($owner, $sale->fresh(['wallet', 'shop']));

        return $sale;
    }

    /** แจ้งผู้ปกครอง (กระเป๋านักเรียน) หรือเจ้าตัว (กระเป๋าครู) */
    public static function notify(Student|User $owner, string $text): void
    {
        $owner instanceof Student
            ? Notifier::parents($owner, $text, route('parent.wallet', $owner))
            : Notifier::users([$owner], $text, route('wallet.mine'));
    }

    private static function notifyPurchase(Student|User $owner, WalletSale $sale): void
    {
        $wallet = $sale->wallet;
        $who = $owner instanceof Student ? 'น้อง'.($owner->nickname ?: $owner->first_name) : 'คุณ';
        self::notify($owner, "🛒 {$who} ซื้อ {$sale->itemsLabel()} ที่{$sale->shop->name} ".baht($sale->total).' บาท · คงเหลือ '.baht($wallet->balance).' บาท');

        if ((float) $wallet->balance < self::LOW_BALANCE && ! $wallet->low_notified_on?->isToday()) {
            $wallet->forceFill(['low_notified_on' => today()])->save();
            self::notify($owner, '💰 เงินในกระเป๋า'.($owner instanceof Student ? "ของ{$who}" : '')." เหลือ ".baht($wallet->balance).' บาท เติมเงินได้ในระบบ');
        }
    }

    /** ยกเลิกการขาย: เงินคืนเข้ากระเป๋าเป็นรายการใหม่ (ไม่ลบรายการเดิม) และคืนสต็อก */
    public static function void(WalletSale $sale, User $by, string $reason): void
    {
        DB::transaction(function () use ($sale, $by, $reason) {
            $sale = WalletSale::whereKey($sale->id)->lockForUpdate()->firstOrFail();
            if ($sale->voided_at) {
                throw new WalletException('รายการนี้ถูกยกเลิกไปแล้ว');
            }
            // เงินของรายการนี้จ่ายให้ร้านไปแล้ว คืนเข้ากระเป๋าอีกจะทำให้เงินออกสองทาง
            if ($sale->settlement_id) {
                throw new WalletException("รายการนี้อยู่ในใบจ่ายเงินให้ร้าน เลขที่ {$sale->settlement?->doc_no} แล้ว ต้องยกเลิกใบจ่ายเงินนั้นก่อนจึงยกเลิกรายการขายได้");
            }
            $sale->update(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason]);
            foreach ($sale->items as $item) {
                if (! empty($item['product_id'])) {
                    ShopProduct::whereKey($item['product_id'])->whereNotNull('stock')->increment('stock', (int) $item['qty']);
                }
            }
            if ($sale->wallet_id) {
                self::post(self::locked($sale->wallet), 'void', (float) $sale->total, ['sale_id' => $sale->id, 'note' => 'ยกเลิก: '.$reason, 'created_by' => $by->id]);
            }
        });
    }

    /** เติมเงินสดที่ห้องการเงิน เข้ากระเป๋าทันที */
    public static function topupCash(Student|User $owner, float $amount, User $by, ?string $note = null): WalletTopup
    {
        return DB::transaction(function () use ($owner, $amount, $by, $note) {
            $wallet = self::locked(self::for($owner));
            $topup = $wallet->topups()->create(['amount' => $amount, 'method' => 'cash', 'status' => 'approved', 'note' => $note,
                'requested_by' => $by->id, 'reviewed_by' => $by->id, 'reviewed_at' => now()]);
            self::post($wallet, 'topup', $amount, ['topup_id' => $topup->id, 'note' => 'เงินสด', 'created_by' => $by->id]);

            return $topup;
        });
    }

    /** อนุมัติรายการเติมเงินที่รออยู่ เงินจึงเข้ากระเป๋า ($by ว่าง = ธนาคารแจ้งเข้ามาเอง) */
    public static function approve(WalletTopup $topup, ?User $by = null, ?string $gatewayTxn = null): void
    {
        DB::transaction(function () use ($topup, $by, $gatewayTxn) {
            $topup = WalletTopup::whereKey($topup->id)->lockForUpdate()->firstOrFail();
            if ($topup->status !== 'pending') {
                throw new WalletException('รายการนี้ตรวจไปแล้ว');
            }
            $topup->update(['status' => 'approved', 'reviewed_by' => $by?->id, 'reviewed_at' => now(), 'gateway_txn' => $gatewayTxn]);
            self::post(self::locked($topup->wallet), 'topup', (float) $topup->amount,
                ['topup_id' => $topup->id, 'note' => WalletTopup::METHODS[$topup->method] ?? $topup->method, 'created_by' => $by?->id]);
        });
    }

    /** เปิดรายการเติมเงินอัตโนมัติ: ได้เลขอ้างอิงสำหรับใส่ใน QR ชำระบิล */
    public static function openAutoTopup(Student|User $owner, float $amount, User $by): WalletTopup
    {
        do {
            $reference = 'W'.strtoupper(Str::random(11));
        } while (WalletTopup::where('reference', $reference)->exists());

        return self::for($owner)->topups()->create(['amount' => $amount, 'method' => 'auto', 'status' => 'pending',
            'reference' => $reference, 'requested_by' => $by->id]);
    }

    /**
     * ปรับยอด (บวกหรือลบ) หรือถอนเงินคืน ต้องมีเหตุผลเสมอ
     *
     * @param  string|null  $method  วิธีคืนเงินของรายการถอนคืน (cash, transfer)
     */
    public static function adjust(Student|User $owner, string $type, float $amount, User $by, string $note, ?string $method = null): WalletTransaction
    {
        return DB::transaction(fn () => self::post(self::locked(self::for($owner)), $type, $amount,
            ['note' => $note, 'created_by' => $by->id, 'method' => $type === 'withdraw' ? ($method ?: 'cash') : null]));
    }

    /** ถอนเงินคืนทั้งหมดที่เหลือในกระเป๋า (นักเรียนจบ/ย้ายออก บุคลากรลาออก) คืนค่าว่างถ้าไม่มีเงินเหลือ */
    public static function withdrawAll(Wallet $wallet, User $by, string $note, string $method): ?WalletTransaction
    {
        return DB::transaction(function () use ($wallet, $by, $note, $method) {
            $wallet = self::locked($wallet);
            $balance = (float) $wallet->balance;

            return $balance > 0
                ? self::post($wallet, 'withdraw', -$balance, ['note' => $note, 'created_by' => $by->id, 'method' => $method])
                : null;
        });
    }

    /* ---------------- จ่ายเงินยอดขายให้ร้านค้า ---------------- */

    /**
     * ออกใบจ่ายเงินให้ร้าน: รวมยอดขายที่ยังไม่ได้จ่ายถึงสิ้นวันที่ระบุ หักส่วนแบ่งของโรงเรียน
     * ล็อกแถวของร้านไว้จนจบ รายการขายเดียวกันจึงไม่เข้าใบจ่ายเงินสองใบแม้กดพร้อมกัน
     */
    public static function settle(Shop $shop, Carbon $until, string $method, User $by, ?string $note = null): ShopSettlement
    {
        return DB::transaction(function () use ($shop, $until, $method, $by, $note) {
            $shop = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            // ล็อกรายการขายด้วย: รายการที่กำลังถูกยกเลิกพร้อมกันจะไม่หลุดเข้ามาในใบ และรายการในใบจะยกเลิกไม่ได้
            $sales = $shop->unsettledSales()->where('created_at', '<=', $until->copy()->endOfDay())->orderBy('id')->lockForUpdate()->get(['id', 'total', 'created_at']);
            if ($sales->isEmpty()) {
                throw new WalletException('ร้านนี้ไม่มียอดขายค้างจ่ายถึงวันที่เลือก');
            }
            $gross = round((float) $sales->sum('total'), 2);
            $fee = round($gross * (float) $shop->fee_percent / 100, 2);

            $settlement = ShopSettlement::create([
                'doc_no' => ShopSettlement::nextNumber(), 'shop_id' => $shop->id,
                'from_date' => $sales->min('created_at')->toDateString(), 'to_date' => $sales->max('created_at')->toDateString(),
                'sales_count' => $sales->count(), 'gross' => $gross, 'fee_percent' => $shop->fee_percent, 'fee' => $fee, 'net' => round($gross - $fee, 2),
                'method' => $method, 'note' => $note, 'paid_by' => $by->id, 'paid_at' => now(),
            ]);
            foreach ($sales->pluck('id')->chunk(500) as $ids) {
                WalletSale::whereIn('id', $ids)->update(['settlement_id' => $settlement->id]);
            }

            return $settlement;
        });
    }

    /** ยกเลิกใบจ่ายเงิน: รายการขายในใบกลับไปเป็นยอดค้างจ่ายของร้าน (เลขที่และแถวของใบเก็บไว้) */
    public static function voidSettlement(ShopSettlement $settlement, User $by, string $reason): void
    {
        DB::transaction(function () use ($settlement, $by, $reason) {
            $settlement = ShopSettlement::whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            if ($settlement->isVoided()) {
                throw new WalletException('ใบจ่ายเงินนี้ถูกยกเลิกไปแล้ว');
            }
            $settlement->update(['voided_at' => now(), 'voided_by' => $by->id, 'void_reason' => $reason]);
            WalletSale::where('settlement_id', $settlement->id)->update(['settlement_id' => null]);
        });
    }
}
