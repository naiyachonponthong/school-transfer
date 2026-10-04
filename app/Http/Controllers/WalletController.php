<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use App\Models\WalletTopup;
use App\Services\WalletService;
use App\Support\PromptPay;
use App\Support\Settings;
use Illuminate\Http\Request;

/**
 * กระเป๋าเงินฝั่งเจ้าของ: ผู้ปกครอง (ของบุตรหลาน) นักเรียน (ดูอย่างเดียว) และครู/บุคลากร (ของตัวเอง)
 * ดูยอดและรายการ เติมเงินด้วยการโอนหรือสแกนจ่ายอัตโนมัติ ตั้งวงเงินต่อวัน ระงับการใช้จ่าย
 */
class WalletController extends Controller
{
    /** ผู้ปกครอง: กระเป๋าของบุตรหลาน (ไม่ระบุ = คนแรก) */
    public function parent(Request $request, ?Student $student = null)
    {
        $children = $request->user()->children()->with('classroom')->get();
        $student ??= $children->first();
        abort_unless($student && $children->contains('id', $student->id), 403);

        return $this->page($request, $student, $children, true);
    }

    /** นักเรียน: กระเป๋าของตัวเอง (ดูได้อย่างเดียว) */
    public function student(Request $request)
    {
        $student = $request->user()->studentProfile;
        abort_unless($student, 404);

        return $this->page($request, $student, collect(), false);
    }

    /** ครู/บุคลากร: กระเป๋าของตัวเอง */
    public function mine(Request $request)
    {
        return $this->page($request, $request->user(), collect(), true);
    }

    /** ที่อยู่ของการกระทำต่าง ๆ ในหน้ากระเป๋า ต่างกันตามว่าเป็นกระเป๋าของนักเรียน (ผู้ปกครองจัดการ) หรือของบุคลากรเอง */
    private function urls(Student|User $owner, ?WalletTopup $auto = null): array
    {
        return $owner instanceof Student
            ? ['base' => route('parent.wallet', $owner), 'topup' => route('parent.wallet.topup', $owner), 'auto' => route('parent.wallet.auto', $owner),
                'settings' => route('parent.wallet.settings', $owner), 'status' => $auto ? route('parent.wallet.status', [$owner, $auto]) : null]
            : ['base' => route('wallet.mine'), 'topup' => route('wallet.mine.topup'), 'auto' => route('wallet.mine.auto'),
                'settings' => route('wallet.mine.settings'), 'status' => $auto ? route('wallet.mine.status', $auto) : null];
    }

    private function page(Request $request, Student|User $owner, $children, bool $canManage)
    {
        $wallet = WalletService::for($owner);
        $amount = (float) $request->query('amount', 0);
        $amount = $amount >= 1 && $amount <= 20000 ? round($amount, 2) : null;
        $promptpay = Settings::get('promptpay_id');
        $biller = Settings::get('wallet_biller_id');
        // รายการสแกนจ่ายอัตโนมัติที่เปิดค้างไว้ (แสดง QR ชำระบิลของรายการนั้น)
        $auto = $canManage && $biller ? $wallet->topups()->where('method', 'auto')->whereKey((int) $request->query('topup'))->first() : null;

        return view('wallet.show', [
            'student' => $owner instanceof Student ? $owner : null,
            'ownerName' => $wallet->ownerName(), 'ownerSub' => $wallet->ownerSub(),
            'wallet' => $wallet, 'children' => $children, 'canManage' => $canManage,
            'urls' => $this->urls($owner, $auto),
            'transactions' => $wallet->transactions()->with('sale.shop')->latest('id')->limit(60)->get(),
            'topups' => $wallet->topups()->where('method', 'transfer')->latest('id')->limit(5)->get(),
            'spentToday' => $wallet->spentToday(),
            'amount' => $amount,
            'promptpay' => $promptpay,
            'qr' => $canManage && $amount && $promptpay ? PromptPay::payload($promptpay, $amount) : null,
            'biller' => $biller,
            'auto' => $auto,
            'autoQr' => $auto && $auto->status === 'pending' ? PromptPay::billPayment($biller, $auto->reference, (float) $auto->amount) : null,
        ]);
    }

    /** เจ้าของกระเป๋าของคำขอนี้: บุตรหลานที่ผู้ปกครองดูแล หรือบุคลากรเอง */
    private function owner(Request $request, ?Student $student): Student|User
    {
        if ($student) {
            abort_unless($student->isGuardedBy($request->user()), 403);

            return $student;
        }
        abort_unless($request->user()->isStaff(), 403);

        return $request->user();
    }

    /** เปิดรายการสแกนจ่ายอัตโนมัติ: ได้ QR ชำระบิลที่มีเลขอ้างอิง เงินเข้าเองเมื่อธนาคารแจ้งผล */
    public function auto(Request $request, ?Student $student = null)
    {
        $owner = $this->owner($request, $student);
        abort_unless(filled(Settings::get('wallet_biller_id')), 404);
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1', 'max:20000']], [], ['amount' => 'จำนวนเงิน']);
        $topup = WalletService::openAutoTopup($owner, round((float) $data['amount'], 2), $request->user());

        return redirect()->to($this->urls($owner)['base'].'?topup='.$topup->id);
    }

    /** หน้า QR ถามสถานะเป็นระยะ เพื่อแจ้งทันทีเมื่อเงินเข้า */
    public function status(Request $request, Student $student, WalletTopup $topup)
    {
        abort_unless($student->isGuardedBy($request->user()) && $topup->wallet->student_id === $student->id, 403);

        return response()->json(['status' => $topup->status, 'balance' => (float) $topup->wallet->balance]);
    }

    public function mineStatus(Request $request, WalletTopup $topup)
    {
        abort_unless($topup->wallet->user_id === $request->user()->id, 403);

        return response()->json(['status' => $topup->status, 'balance' => (float) $topup->wallet->balance]);
    }

    /** ส่งสลิปการโอนเพื่อเติมเงิน (เงินเข้ากระเป๋าเมื่อการเงินอนุมัติ) */
    public function topup(Request $request, ?Student $student = null)
    {
        $owner = $this->owner($request, $student);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:20000'],
            'slip' => ['required', 'image', 'max:6144'],
        ], ['slip.required' => 'กรุณาแนบรูปสลิป'], ['amount' => 'จำนวนเงิน']);

        // สลิปใบเดิมส่งซ้ำไม่ได้ (ยกเว้นใบที่ถูกตีกลับ)
        $hash = hash_file('sha256', $request->file('slip')->getRealPath());
        if (WalletTopup::where('slip_hash', $hash)->where('status', '!=', 'rejected')->exists()) {
            return back()->withErrors(['slip' => 'สลิปนี้เคยส่งเข้าระบบแล้ว']);
        }
        WalletService::for($owner)->topups()->create([
            'amount' => $data['amount'], 'method' => 'transfer', 'status' => 'pending', 'slip_hash' => $hash,
            'slip' => $request->file('slip')->store('wallet-slips', 'local'), 'requested_by' => $request->user()->id,
        ]);

        return redirect()->to($this->urls($owner)['base'])->with('success', 'ส่งสลิปแล้ว ฝ่ายการเงินจะตรวจสอบ เงินจะเข้ากระเป๋าเมื่ออนุมัติ');
    }

    /** วงเงินต่อวันและการระงับการใช้จ่าย */
    public function settings(Request $request, ?Student $student = null)
    {
        $owner = $this->owner($request, $student);
        $data = $request->validate(['daily_limit' => ['nullable', 'numeric', 'min:1', 'max:20000']], [], ['daily_limit' => 'วงเงินต่อวัน']);
        WalletService::for($owner)->update(['daily_limit' => $data['daily_limit'] ?? null, 'is_frozen' => $request->boolean('is_frozen')]);

        return back()->with('success', 'บันทึกการตั้งค่ากระเป๋าเงินแล้ว');
    }
}
