<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Student;
use App\Models\User;
use App\Services\WalletException;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * สแกนจ่ายจากกระเป๋าเงิน: ลูกค้าเปิดกล้องจากหน้ากระเป๋าเงิน สแกน QR ที่ร้านแสดง ตรวจรายการ แล้วยืนยันตัดเงินจากกระเป๋าของตัวเอง
 * QR ของร้านคือที่อยู่ /pay/{token} ของรายการขายที่คนขายเปิดไว้ (อยู่ได้ไม่กี่นาที ใช้ได้ครั้งเดียว)
 */
class WalletPayController extends Controller
{
    /** รายการขายที่รอให้ลูกค้าสแกนจ่ายอยู่ได้กี่นาที */
    public const TTL_MINUTES = 10;

    public static function key(string $token): string
    {
        return 'pos-pay:'.$token;
    }

    /**
     * กระเป๋าที่ผู้ใช้คนนี้จ่ายได้: นักเรียน = ของตัวเอง · ครู/บุคลากร = ของตัวเอง · ผู้ปกครอง = ของบุตรหลาน
     *
     * @return array<string, Student|User>  key "s:ID" หรือ "u:ID"
     */
    private function payers(User $user): array
    {
        if ($user->isStudent()) {
            return $user->studentProfile ? ['s:'.$user->studentProfile->id => $user->studentProfile] : [];
        }
        if ($user->isParent()) {
            return $user->children()->where('status', 'active')->get()->mapWithKeys(fn (Student $s) => ['s:'.$s->id => $s])->all();
        }

        return $user->isStaff() ? ['u:'.$user->id => $user] : [];
    }

    /** หน้ากล้องสแกน QR ของร้าน */
    public function scan()
    {
        return view('wallet.scan');
    }

    /** หน้ายืนยันการจ่าย: ร้าน รายการ ยอดรวม และกระเป๋าที่จะตัดเงิน */
    public function show(Request $request, string $token)
    {
        $order = Cache::get(self::key($token));
        $shop = $order ? Shop::find($order['shop_id']) : null;
        $payers = collect($this->payers($request->user()))->map(fn ($owner, $key) => [
            'key' => $key,
            'name' => $owner instanceof Student ? $owner->fullName() : $owner->name,
            'wallet' => WalletService::for($owner),
        ])->values();

        return view('wallet.pay', ['token' => $token, 'order' => $shop ? $order : null, 'shop' => $shop, 'payers' => $payers]);
    }

    public function confirm(Request $request, string $token)
    {
        $data = $request->validate(['payer' => ['required', 'string']], [], ['payer' => 'กระเป๋าเงิน']);
        $owner = $this->payers($request->user())[$data['payer']] ?? null;
        abort_unless($owner, 403, 'คุณจ่ายจากกระเป๋านี้ไม่ได้');

        // กันสองคนสแกน QR ใบเดียวกันแล้วกดยืนยันพร้อมกัน
        $lock = Cache::lock(self::key($token).':lock', 10);
        if (! $lock->get()) {
            return back()->with('warning', 'รายการนี้กำลังถูกดำเนินการ ลองใหม่อีกครั้ง');
        }
        try {
            $order = Cache::get(self::key($token));
            $shop = $order ? Shop::find($order['shop_id']) : null;
            $cashier = $order ? User::find($order['cashier_id']) : null;
            if (! $shop || ! $cashier) {
                return back()->with('warning', 'QR นี้หมดอายุแล้ว ให้ร้านสร้าง QR ใหม่');
            }
            if ($order['status'] !== 'pending') {
                return back()->with('warning', 'รายการนี้ชำระไปแล้ว');
            }
            try {
                $sale = WalletService::charge($owner, $shop, $order['items'], $cashier, $order['client_key']);
            } catch (WalletException $e) {
                return back()->with('warning', $e->getMessage());
            }
            // ถ้ารายการนี้ถูกตัดจากกระเป๋าอื่นไปแล้ว (เช่น คนขายแตะบัตรให้พร้อมกัน) ไม่ถือว่าคนนี้จ่าย
            if ($sale->wallet_id !== WalletService::for($owner)->id) {
                return back()->with('warning', 'รายการนี้ชำระไปแล้ว');
            }
            $wallet = $sale->wallet->fresh();
            Cache::put(self::key($token), ['status' => 'paid', 'sale_id' => $sale->id, 'balance' => (float) $wallet->balance,
                'customer' => ['name' => $wallet->ownerName(), 'sub' => $wallet->ownerSub(),
                    'photo' => $owner instanceof Student ? $owner->photoUrl() : $owner->avatarUrl(), 'initials' => $owner->initials()]] + $order,
                now()->addMinutes(self::TTL_MINUTES));
        } finally {
            $lock->release();
        }

        return redirect()->route('wallet.pay', $token)->with('success', 'จ่าย '.baht($sale->total).' บาท ให้ '.$shop->name.' แล้ว · คงเหลือ '.baht($wallet->balance).' บาท');
    }
}
