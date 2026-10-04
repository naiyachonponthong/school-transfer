<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Student;
use App\Models\WalletSale;
use App\Services\WalletException;
use App\Services\WalletService;
use Illuminate\Http\Request;

/**
 * หน้าจอขาย (POS) ของร้านค้าในโรงเรียน: สแกนบัตรนักเรียนแล้วตัดเงินจากกระเป๋า
 * ใช้บนเบราว์เซอร์ของแท็บเล็ต คอมพิวเตอร์ หรือเครื่อง POS แบบ Android ได้ · เครื่องอ่านบาร์โค้ด/บัตรแบบ USB พิมพ์รหัสลงช่องสแกนได้เลย
 */
class PosController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $shops = Shop::where('is_active', true)->orderBy('name')->get()->filter->canBeUsedBy($user)->values();
        if ($shops->count() === 1) {
            return redirect()->route('pos.show', $shops->first());
        }

        return view('pos.index', compact('shops'));
    }

    private function authorizeShop(Request $request, Shop $shop): void
    {
        abort_unless($shop->is_active && $shop->canBeUsedBy($request->user()), 403, 'คุณไม่ได้เป็นผู้ขายของร้านนี้');
    }

    public function show(Request $request, Shop $shop)
    {
        $this->authorizeShop($request, $shop);
        $today = $shop->sales()->where('created_at', '>=', today());

        return view('pos.show', [
            'shop' => $shop,
            'products' => $shop->products()->where('is_active', true)->get(),
            'sales' => (clone $today)->with('wallet.student.classroom')->latest('id')->limit(20)->get(),
            'todayTotal' => (float) (clone $today)->whereNull('voided_at')->sum('total'),
            'todayCount' => (clone $today)->whereNull('voided_at')->count(),
        ]);
    }

    /** หานักเรียนจากรหัสที่สแกน (QR บนบัตร หรือรหัสนักเรียน) พร้อมยอดคงเหลือ */
    public function lookup(Request $request, Shop $shop)
    {
        $this->authorizeShop($request, $shop);
        $code = trim((string) $request->input('code'));
        $student = Student::with('classroom')->active()
            ->scannedBy($code)->first();
        if (! $student || $code === '') {
            return response()->json(['ok' => false, 'message' => 'ไม่พบนักเรียนจากรหัสนี้'], 404);
        }
        $wallet = WalletService::for($student);

        return response()->json(['ok' => true, 'student' => [
            'id' => $student->id,
            'name' => $student->fullName(),
            'classroom' => $student->classroom?->name(),
            'photo' => $student->photoUrl(),
            'initials' => $student->initials(),
            'balance' => (float) $wallet->balance,
            'frozen' => $wallet->is_frozen,
            'limit_left' => $wallet->daily_limit === null ? null : max(0, (float) $wallet->daily_limit - $wallet->spentToday()),
        ]]);
    }

    public function charge(Request $request, Shop $shop)
    {
        $this->authorizeShop($request, $shop);
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'client_key' => ['required', 'string', 'min:8', 'max:64'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.price' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        // ราคาและชื่อสินค้ายึดจากฐานข้อมูล ไม่เชื่อค่าที่หน้าจอส่งมา (เว้นแต่บรรทัดที่กดจำนวนเงินเอง)
        $products = $shop->products()->where('is_active', true)->get()->keyBy('id');
        $items = [];
        foreach ($data['items'] as $row) {
            if (! empty($row['product_id'])) {
                $product = $products->get((int) $row['product_id']);
                abort_unless($product, 422, 'มีสินค้าที่ไม่ได้ขายในร้านนี้แล้ว กรุณารีเฟรชหน้าจอ');
                $items[] = ['product_id' => $product->id, 'name' => $product->name, 'price' => (float) $product->price, 'qty' => (int) $row['qty'],
                    'cost' => $product->cost !== null ? (float) $product->cost : null];
            } else {
                abort_unless(isset($row['price']), 422, 'ไม่ได้ระบุจำนวนเงิน');
                $items[] = ['name' => 'รายการอื่น', 'price' => round((float) $row['price'], 2), 'qty' => (int) $row['qty']];
            }
        }

        try {
            $sale = WalletService::charge(Student::active()->findOrFail($data['student_id']), $shop, $items, $request->user(), $data['client_key']);
        } catch (WalletException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'sale_id' => $sale->id, 'total' => (float) $sale->total, 'balance' => (float) $sale->wallet->fresh()->balance]);
    }

    /** ใบเสร็จขนาดกระดาษ 58 มม. สำหรับเครื่องพิมพ์ใบเสร็จ (สั่งพิมพ์จากเบราว์เซอร์) */
    public function receipt(Request $request, WalletSale $sale)
    {
        abort_unless($sale->shop->canBeUsedBy($request->user()), 403);

        return view('pos.receipt', ['sale' => $sale->load(['shop', 'wallet.student.classroom', 'cashier']),
            'balance' => (float) $sale->wallet->transactions()->where('sale_id', $sale->id)->where('type', 'purchase')->value('balance_after')]);
    }

    /** ยกเลิกการขาย: คนขายยกเลิกได้เฉพาะรายการของวันนี้ ผู้จัดการกระเป๋าเงินยกเลิกย้อนหลังได้ */
    public function void(Request $request, WalletSale $sale)
    {
        $user = $request->user();
        abort_unless($sale->shop->canBeUsedBy($user), 403);
        abort_unless($sale->created_at->isToday() || $user->hasPermission('wallet.manage'), 403, 'ยกเลิกรายการย้อนหลังได้เฉพาะผู้จัดการกระเป๋าเงิน');
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], [], ['reason' => 'เหตุผล']);

        try {
            WalletService::void($sale, $user, $data['reason']);
        } catch (WalletException $e) {
            return back()->with('warning', $e->getMessage());
        }

        return back()->with('success', 'ยกเลิกรายการแล้ว เงิน '.baht($sale->total).' บาท คืนเข้ากระเป๋านักเรียน');
    }
}
