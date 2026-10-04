<?php

namespace App\Http\Controllers;

use App\Models\WalletTopup;
use App\Services\Notifier;
use App\Services\WalletException;
use App\Services\WalletService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * รับแจ้งผลการชำระจากธนาคาร/ผู้ให้บริการรับชำระ เพื่อเติมเงินเข้ากระเป๋าอัตโนมัติ
 *
 * รูปแบบกลางของระบบ: POST JSON {reference, amount, transaction_id} พร้อม header X-Signature = HMAC-SHA256(เนื้อความดิบ, รหัสลับ)
 * ธนาคารแต่ละแห่งส่งรูปแบบของตัวเอง ตอนเชื่อมจริงให้เพิ่มตัวแปลงในเมธอด fields() (หรือให้ตัวกลางแปลงมาเป็นรูปแบบนี้)
 */
class WalletGatewayController extends Controller
{
    public function hook(Request $request)
    {
        $secret = Settings::get('wallet_gateway_secret');
        abort_unless(filled($secret), 404);
        $signature = (string) $request->header('X-Signature');
        abort_unless($signature !== '' && hash_equals(hash_hmac('sha256', $request->getContent(), $secret), strtolower($signature)), 403, 'bad signature');

        [$reference, $amount, $txn] = $this->fields($request);
        if (! $reference || ! $amount || ! $txn) {
            return response()->json(['ok' => false, 'message' => 'missing fields'], 422);
        }
        // ธนาคารส่งซ้ำได้: รายการเดิมตอบสำเร็จโดยไม่เติมซ้ำ
        if (WalletTopup::where('gateway_txn', $txn)->exists()) {
            return response()->json(['ok' => true, 'result' => 'duplicate']);
        }
        $topup = WalletTopup::where('reference', $reference)->where('method', 'auto')->first();
        if (! $topup) {
            Log::warning('wallet gateway: unknown reference', ['reference' => $reference, 'txn' => $txn]);

            return response()->json(['ok' => false, 'message' => 'unknown reference'], 404);
        }
        if (round((float) $topup->amount, 2) !== round($amount, 2)) {
            Log::warning('wallet gateway: amount mismatch', ['reference' => $reference, 'expected' => $topup->amount, 'paid' => $amount]);

            return response()->json(['ok' => false, 'message' => 'amount mismatch'], 422);
        }

        try {
            WalletService::approve($topup, null, $txn);
        } catch (WalletException) {
            return response()->json(['ok' => true, 'result' => 'already processed']);
        }
        $student = $topup->wallet->student;
        Notifier::parents($student, '✅ เติมเงินเข้ากระเป๋า '.baht($topup->amount).' บาท เรียบร้อยแล้ว', route('parent.wallet', $student));

        return response()->json(['ok' => true, 'result' => 'approved']);
    }

    /** @return array{0: ?string, 1: ?float, 2: ?string} เลขอ้างอิง, จำนวนเงิน, เลขรายการของธนาคาร */
    private function fields(Request $request): array
    {
        $reference = $request->input('reference') ?? $request->input('ref1') ?? $request->input('billPaymentRef1');
        $amount = $request->input('amount');
        $txn = $request->input('transaction_id') ?? $request->input('transactionId');

        return [
            is_scalar($reference) ? strtoupper(trim((string) $reference)) : null,
            is_numeric($amount) ? (float) $amount : null,
            is_scalar($txn) && $txn !== '' ? mb_substr((string) $txn, 0, 80) : null,
        ];
    }
}
