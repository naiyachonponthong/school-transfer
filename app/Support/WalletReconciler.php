<?php

namespace App\Support;

use App\Models\ShopSettlement;
use App\Models\Wallet;
use App\Models\WalletSale;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * กระทบยอดกระเป๋าเงิน: ตรวจว่าตัวเลขที่เก็บไว้หลายที่ยังเล่าเรื่องเดียวกัน
 *   1) ยอดคงเหลือของทุกกระเป๋า = ผลรวมของสมุดรายการ และไม่ติดลบ
 *   2) รายการขายทุกรายการมีบรรทัดตัดเงินหนึ่งบรรทัดยอดตรงกัน (ที่ยกเลิกแล้วมีบรรทัดคืนเงินหนึ่งบรรทัด)
 *   3) รายการเติมเงินที่อนุมัติแล้วมีบรรทัดเงินเข้าหนึ่งบรรทัดยอดตรงกัน (ที่ยังไม่อนุมัติต้องไม่มี)
 *   4) ใบจ่ายเงินให้ร้านค้ามียอดตรงกับรายการขายในใบ
 * ปกติทุกข้อเป็นจริงเสมอเพราะเงินเคลื่อนผ่าน WalletService เท่านั้น ถ้าไม่ตรงแปลว่ามีการแก้ฐานข้อมูลตรง ๆ หรือมีข้อผิดพลาดของระบบ
 */
class WalletReconciler
{
    /** เก็บรายละเอียดไว้แสดงบนหน้ากระเป๋าเงินไม่เกินกี่จุด (จำนวนจริงเก็บครบ) */
    private const KEEP = 30;

    private static function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /** @return list<string> จุดที่ไม่ตรง (ว่าง = ตรงทั้งหมด) */
    public static function check(): array
    {
        return array_merge(self::checkBalances(), self::checkSales(), self::checkTopups(), self::checkSettlements());
    }

    /** ตรวจแล้วจำผลไว้ให้หน้ากระเป๋าเงินแสดง */
    public static function run(): array
    {
        $issues = self::check();
        Settings::set([
            'wallet_reconciled_at' => now()->toDateTimeString(),
            'wallet_reconcile_count' => (string) count($issues),
            'wallet_reconcile_issues' => json_encode(array_slice($issues, 0, self::KEEP), JSON_UNESCAPED_UNICODE),
        ]);

        return $issues;
    }

    /** @return array{at: Carbon, count: int, issues: list<string>}|null ว่าง = ยังไม่เคยตรวจ */
    public static function lastResult(): ?array
    {
        $at = Settings::get('wallet_reconciled_at');

        return $at ? [
            'at' => Carbon::parse($at),
            'count' => (int) Settings::get('wallet_reconcile_count'),
            'issues' => json_decode((string) Settings::get('wallet_reconcile_issues'), true) ?: [],
        ] : null;
    }

    private static function checkBalances(): array
    {
        $issues = [];
        $ledger = DB::table('wallet_transactions')->selectRaw('wallet_id, sum(amount) as total, max(id) as last_id')->groupBy('wallet_id')->get()->keyBy('wallet_id');
        $after = collect();
        foreach ($ledger->pluck('last_id')->chunk(500) as $ids) {
            $after = $after->union(DB::table('wallet_transactions')->whereIn('id', $ids)->pluck('balance_after', 'wallet_id'));
        }

        foreach (Wallet::with(['student', 'user'])->lazy(500) as $wallet) {
            $name = $wallet->ownerName();
            $balance = self::cents($wallet->balance);
            $sum = self::cents($ledger[$wallet->id]->total ?? 0);
            if ($balance < 0) {
                $issues[] = "กระเป๋าของ {$name} ยอดติดลบ ".baht($wallet->balance).' บาท';
            }
            if ($balance !== $sum) {
                $issues[] = "กระเป๋าของ {$name} ยอดคงเหลือ ".baht($wallet->balance).' บาท ไม่ตรงกับผลรวมสมุดรายการ '.baht($sum / 100).' บาท';
            } elseif ($after->has($wallet->id) && self::cents($after[$wallet->id]) !== $balance) {
                $issues[] = "กระเป๋าของ {$name} ยอดหลังรายการล่าสุดในสมุดรายการ ".baht($after[$wallet->id]).' บาท ไม่ตรงกับยอดคงเหลือ '.baht($wallet->balance).' บาท';
            }
        }

        return $issues;
    }

    /** ผลรวมของบรรทัดในสมุดรายการ แยกตามเอกสารที่อ้าง: [id เอกสาร][ประเภท] => {n, total} */
    private static function ledgerBy(string $column, iterable $ids, array $types)
    {
        return DB::table('wallet_transactions')->whereIn($column, $ids)->whereIn('type', $types)
            ->selectRaw("{$column} as ref, type, count(*) as n, sum(amount) as total")->groupBy($column, 'type')->get()
            ->groupBy('ref')->map->keyBy('type');
    }

    private static function checkSales(): array
    {
        $issues = [];
        WalletSale::whereNotNull('wallet_id')->select(['id', 'total', 'voided_at', 'created_at'])->chunkById(1000, function ($sales) use (&$issues) {
            $ledger = self::ledgerBy('sale_id', $sales->pluck('id'), ['purchase', 'void']);
            foreach ($sales as $sale) {
                $label = "รายการขาย #{$sale->id} (".thai_datetime($sale->created_at).') '.baht($sale->total).' บาท';
                $purchase = $ledger[$sale->id]['purchase'] ?? null;
                $void = $ledger[$sale->id]['void'] ?? null;
                if (! $purchase || (int) $purchase->n !== 1 || self::cents($purchase->total) !== -self::cents($sale->total)) {
                    $issues[] = "{$label} ไม่มีบรรทัดตัดเงินที่ยอดตรงกันในสมุดรายการ";
                }
                if ($sale->voided_at && (! $void || (int) $void->n !== 1 || self::cents($void->total) !== self::cents($sale->total))) {
                    $issues[] = "{$label} ถูกยกเลิกแล้วแต่ไม่มีบรรทัดคืนเงินที่ยอดตรงกัน";
                }
                if (! $sale->voided_at && $void) {
                    $issues[] = "{$label} มีบรรทัดคืนเงินทั้งที่รายการไม่ได้ถูกยกเลิก";
                }
            }
        });

        $orphans = WalletTransaction::whereIn('type', ['purchase', 'void'])->whereNull('sale_id')->count();
        if ($orphans) {
            $issues[] = "มีบรรทัดซื้อ/คืนเงินในสมุดรายการ {$orphans} บรรทัดที่ไม่มีรายการขายอ้างอิง";
        }

        return $issues;
    }

    private static function checkTopups(): array
    {
        $issues = [];
        WalletTopup::select(['id', 'amount', 'status', 'created_at'])->chunkById(1000, function ($topups) use (&$issues) {
            $ledger = self::ledgerBy('topup_id', $topups->pluck('id'), ['topup']);
            foreach ($topups as $topup) {
                $label = "รายการเติมเงิน #{$topup->id} (".thai_datetime($topup->created_at).') '.baht($topup->amount).' บาท';
                $row = $ledger[$topup->id]['topup'] ?? null;
                if ($topup->status === 'approved' && (! $row || (int) $row->n !== 1 || self::cents($row->total) !== self::cents($topup->amount))) {
                    $issues[] = "{$label} อนุมัติแล้วแต่ไม่มีบรรทัดเงินเข้าที่ยอดตรงกัน";
                }
                if ($topup->status !== 'approved' && $row) {
                    $issues[] = "{$label} ยังไม่อนุมัติแต่มีเงินเข้ากระเป๋าแล้ว";
                }
            }
        });

        $orphans = WalletTransaction::where('type', 'topup')->whereNull('topup_id')->count();
        if ($orphans) {
            $issues[] = "มีบรรทัดเติมเงินในสมุดรายการ {$orphans} บรรทัดที่ไม่มีรายการเติมเงินอ้างอิง";
        }

        return $issues;
    }

    private static function checkSettlements(): array
    {
        $issues = [];
        $sales = DB::table('wallet_sales')->whereNotNull('settlement_id')
            ->selectRaw('settlement_id, count(*) as n, sum(total) as total, sum(case when voided_at is not null then 1 else 0 end) as voided')
            ->groupBy('settlement_id')->get()->keyBy('settlement_id');

        foreach (ShopSettlement::with('shop')->lazy(500) as $settlement) {
            $label = "ใบจ่ายเงินร้านค้า {$settlement->doc_no} ({$settlement->shop?->name})";
            $row = $sales[$settlement->id] ?? null;
            if ($settlement->isVoided()) {
                if ($row) {
                    $issues[] = "{$label} ถูกยกเลิกแล้วแต่ยังมีรายการขายผูกอยู่ {$row->n} รายการ";
                }

                continue;
            }
            if (! $row || (int) $row->n !== (int) $settlement->sales_count || self::cents($row->total) !== self::cents($settlement->gross)) {
                $issues[] = "{$label} ยอดขายในใบ ".baht($settlement->gross).' บาท ไม่ตรงกับรายการขายที่ผูกอยู่ '.baht($row->total ?? 0).' บาท';
            }
            if ($row && (int) $row->voided > 0) {
                $issues[] = "{$label} มีรายการขายที่ถูกยกเลิกอยู่ในใบ {$row->voided} รายการ";
            }
        }

        return $issues;
    }
}
