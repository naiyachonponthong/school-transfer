<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\ShopProduct;
use App\Models\Student;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletSale;
use App\Models\WalletTopup;
use App\Models\WalletTransaction;
use App\Services\Notifier;
use App\Services\WalletException;
use App\Services\WalletService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** งานของผู้จัดการกระเป๋าเงิน: เติมเงิน ตรวจสลิป ร้านค้าและสินค้า รายงาน และกระเป๋าของนักเรียนรายคน */
class WalletAdminController extends Controller
{
    public function index()
    {
        $today = today();

        return view('wallets.index', [
            'outstanding' => (float) Wallet::sum('balance'),
            'topupToday' => (float) WalletTransaction::where('type', 'topup')->where('created_at', '>=', $today)->sum('amount'),
            'salesToday' => (float) WalletSale::whereNull('voided_at')->where('created_at', '>=', $today)->sum('total'),
            'pending' => WalletTopup::with(['wallet.student.classroom', 'requester'])->where('status', 'pending')->oldest()->get(),
            'shops' => Shop::withCount('products')->with('cashiers')->orderBy('name')->get(),
            'shopToday' => WalletSale::whereNull('voided_at')->where('created_at', '>=', $today)->selectRaw('shop_id, sum(total) as total, count(*) as n')->groupBy('shop_id')->get()->keyBy('shop_id'),
            'students' => Student::active()->with('classroom')->orderBy('student_code')->get(['id', 'student_code', 'prefix', 'first_name', 'last_name', 'classroom_id']),
            'recent' => WalletTransaction::with('wallet.student')->where('type', 'topup')->latest('id')->limit(10)->get(),
        ]);
    }

    /* ---------------- เติมเงิน ---------------- */

    public function topupCash(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:20000'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [], ['student_id' => 'นักเรียน', 'amount' => 'จำนวนเงิน']);
        $student = Student::findOrFail($data['student_id']);
        WalletService::topupCash($student, (float) $data['amount'], $request->user(), $data['note'] ?? null);
        Audit::log('finance.wallet', $student, 'เติมเงินสดเข้ากระเป๋า '.baht($data['amount'])." บาท ให้ {$student->fullName()}");

        return back()->with('success', 'เติมเงิน '.baht($data['amount'])." บาท ให้ {$student->fullName()} แล้ว · คงเหลือ ".baht(WalletService::for($student)->balance).' บาท');
    }

    public function approve(Request $request, WalletTopup $topup)
    {
        try {
            WalletService::approve($topup, $request->user());
        } catch (WalletException $e) {
            return back()->with('warning', $e->getMessage());
        }
        $student = $topup->wallet->student;
        Audit::log('finance.wallet', $student, 'อนุมัติสลิปเติมเงิน '.baht($topup->amount)." บาท ของ {$student->fullName()}");
        Notifier::parents($student, '✅ เติมเงินเข้ากระเป๋า '.baht($topup->amount).' บาท เรียบร้อยแล้ว', route('parent.wallet', $student));

        return back()->with('success', 'อนุมัติแล้ว เงินเข้ากระเป๋าของ '.$student->fullName());
    }

    public function reject(Request $request, WalletTopup $topup)
    {
        abort_unless($topup->status === 'pending', 422, 'รายการนี้ตรวจไปแล้ว');
        $data = $request->validate(['note' => ['required', 'string', 'max:200']], [], ['note' => 'เหตุผล']);
        $topup->update(['status' => 'rejected', 'note' => $data['note'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        $student = $topup->wallet->student;
        Notifier::parents($student, '⚠️ สลิปเติมเงิน '.baht($topup->amount).' บาท ไม่ผ่านการตรวจ: '.$data['note'], route('parent.wallet', $student));

        return back()->with('success', 'บันทึกว่าไม่อนุมัติแล้ว');
    }

    /* ---------------- กระเป๋าของนักเรียนรายคน ---------------- */

    public function student(Student $student)
    {
        $wallet = WalletService::for($student);

        return view('wallets.student', [
            'student' => $student->load('classroom'),
            'wallet' => $wallet,
            'transactions' => $wallet->transactions()->with('sale.shop')->latest('id')->paginate(40),
        ]);
    }

    /** ปรับยอด (แก้ข้อผิดพลาด) หรือถอนเงินคืนผู้ปกครอง (จบ/ย้ายออก) */
    public function adjust(Request $request, Student $student)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['adjust_in', 'adjust_out', 'withdraw'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'note' => ['required', 'string', 'max:200'],
        ], [], ['amount' => 'จำนวนเงิน', 'note' => 'เหตุผล']);
        $signed = $data['type'] === 'adjust_in' ? (float) $data['amount'] : -(float) $data['amount'];

        try {
            WalletService::adjust($student, $data['type'] === 'withdraw' ? 'withdraw' : 'adjust', $signed, $request->user(), $data['note']);
        } catch (WalletException $e) {
            return back()->with('warning', $e->getMessage());
        }
        Audit::log('finance.wallet', $student, ($data['type'] === 'withdraw' ? 'ถอนเงินคืน ' : 'ปรับยอดกระเป๋า ').baht($signed)." บาท ของ {$student->fullName()}: {$data['note']}");

        return back()->with('success', 'บันทึกแล้ว');
    }

    /* ---------------- ร้านค้า สินค้า คนขาย ---------------- */

    public function storeShop(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:255']], [], ['name' => 'ชื่อร้าน']);
        $shop = Shop::create($data);

        return redirect()->route('wallets.shop', $shop)->with('success', "เพิ่มร้าน {$shop->name} แล้ว เพิ่มสินค้าและกำหนดคนขายได้เลย");
    }

    public function shop(Shop $shop)
    {
        return view('wallets.shop', [
            'shop' => $shop->load(['products', 'cashiers']),
            'staff' => User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function updateShop(Request $request, Shop $shop)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'location' => ['nullable', 'string', 'max:255'],
            'cashiers' => ['nullable', 'array'], 'cashiers.*' => ['integer', 'exists:users,id'],
        ], [], ['name' => 'ชื่อร้าน']);
        $shop->update(['name' => $data['name'], 'location' => $data['location'] ?? null, 'is_active' => $request->boolean('is_active')]);
        $shop->cashiers()->sync($data['cashiers'] ?? []);

        return back()->with('success', 'บันทึกร้านค้าแล้ว');
    }

    private function productRules(): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:60'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:99999'], 'sort' => ['nullable', 'integer', 'min:0', 'max:9999']];
    }

    public function storeProduct(Request $request, Shop $shop)
    {
        $data = $request->validate($this->productRules(), [], ['name' => 'ชื่อสินค้า', 'price' => 'ราคา']);
        $shop->products()->create(['sort' => (int) ($data['sort'] ?? 0)] + $data);

        return back()->with('success', 'เพิ่มสินค้าแล้ว');
    }

    public function updateProduct(Request $request, ShopProduct $product)
    {
        $data = $request->validate($this->productRules(), [], ['name' => 'ชื่อสินค้า', 'price' => 'ราคา']);
        $product->update(['sort' => (int) ($data['sort'] ?? 0), 'is_active' => $request->boolean('is_active')] + $data);

        return back()->with('success', 'บันทึกสินค้าแล้ว');
    }

    public function destroyProduct(ShopProduct $product)
    {
        $product->delete(); // รายการขายเก่าเก็บชื่อและราคาไว้ในตัวเอง ไม่กระทบ

        return back()->with('success', 'ลบสินค้าแล้ว');
    }

    /* ---------------- รายงาน ---------------- */

    public function report(Request $request)
    {
        $from = Carbon::parse($request->query('from', today()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', today()->toDateString()))->endOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }
        $sales = WalletSale::with(['shop', 'wallet.student', 'cashier'])->whereBetween('created_at', [$from, $to])->latest('id')->get();
        $valid = $sales->whereNull('voided_at');
        $shops = Shop::orderBy('name')->get()->keyBy('id');

        // ยอดขายรายสินค้า
        $products = $valid->flatMap(fn (WalletSale $s) => collect($s->items)->map(fn ($i) => ['shop' => $s->shop_id, 'name' => $i['name'], 'qty' => $i['qty'], 'sum' => $i['price'] * $i['qty']]))
            ->groupBy(fn ($r) => $r['shop'].'|'.$r['name'])
            ->map(fn ($g) => ['shop' => $shops[$g[0]['shop']]->name ?? '-', 'name' => $g[0]['name'], 'qty' => $g->sum('qty'), 'sum' => $g->sum('sum')])
            ->sortByDesc('sum')->values();

        return view('wallets.report', [
            'from' => $from, 'to' => $to, 'sales' => $sales, 'products' => $products,
            'byShop' => $valid->groupBy('shop_id')->map(fn ($g, $id) => ['name' => $shops[$id]->name ?? '-', 'count' => $g->count(), 'total' => $g->sum('total')])->sortByDesc('total')->values(),
            'voided' => $sales->whereNotNull('voided_at'),
            'topups' => WalletTopup::where('status', 'approved')->whereBetween('reviewed_at', [$from, $to])->selectRaw('method, sum(amount) as total, count(*) as n')->groupBy('method')->get()->keyBy('method'),
            'withdrawn' => (float) WalletTransaction::where('type', 'withdraw')->whereBetween('created_at', [$from, $to])->sum('amount'),
            'outstanding' => (float) Wallet::sum('balance'),
        ]);
    }
}
