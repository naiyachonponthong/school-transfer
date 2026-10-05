<?php

namespace App\Http\Controllers;

use App\Models\CashClosing;
use App\Models\Shop;
use App\Models\ShopSettlement;
use App\Services\Notifier;
use App\Services\WalletException;
use App\Services\WalletService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * จ่ายเงินยอดขายให้ร้านค้า: ค่าสินค้าตัดจากกระเป๋าเงินมาอยู่ที่โรงเรียน โรงเรียนจึงจ่ายคืนให้ร้านเป็นงวด
 * หน้านี้บอกยอดค้างจ่ายของแต่ละร้าน ออกใบจ่ายเงิน (หักส่วนแบ่งของโรงเรียน) และเก็บทะเบียนใบจ่ายเงินไว้ตรวจย้อนหลัง
 */
class ShopSettlementController extends Controller
{
    public function index(Request $request)
    {
        $until = Carbon::parse($request->query('until', today()->toDateString()))->endOfDay();
        $shops = Shop::orderBy('name')->get()->map(function (Shop $shop) use ($until) {
            $due = $shop->unsettledSales()->where('created_at', '<=', $until)
                ->selectRaw('count(*) as n, coalesce(sum(total), 0) as gross, min(created_at) as since')->toBase()->first();
            $gross = round((float) $due->gross, 2);
            $fee = round($gross * (float) $shop->fee_percent / 100, 2);

            return ['shop' => $shop, 'count' => (int) $due->n, 'gross' => $gross, 'fee' => $fee, 'net' => round($gross - $fee, 2),
                'since' => $due->since ? Carbon::parse($due->since) : null];
        });

        return view('wallets.settlements', [
            'until' => $until,
            'shops' => $shops,
            'settlements' => ShopSettlement::with(['shop', 'payer'])->latest('id')->paginate(30)->withQueryString(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'until' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(ShopSettlement::METHODS))],
            'note' => ['nullable', 'string', 'max:255'],
        ], [], ['shop_id' => 'ร้านค้า', 'until' => 'ถึงวันที่', 'method' => 'วิธีจ่าย']);
        $shop = Shop::findOrFail($data['shop_id']);

        try {
            $settlement = WalletService::settle($shop, Carbon::parse($data['until']), $data['method'], $request->user(), $data['note'] ?? null);
        } catch (WalletException $e) {
            return back()->with('warning', $e->getMessage());
        }
        Audit::log('finance.settlement', $settlement, "จ่ายเงินยอดขายให้{$shop->name} เลขที่ {$settlement->doc_no} ".baht($settlement->net).' บาท ('.$settlement->methodLabel().')');
        Notifier::users($shop->cashiers, "💵 โรงเรียนจ่ายเงินยอดขายของ{$shop->name} ".baht($settlement->net)." บาท ({$settlement->methodLabel()}) · งวด {$settlement->periodLabel()} · เลขที่ {$settlement->doc_no}",
            route('wallets.settlements.show', $settlement));

        return redirect()->route('wallets.settlements.show', $settlement)->with('success', "ออกใบจ่ายเงินเลขที่ {$settlement->doc_no} แล้ว ".baht($settlement->net).' บาท');
    }

    /** ใบจ่ายเงินพร้อมรายการขายในงวด (พิมพ์ให้ร้านเซ็นรับเงิน) — คนขายของร้านนั้นเปิดดูได้ */
    public function show(Request $request, ShopSettlement $settlement)
    {
        abort_unless($settlement->shop->canBeUsedBy($request->user()), 403);
        $sales = $settlement->sales()->orderBy('id')->get(['id', 'total', 'created_at']);

        return view('wallets.settlement', [
            'settlement' => $settlement->load(['shop', 'payer', 'voider']),
            // ใบที่ยกเลิกแล้วรายการขายถูกปล่อยกลับไปเป็นยอดค้างจ่าย จึงไม่มีรายการรายวันให้แสดง
            'days' => $sales->groupBy(fn ($s) => $s->created_at->toDateString())
                ->map(fn ($rows, $date) => ['date' => Carbon::parse($date), 'count' => $rows->count(), 'total' => (float) $rows->sum('total')])->values(),
            'canManage' => $request->user()->hasPermission('wallet.manage'),
        ]);
    }

    public function void(Request $request, ShopSettlement $settlement)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], [], ['reason' => 'เหตุผล']);
        // เงินสดที่จ่ายออกนับอยู่ในใบนำส่งของวันนั้นแล้ว
        abort_if($settlement->method === 'cash' && CashClosing::isClosed($settlement->paid_at->toDateString()), 422,
            'วันที่ '.thai_date($settlement->paid_at).' ปิดยอดแล้ว ให้ผู้ดูแลระบบยกเลิกการปิดยอดก่อน');

        try {
            WalletService::voidSettlement($settlement, $request->user(), $data['reason']);
        } catch (WalletException $e) {
            return back()->with('warning', $e->getMessage());
        }
        Audit::log('finance.settlement', $settlement, "ยกเลิกใบจ่ายเงินร้านค้า เลขที่ {$settlement->doc_no} ".baht($settlement->net)." บาท: {$data['reason']}");

        return redirect()->route('wallets.settlements')->with('success', "ยกเลิกใบจ่ายเงินเลขที่ {$settlement->doc_no} แล้ว ยอดขายในใบกลับไปเป็นยอดค้างจ่าย");
    }
}
