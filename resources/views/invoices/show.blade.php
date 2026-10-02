@extends('layouts.app')
@section('title', 'ใบแจ้งหนี้ '.$invoice->invoice_no)

@section('content')
@php
    $admin = auth()->user()->isAdmin();
@endphp
<div class="page-head no-print">
    <div><h1>ใบแจ้งหนี้ {{ $invoice->invoice_no }}</h1><div class="sub">{{ $invoice->title }}</div></div>
    <div class="actions">
        <button onclick="print()" class="btn btn-light border"><i class="bi bi-printer"></i> พิมพ์</button>
        @if (session('receipt'))
            <a href="{{ route('payments.receipt', session('receipt')) }}" class="btn btn-success" target="_blank"><i class="bi bi-receipt"></i> พิมพ์ใบเสร็จ</a>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between mb-3">
                    <div>
                        <div class="fw-bold">{{ school('school_name') }}</div>
                        <div class="small text-muted">{{ school('school_address') }}</div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-{{ $invoice->statusColor() }} fs-6">{{ $invoice->statusLabel() }}</span>
                        @if ($invoice->isOverdue())<div class="small text-danger mt-1">เลยกำหนดชำระ</div>@endif
                    </div>
                </div>
                <div class="row small mb-3">
                    <div class="col-6"><span class="text-muted">นักเรียน</span><br><b>{{ $invoice->student->fullName() }}</b><br>{{ $invoice->student->student_code }} · {{ $invoice->student->classroom?->name() }}</div>
                    <div class="col-6 text-end"><span class="text-muted">วันที่ออก</span> {{ thai_date($invoice->created_at) }}<br><span class="text-muted">กำหนดชำระ</span> {{ $invoice->due_date ? thai_date($invoice->due_date) : '-' }}</div>
                </div>
                <table class="table">
                    <thead><tr><th>รายการ</th><th class="text-end">จำนวนเงิน</th></tr></thead>
                    <tbody>
                        @foreach ($invoice->items as $it)<tr><td>{{ $it->description }}</td><td class="text-end">{{ baht($it->amount) }}</td></tr>@endforeach
                    </tbody>
                    <tfoot>
                        @if ($invoice->discount > 0)<tr><td class="text-end">ส่วนลด</td><td class="text-end">-{{ baht($invoice->discount) }}</td></tr>@endif
                        <tr class="fw-bold"><td class="text-end">ยอดสุทธิ</td><td class="text-end">{{ baht($invoice->netTotal()) }}</td></tr>
                        <tr><td class="text-end">ชำระแล้ว</td><td class="text-end text-success">{{ baht($invoice->paid) }}</td></tr>
                        <tr class="fw-bold fs-5"><td class="text-end">คงค้าง</td><td class="text-end text-danger">{{ baht($invoice->balance()) }}</td></tr>
                    </tfoot>
                </table>
                @if ($invoice->balance() > 0 && (school('promptpay_id') || school('bank_info')))
                    <div class="border rounded-3 p-3 small bg-light">
                        <div class="fw-semibold mb-1"><i class="bi bi-bank"></i> ช่องทางชำระเงิน</div>
                        @if (school('promptpay_id'))<div>พร้อมเพย์: <b>{{ school('promptpay_id') }}</b></div>@endif
                        @if (school('bank_info'))<div style="white-space:pre-line">{{ school('bank_info') }}</div>@endif
                        <div class="text-muted mt-1">โอนแล้วแจ้งหลักฐานกับครูประจำชั้นหรือฝ่ายการเงิน</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-5 no-print">
        @php
            $open = in_array($invoice->status, ['unpaid', 'partial']);
            $ppPayload = $open && school('promptpay_id') ? \App\Support\PromptPay::payload(school('promptpay_id'), $invoice->balance()) : null;
            $slips = $invoice->slips()->latest()->get();
        @endphp
        @if ($ppPayload)
            <div class="card mb-3 text-center">
                <div class="card-body">
                    <div class="fw-bold mb-1"><i class="bi bi-qr-code text-primary"></i> สแกนจ่ายด้วยแอปธนาคาร</div>
                    <div class="small text-muted mb-2">QR พร้อมเพย์ ยอด {{ baht($invoice->balance()) }} บาท (ใส่ยอดให้แล้ว)</div>
                    <div class="mx-auto bg-white p-2 rounded-3 border" style="width:220px" data-qr="{{ $ppPayload }}" data-cell="4"></div>
                    <div class="small mt-2">พร้อมเพย์ {{ school('promptpay_id') }} · {{ school('school_name') }}</div>
                </div>
            </div>
        @endif
        @if ($open)
            <form method="POST" action="{{ route('slips.store', $invoice) }}" enctype="multipart/form-data" class="card mb-3">
                @csrf
                <div class="card-header"><i class="bi bi-upload"></i> แนบสลิปโอนเงิน</div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small">ยอดที่โอน</label><input type="number" step="0.01" name="amount" value="{{ $invoice->balance() }}" class="form-control" required></div>
                        <div class="col-6"><label class="form-label small">เวลาโอน</label><input type="datetime-local" name="transferred_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="form-control"></div>
                    </div>
                    <input type="file" name="slip" accept="image/*" class="form-control mt-2" required data-preview="#slipPreview">
                    <img id="slipPreview" class="d-none mt-2 rounded-3 w-100" style="max-height:220px;object-fit:contain" alt="">
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-primary w-100"><i class="bi bi-send"></i> ส่งสลิป</button></div>
            </form>
        @endif
        @if ($slips->isNotEmpty())
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-receipt-cutoff"></i> สลิปที่ส่ง</div>
                @foreach ($slips as $s)
                    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom small">
                        <a href="{{ $s->imageUrl() }}" target="_blank"><img src="{{ $s->imageUrl() }}" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:8px"></a>
                        <div class="flex-grow-1">{{ baht($s->amount) }} บาท · {{ thai_datetime($s->created_at) }}@if($s->review_note)<div class="text-muted">{{ $s->review_note }}</div>@endif</div>
                        <span class="badge bg-{{ $s->statusColor() }}">{{ $s->statusLabel() }}</span>
                        @if ($admin && $s->status === 'pending')<a href="{{ route('slips.index') }}" class="btn btn-sm btn-soft">ตรวจ</a>@endif
                    </div>
                @endforeach
            </div>
        @endif
        @if ($admin && in_array($invoice->status, ['unpaid', 'partial']))
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-cash-coin"></i> รับชำระเงิน</div>
                <form method="POST" action="{{ route('invoices.pay', $invoice) }}" class="card-body">
                    @csrf
                    <label class="form-label">จำนวนเงิน</label>
                    <div class="input-group mb-3">
                        <input type="number" step="0.01" name="amount" value="{{ $invoice->balance() }}" max="{{ $invoice->balance() }}" class="form-control form-control-lg" required>
                        <span class="input-group-text">บาท</span>
                    </div>
                    <div class="btn-group w-100 mb-3">
                        @foreach (\App\Models\Payment::METHODS as $k => $v)
                            <input type="radio" class="btn-check" name="method" value="{{ $k }}" id="m{{ $k }}" @checked($loop->first)>
                            <label class="btn btn-outline-primary" for="m{{ $k }}">{{ $v }}</label>
                        @endforeach
                    </div>
                    <input name="note" class="form-control mb-3" placeholder="หมายเหตุ (ไม่บังคับ)">
                    <button class="btn btn-success btn-lg w-100"><i class="bi bi-check2-circle"></i> ยืนยันรับชำระ</button>
                </form>
            </div>
        @endif
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> ประวัติการชำระ</div>
            @forelse ($invoice->payments as $p)
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ baht($p->amount) }} บาท <span class="small text-muted fw-normal">{{ $p->methodLabel() }}</span></div>
                        <div class="small text-muted">{{ $p->receipt_no }} · {{ thai_datetime($p->paid_at) }} · {{ $p->receiver?->name }}</div>
                    </div>
                    <a href="{{ route('payments.receipt', $p) }}" target="_blank" class="btn btn-sm btn-light border"><i class="bi bi-printer"></i> ใบเสร็จ</a>
                </div>
            @empty
                <div class="empty py-4"><i class="bi bi-hourglass"></i>ยังไม่มีการชำระ</div>
            @endforelse
        </div>
        @if ($admin && $invoice->paid == 0 && $invoice->status !== 'void')
            <form method="POST" action="{{ route('invoices.void', $invoice) }}" data-confirm="ยกเลิกใบแจ้งหนี้นี้?" class="mt-2 text-end">@csrf<button class="btn btn-sm btn-link text-danger">ยกเลิกใบแจ้งหนี้</button></form>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
@endpush
