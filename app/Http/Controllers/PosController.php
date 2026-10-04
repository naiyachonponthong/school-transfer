<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Student;
use App\Models\User;
use App\Models\WalletSale;
use App\Services\WalletException;
use App\Services\WalletService;
use App\Support\PromptPay;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * หน้าจอขาย (POS) ของร้านค้าในโรงเรียน: สแกนบัตรนักเรียนหรือครูแล้วตัดเงินจากกระเป๋า หรือให้ลูกค้าสแกน QR พร้อมเพย์จ่ายเอง
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
            'sales' => (clone $today)->with(['wallet.student.classroom', 'wallet.user'])->latest('id')->limit(20)->get(),
            'todayTotal' => (float) (clone $today)->whereNull('voided_at')->sum('total'),
            'todayCount' => (clone $today)->whereNull('voided_at')->count(),
            'canQr' => filled($shop->promptpayId()),
        ]);
    }

    /** เจ้าของกระเป๋าจากรหัสที่สแกน: นักเรียน (QR บนบัตร บัตรแตะ รหัสนักเรียน) หรือครู/บุคลากร (รหัสบุคลากร ชื่อผู้ใช้) */
    private function ownerByCode(string $code): Student|User|null
    {
        if ($code === '') {
            return null;
        }

        return Student::with('classroom')->active()->scannedBy($code)->first()
            ?? User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)
                ->where(fn ($q) => $q->where('staff_code', $code)->orWhere('username', $code))->orderByRaw('staff_code = ? desc', [$code])->first();
    }

    /** หาลูกค้าจากรหัสที่สแกน พร้อมยอดคงเหลือ */
    public function lookup(Request $request, Shop $shop)
    {
        $this->authorizeShop($request, $shop);
        $owner = $this->ownerByCode(trim((string) $request->input('code')));
        if (! $owner) {
            return response()->json(['ok' => false, 'message' => 'ไม่พบนักเรียนหรือบุคลากรจากรหัสนี้'], 404);
        }
        $wallet = WalletService::for($owner);
        $isStudent = $owner instanceof Student;
        // คงชื่อ key "student" ไว้ให้หน้าจอเดิมใช้ต่อได้ และเพิ่ม type บอกว่าเป็นนักเรียนหรือบุคลากร
        return response()->json(['ok' => true, 'student' => [
            'type' => $isStudent ? 'student' : 'staff',
            'id' => $owner->id,
            'name' => $isStudent ? $owner->fullName() : $owner->name,
            'classroom' => $isStudent ? $owner->classroom?->name() : ($owner->position ?: 'ครู/บุคลากร'),
            'photo' => $isStudent ? $owner->photoUrl() : $owner->avatarUrl(),
            'initials' => $owner->initials(),
            'balance' => (float) $wallet->balance,
            'frozen' => $wallet->is_frozen,
            'limit_left' => $wallet->daily_limit === null ? null : max(0, (float) $wallet->daily_limit - $wallet->spentToday()),
        ]]);
    }

    /** รายการสินค้าที่จะขาย: ราคาและชื่อยึดจากฐานข้อมูล ไม่เชื่อค่าที่หน้าจอส่งมา (เว้นแต่บรรทัดที่กดจำนวนเงินเอง) */
    private function items(Request $request, Shop $shop): array
    {
        $data = $request->validate([
            'client_key' => ['required', 'string', 'min:8', 'max:64'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.price' => ['nullable', 'numeric', 'min:0.01', 'max:99999'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
        ]);
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

        return [$items, $data['client_key']];
    }

    public function charge(Request $request, Shop $shop)
    {
        $this->authorizeShop($request, $shop);
        $who = $request->validate([
            'student_id' => ['required_without:staff_id', 'nullable', 'integer', 'exists:students,id'],
            'staff_id' => ['required_without:student_id', 'nullable', 'integer', 'exists:users,id'],
        ]);
        [$items, $key] = $this->items($request, $shop);
        $owner = ! empty($who['student_id'])
            ? Student::active()->findOrFail($who['student_id'])
            : User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->findOrFail($who['staff_id']);

        try {
            $sale = WalletService::charge($owner, $shop, $items, $request->user(), $key);
        } catch (WalletException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'sale_id' => $sale->id, 'total' => (float) $sale->total, 'balance' => (float) $sale->wallet->fresh()->balance]);
    }

    /** ข้อความ QR พร้อมเพย์ของยอดที่จะขาย ให้ลูกค้าสแกนจ่ายด้วยแอปธนาคาร */
    public function qr(Request $request, Shop $shop)
    {
        $this->authorizeShop($request, $shop);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01', 'max:99999']]);
        $payload = PromptPay::payload((string) $shop->promptpayId(), round((float) $data['amount'], 2));
        if (! $payload) {
            return response()->json(['ok' => false, 'message' => 'ยังไม่ได้ตั้งพร้อมเพย์ของร้านหรือของโรงเรียน'], 422);
        }

        return response()->json(['ok' => true, 'qr' => $payload, 'promptpay' => $shop->promptpayId()]);
    }

    /** บันทึกการขายที่รับเงินด้วย QR: คนขายยืนยันเองว่าเห็นเงินเข้าแล้ว */
    public function qrPaid(Request $request, Shop $shop)
    {
        $this->authorizeShop($request, $shop);
        abort_unless(filled($shop->promptpayId()), 422, 'ยังไม่ได้ตั้งพร้อมเพย์');
        [$items, $key] = $this->items($request, $shop);

        try {
            $sale = WalletService::recordQrSale($shop, $items, $request->user(), $key);
        } catch (WalletException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'sale_id' => $sale->id, 'total' => (float) $sale->total]);
    }

    /* ---------------- หน้าจอลูกค้า ---------------- */

    /** สถานะของหน้าจอลูกค้าแยกตามร้านและบัญชีคนขาย (เครื่องขายหนึ่งเครื่อง = หน้าจอลูกค้าหนึ่งจอ) */
    private function displayKey(Request $request, Shop $shop): string
    {
        return "pos-display:{$shop->id}:{$request->user()->id}";
    }

    /** หน้าจอที่หันหาลูกค้า: เปิดบนจอที่สองหรือแท็บเล็ตอีกเครื่องด้วยบัญชีเดียวกับคนขาย */
    public function display(Request $request, Shop $shop)
    {
        $this->authorizeShop($request, $shop);

        return view('pos.display', ['shop' => $shop]);
    }

    public function displayState(Request $request, Shop $shop)
    {
        $this->authorizeShop($request, $shop);

        return response()->json(Cache::get($this->displayKey($request, $shop), ['status' => 'idle']));
    }

    /** หน้าจอขายส่งสิ่งที่จะให้ลูกค้าเห็น (รายการ ยอดรวม ชื่อ ยอดคงเหลือ QR ผลการชำระ) */
    public function displayPush(Request $request, Shop $shop)
    {
        $this->authorizeShop($request, $shop);
        $data = $request->validate([
            'status' => ['required', 'in:idle,cart,qr,paid,error'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.price' => ['required', 'numeric'],
            'items.*.qty' => ['required', 'integer'],
            'total' => ['nullable', 'numeric'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['nullable', 'string', 'max:255'],
            'customer.sub' => ['nullable', 'string', 'max:255'],
            'customer.photo' => ['nullable', 'string', 'max:500'],
            'customer.initials' => ['nullable', 'string', 'max:5'],
            'customer.balance' => ['nullable', 'numeric'],
            'balance_after' => ['nullable', 'numeric'],
            'qr' => ['nullable', 'string', 'max:600'],
            'message' => ['nullable', 'string', 'max:255'],
        ]);
        // ร้านที่ตั้งไม่ให้แสดงยอดคงเหลือ: ไม่ส่งยอดไปที่หน้าจอลูกค้าเลย
        if (! $shop->show_balance) {
            unset($data['customer']['balance'], $data['balance_after']);
        }
        Cache::put($this->displayKey($request, $shop), $data + ['at' => now()->timestamp], now()->addMinutes(30));

        return response()->json(['ok' => true]);
    }

    /* ---------------- ใบเสร็จและการยกเลิก ---------------- */

    /** ใบเสร็จขนาดกระดาษ 58 มม. สำหรับเครื่องพิมพ์ใบเสร็จ (สั่งพิมพ์จากเบราว์เซอร์) */
    public function receipt(Request $request, WalletSale $sale)
    {
        abort_unless($sale->shop->canBeUsedBy($request->user()), 403);

        return view('pos.receipt', ['sale' => $sale->load(['shop', 'wallet.student.classroom', 'wallet.user', 'cashier']),
            'balance' => $sale->wallet_id ? (float) $sale->wallet->transactions()->where('sale_id', $sale->id)->where('type', 'purchase')->value('balance_after') : null]);
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

        return back()->with('success', $sale->wallet_id
            ? 'ยกเลิกรายการแล้ว เงิน '.baht($sale->total).' บาท คืนเข้ากระเป๋า'
            : 'ยกเลิกรายการแล้ว รายการนี้จ่ายด้วย QR ต้องคืนเงิน '.baht($sale->total).' บาท ให้ลูกค้าเอง');
    }
}
