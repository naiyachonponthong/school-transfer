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
use App\Models\Classroom;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            'pending' => WalletTopup::with(['wallet.student.classroom', 'wallet.user', 'requester'])->where('status', 'pending')->oldest()->get(),
            'shops' => Shop::withCount('products')->with('cashiers')->orderBy('name')->get(),
            'shopToday' => WalletSale::whereNull('voided_at')->where('created_at', '>=', $today)->selectRaw('shop_id, sum(total) as total, count(*) as n')->groupBy('shop_id')->get()->keyBy('shop_id'),
            'students' => Student::active()->with('classroom')->orderBy('student_code')->get(['id', 'student_code', 'prefix', 'first_name', 'last_name', 'classroom_id']),
            'recent' => WalletTransaction::with(['wallet.student', 'wallet.user'])->where('type', 'topup')->latest('id')->limit(10)->get(),
            'staff' => User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'position', 'staff_code', 'username']),
            'billerId' => Settings::get('wallet_biller_id'),
            'hasSecret' => filled(Settings::get('wallet_gateway_secret')),
            'cardCount' => Student::active()->whereNotNull('card_uid')->count(),
        ]);
    }

    /* ---------------- เติมเงิน ---------------- */

    /** เจ้าของกระเป๋าจากค่าในฟอร์ม: "s:รหัสแถวนักเรียน" หรือ "u:รหัสแถวบุคลากร" */
    private function ownerFromKey(?string $key): Student|User|null
    {
        [$type, $id] = array_pad(explode(':', (string) $key, 2), 2, null);

        return match ($type) {
            's' => Student::find((int) $id),
            'u' => User::whereIn('role', ['admin', 'teacher'])->find((int) $id),
            default => null,
        };
    }

    private static function nameOf(Student|User $owner): string
    {
        return $owner instanceof Student ? $owner->fullName() : $owner->name;
    }

    public function topupCash(Request $request)
    {
        $data = $request->validate([
            'owner' => ['required_without:student_id', 'nullable', 'string', 'regex:/^[su]:[0-9]+$/'],
            'student_id' => ['required_without:owner', 'nullable', 'integer', 'exists:students,id'],
            'amount' => ['required', 'numeric', 'min:1', 'max:20000'],
            'note' => ['nullable', 'string', 'max:200'],
        ], [], ['owner' => 'เจ้าของกระเป๋า', 'student_id' => 'นักเรียน', 'amount' => 'จำนวนเงิน']);
        $owner = $this->ownerFromKey($data['owner'] ?? 's:'.$data['student_id']);
        abort_unless($owner, 422, 'ไม่พบเจ้าของกระเป๋า');
        $name = self::nameOf($owner);
        WalletService::topupCash($owner, (float) $data['amount'], $request->user(), $data['note'] ?? null);
        Audit::log('finance.wallet', $owner, 'เติมเงินสดเข้ากระเป๋า '.baht($data['amount'])." บาท ให้ {$name}");

        return back()->with('success', 'เติมเงิน '.baht($data['amount'])." บาท ให้ {$name} แล้ว · คงเหลือ ".baht(WalletService::for($owner)->balance).' บาท');
    }

    public function approve(Request $request, WalletTopup $topup)
    {
        try {
            WalletService::approve($topup, $request->user());
        } catch (WalletException $e) {
            return back()->with('warning', $e->getMessage());
        }
        $owner = $topup->wallet->owner();
        Audit::log('finance.wallet', $owner, 'อนุมัติสลิปเติมเงิน '.baht($topup->amount).' บาท ของ '.self::nameOf($owner));
        WalletService::notify($owner, '✅ เติมเงินเข้ากระเป๋า '.baht($topup->amount).' บาท เรียบร้อยแล้ว');

        return back()->with('success', 'อนุมัติแล้ว เงินเข้ากระเป๋าของ '.self::nameOf($owner));
    }

    public function reject(Request $request, WalletTopup $topup)
    {
        abort_unless($topup->status === 'pending', 422, 'รายการนี้ตรวจไปแล้ว');
        $data = $request->validate(['note' => ['required', 'string', 'max:200']], [], ['note' => 'เหตุผล']);
        $topup->update(['status' => 'rejected', 'note' => $data['note'], 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
        WalletService::notify($topup->wallet->owner(), '⚠️ สลิปเติมเงิน '.baht($topup->amount).' บาท ไม่ผ่านการตรวจ: '.$data['note']);

        return back()->with('success', 'บันทึกว่าไม่อนุมัติแล้ว');
    }

    /** หานักเรียนจากบัตรที่สแกน (ใช้กับช่องเติมเงินสด) */
    public function lookup(Request $request)
    {
        $code = trim((string) $request->input('code'));
        $owner = $code === '' ? null : (Student::active()->scannedBy($code)->first()
            ?? User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)
                ->where(fn ($q) => $q->where('staff_code', $code)->orWhere('username', $code))->orderByRaw('staff_code = ? desc', [$code])->first());

        return $owner
            ? response()->json(['ok' => true, 'id' => $owner->id, 'key' => ($owner instanceof Student ? 's:' : 'u:').$owner->id,
                'name' => self::nameOf($owner), 'balance' => (float) WalletService::for($owner)->balance])
            : response()->json(['ok' => false, 'message' => 'ไม่พบนักเรียนหรือบุคลากรจากรหัสนี้'], 404);
    }

    /* ---------------- บัตรแตะ (RFID/NFC) ---------------- */

    public function cards(Request $request)
    {
        $classrooms = Classroom::currentYear()->ordered()->get();
        $classroom = $classrooms->firstWhere('id', (int) $request->query('classroom')) ?? $classrooms->first();

        return view('wallets.cards', [
            'classrooms' => $classrooms, 'classroom' => $classroom,
            'students' => $classroom ? $classroom->students()->get() : collect(),
        ]);
    }

    /** บันทึกหมายเลขบัตรของนักเรียนทั้งห้อง (แตะบัตรทีละคนที่ช่องของคนนั้น) */
    public function saveCards(Request $request)
    {
        $data = $request->validate(['cards' => ['required', 'array'], 'cards.*' => ['nullable', 'string', 'max:40', 'regex:/^[A-Za-z0-9]+$/']],
            ['cards.*.regex' => 'หมายเลขบัตรใช้ได้เฉพาะตัวอักษรอังกฤษและตัวเลข']);
        $cards = collect($data['cards'])->map(fn ($v) => filled($v) ? strtoupper(trim($v)) : null);
        $students = Student::whereIn('id', $cards->keys())->get()->keyBy('id');

        // บัตรใบเดียวผูกกับนักเรียนได้คนเดียว และต้องไม่ซ้ำกับรหัสนักเรียนของใคร
        $used = $cards->filter();
        if ($used->count() !== $used->unique()->count()) {
            return back()->withInput()->with('warning', 'มีหมายเลขบัตรซ้ำกันในหน้านี้ บัตรหนึ่งใบผูกได้กับนักเรียนคนเดียว');
        }
        $clash = Student::whereIn('card_uid', $used)->whereNotIn('id', $students->keys())->first()
            ?? Student::whereIn('student_code', $used)->first();
        if ($clash) {
            return back()->withInput()->with('warning', "หมายเลขบัตรซ้ำกับ {$clash->fullName()} ({$clash->student_code})");
        }

        $changed = 0;
        // ล้างค่าเดิมก่อน เพื่อให้สลับบัตรระหว่างนักเรียนสองคนในหน้าเดียวกันได้
        Student::whereIn('id', $students->keys())->update(['card_uid' => null]);
        foreach ($cards as $id => $uid) {
            if ($students->has($id)) {
                $changed += (int) ($students[$id]->card_uid !== $uid);
                Student::whereKey($id)->update(['card_uid' => $uid]);
            }
        }
        Audit::log('student.card', null, "บันทึกหมายเลขบัตรแตะ เปลี่ยน {$changed} คน");

        return back()->with('success', "บันทึกหมายเลขบัตรแล้ว (เปลี่ยน {$changed} คน)");
    }

    /* ---------------- เติมเงินอัตโนมัติผ่านธนาคาร ---------------- */

    public function gateway(Request $request)
    {
        $data = $request->validate(['wallet_biller_id' => ['nullable', 'digits:15']], [], ['wallet_biller_id' => 'Biller ID']);
        $values = ['wallet_biller_id' => $data['wallet_biller_id'] ?? ''];
        $secret = null;
        if ($request->boolean('rotate') || blank(Settings::get('wallet_gateway_secret'))) {
            $values['wallet_gateway_secret'] = $secret = Str::random(48);
        }
        Settings::set($values);
        Audit::log('setting.update', null, 'แก้การตั้งค่าเติมเงินกระเป๋าอัตโนมัติ'.($secret ? ' (ออกรหัสลับใหม่)' : ''));

        // รหัสลับแสดงครั้งเดียวตอนออกใหม่ หลังจากนั้นดูย้อนหลังไม่ได้
        return back()->with('success', 'บันทึกการตั้งค่าเติมเงินอัตโนมัติแล้ว')->with('gateway_secret', $secret);
    }

    /* ---------------- กระเป๋าของนักเรียนรายคน ---------------- */

    public function student(Student $student)
    {
        return $this->ownerPage($student->load('classroom'), route('wallets.adjust', $student));
    }

    /** กระเป๋าของครู/บุคลากร */
    public function staff(User $user)
    {
        abort_unless($user->isStaff(), 404);

        return $this->ownerPage($user, route('wallets.staff.adjust', $user));
    }

    private function ownerPage(Student|User $owner, string $adjustUrl)
    {
        $wallet = WalletService::for($owner);

        return view('wallets.student', [
            'ownerName' => $wallet->ownerName(),
            'ownerSub' => ($owner instanceof Student ? $owner->student_code : ($owner->staff_code ?: $owner->username)).' · '.$wallet->ownerSub(),
            'isStaff' => $owner instanceof User,
            'adjustUrl' => $adjustUrl,
            'wallet' => $wallet,
            'transactions' => $wallet->transactions()->with('sale.shop')->latest('id')->paginate(40),
        ]);
    }

    /** ปรับยอด (แก้ข้อผิดพลาด) หรือถอนเงินคืน (จบ/ย้ายออก/ลาออก) */
    public function adjust(Request $request, Student $student)
    {
        return $this->adjustOwner($request, $student);
    }

    public function adjustStaff(Request $request, User $user)
    {
        abort_unless($user->isStaff(), 404);

        return $this->adjustOwner($request, $user);
    }

    private function adjustOwner(Request $request, Student|User $owner)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['adjust_in', 'adjust_out', 'withdraw'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'note' => ['required', 'string', 'max:200'],
        ], [], ['amount' => 'จำนวนเงิน', 'note' => 'เหตุผล']);
        $signed = $data['type'] === 'adjust_in' ? (float) $data['amount'] : -(float) $data['amount'];

        try {
            WalletService::adjust($owner, $data['type'] === 'withdraw' ? 'withdraw' : 'adjust', $signed, $request->user(), $data['note']);
        } catch (WalletException $e) {
            return back()->with('warning', $e->getMessage());
        }
        Audit::log('finance.wallet', $owner, ($data['type'] === 'withdraw' ? 'ถอนเงินคืน ' : 'ปรับยอดกระเป๋า ').baht($signed).' บาท ของ '.self::nameOf($owner).": {$data['note']}");

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
        $shop->update(['name' => $data['name'], 'location' => $data['location'] ?? null, 'is_active' => $request->boolean('is_active'),
            'show_balance' => $request->boolean('show_balance')]);
        $shop->cashiers()->sync($data['cashiers'] ?? []);

        return back()->with('success', 'บันทึกร้านค้าแล้ว');
    }

    private function productData(Request $request, ?ShopProduct $product = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'category' => ['nullable', 'string', 'max:60'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:99999'], 'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'description' => ['nullable', 'string', 'max:1000'], 'barcode' => ['nullable', 'string', 'max:40'],
            'unit' => ['nullable', 'string', 'max:20'], 'cost' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:999999'], 'image' => ['nullable', 'image', 'max:4096'],
        ], [], ['name' => 'ชื่อสินค้า', 'price' => 'ราคา', 'barcode' => 'บาร์โค้ด', 'cost' => 'ต้นทุน', 'stock' => 'สต็อก', 'image' => 'รูปสินค้า']);
        $data['sort'] = (int) ($data['sort'] ?? 0);
        unset($data['image']);
        // ช่องที่เว้นว่างในฟอร์ม = ล้างค่า (สต็อกว่าง = ไม่นับสต็อก)
        foreach (['category', 'description', 'barcode', 'unit', 'cost', 'stock'] as $field) {
            $data[$field] = $data[$field] ?? null;
        }
        if ($request->hasFile('image')) {
            if ($product?->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        } elseif ($product?->image && $request->boolean('remove_image')) {
            Storage::disk('public')->delete($product->image);
            $data['image'] = null;
        }

        return $data;
    }

    public function storeProduct(Request $request, Shop $shop)
    {
        $shop->products()->create($this->productData($request));

        return back()->with('success', 'เพิ่มสินค้าแล้ว');
    }

    public function updateProduct(Request $request, ShopProduct $product)
    {
        $product->update(['is_active' => $request->boolean('is_active')] + $this->productData($request, $product));

        return back()->with('success', 'บันทึกสินค้าแล้ว');
    }

    public function destroyProduct(ShopProduct $product)
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
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
        $sales = WalletSale::with(['shop', 'wallet.student', 'wallet.user', 'cashier'])->whereBetween('created_at', [$from, $to])->latest('id')->get();
        $valid = $sales->whereNull('voided_at');
        $shops = Shop::orderBy('name')->get()->keyBy('id');

        // ยอดขายรายสินค้า
        $products = $valid->flatMap(fn (WalletSale $s) => collect($s->items)->map(fn ($i) => ['shop' => $s->shop_id, 'name' => $i['name'], 'qty' => $i['qty'], 'sum' => $i['price'] * $i['qty'],
            'cost' => isset($i['cost']) ? $i['cost'] * $i['qty'] : null]))
            ->groupBy(fn ($r) => $r['shop'].'|'.$r['name'])
            ->map(fn ($g) => ['shop' => $shops[$g[0]['shop']]->name ?? '-', 'name' => $g[0]['name'], 'qty' => $g->sum('qty'), 'sum' => $g->sum('sum'),
                // กำไรขั้นต้นคิดได้เมื่อทุกรายการของสินค้านี้มีต้นทุน
                'cost' => $g->contains(fn ($r) => $r['cost'] === null) ? null : $g->sum('cost')])
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
