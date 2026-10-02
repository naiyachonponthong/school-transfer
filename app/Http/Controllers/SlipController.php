<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentSlip;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** ผู้ปกครองแนบสลิปโอนเงิน → การเงินตรวจแล้วกดยืนยัน ระบบออกใบเสร็จให้ */
class SlipController extends Controller
{
    public function store(Request $request, Invoice $invoice)
    {
        $user = $request->user();
        abort_unless($user->isStaff() || $invoice->student->isGuardedBy($user), 403);
        abort_if(in_array($invoice->status, ['paid', 'void'], true), 422, 'ใบแจ้งหนี้นี้ชำระครบหรือถูกยกเลิกแล้ว');

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:'.$invoice->balance()],
            'slip' => ['required', 'image', 'max:6144'],
            'transferred_at' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['slip.required' => 'กรุณาแนบรูปสลิป', 'amount.max' => 'ยอดเกินยอดค้างชำระ']);

        PaymentSlip::create([
            'invoice_id' => $invoice->id,
            'amount' => $data['amount'],
            'image' => $request->file('slip')->store('slips', 'public'),
            'transferred_at' => $data['transferred_at'] ?? now(),
            'note' => $data['note'] ?? null,
            'uploaded_by' => $user->id,
        ]);

        return back()->with('success', 'ส่งสลิปแล้ว ฝ่ายการเงินจะตรวจสอบและออกใบเสร็จให้');
    }

    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        return view('invoices.slips', [
            'slips' => PaymentSlip::with(['invoice.student.classroom', 'uploader', 'reviewer'])
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest()->paginate(30)->withQueryString(),
            'status' => $status,
            'pendingCount' => PaymentSlip::where('status', 'pending')->count(),
        ]);
    }

    public function approve(Request $request, PaymentSlip $slip)
    {
        abort_unless($slip->status === 'pending', 422, 'สลิปนี้ตรวจแล้ว');
        $invoice = $slip->invoice;
        $amount = min($slip->amount, $invoice->balance());
        abort_if($amount <= 0, 422, 'ใบแจ้งหนี้นี้ชำระครบแล้ว');

        DB::transaction(function () use ($slip, $invoice, $amount, $request) {
            $payment = $invoice->payments()->create([
                'receipt_no' => Payment::nextNumber(),
                'amount' => $amount,
                'method' => 'transfer',
                'paid_at' => $slip->transferred_at ?? now(),
                'note' => 'สลิปโอนเงิน #'.$slip->id,
                'received_by' => $request->user()->id,
            ]);
            $slip->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'payment_id' => $payment->id]);
            $invoice->refreshTotals();
        });

        Notifier::parents($invoice->student, "💰 ยืนยันการชำระ {$invoice->title} จำนวน ".baht($amount).' บาทแล้ว ขอบคุณครับ', route('invoices.show', $invoice));

        return back()->with('success', 'ยืนยันสลิปและออกใบเสร็จแล้ว');
    }

    public function reject(Request $request, PaymentSlip $slip)
    {
        abort_unless($slip->status === 'pending', 422, 'สลิปนี้ตรวจแล้ว');
        $slip->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $request->input('note')]);
        Notifier::parents($slip->invoice->student, '⚠️ สลิปที่ส่งสำหรับ '.$slip->invoice->title.' ไม่ผ่านการตรวจสอบ'.($slip->review_note ? ': '.$slip->review_note : '').' กรุณาส่งใหม่หรือติดต่อฝ่ายการเงิน', route('invoices.show', $slip->invoice));

        return back()->with('success', 'แจ้งผู้ปกครองว่าสลิปไม่ผ่านแล้ว');
    }
}
