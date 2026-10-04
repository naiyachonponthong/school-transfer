<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ใบเสร็จ {{ $payment->receipt_no }}</title>
    @include('partials.assets')
    <style>
        body { background: #fff; }
        .receipt { max-width: 720px; margin: 2rem auto; border: 1px solid #ddd; padding: 2.2rem; border-radius: 8px; }
        @media print { .receipt { border: 0; margin: 0; padding: 0; } .no-print { display: none; } }
        /* จอมือถือ: ลดขอบ และให้หัวใบเสร็จขึ้นบรรทัดใหม่แทนการล้นจอ */
        @media screen and (max-width: 767px) {
            .receipt { margin: .5rem; padding: 1rem; }
            .receipt > .d-flex:first-child { flex-wrap: wrap; gap: .75rem; }
            .receipt table { font-size: .88rem; }
            .receipt .table thead th { white-space: normal; }
            .receipt .table th[style] { width: auto !important; }
        }
    </style>
</head>
<body>
@php($inv = $payment->invoice)
<div class="receipt">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div class="d-flex gap-3 align-items-center">
            @if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" style="height:64px" alt="">@endif
            <div>
                <div class="fw-bold fs-5">{{ school('school_name') }}</div>
                <div class="small text-muted">{{ school('school_address') }} {{ school('school_phone') ? 'โทร '.school('school_phone') : '' }}</div>
            </div>
        </div>
        <div class="text-end">
            <div class="fw-bold fs-5">ใบเสร็จรับเงิน</div>
            <div class="small">เลขที่ {{ $payment->receipt_no }}</div>
            @if ($payment->isVoided())<div class="fw-bold text-danger border border-danger px-2 d-inline-block mt-1">ยกเลิก · {{ thai_date($payment->voided_at) }}</div>@endif
            <div class="small">วันที่ {{ thai_date($payment->paid_at, true) }}</div>
        </div>
    </div>
    <div class="mb-3">
        ได้รับเงินจาก ผู้ปกครองของ <b>{{ $inv->student->fullName() }}</b>
        รหัส {{ $inv->student->student_code }} ชั้น {{ $inv->student->classroom?->name() }}
    </div>
    <table class="table table-bordered">
        <thead class="table-light"><tr><th>รายการ ({{ $inv->title }} · {{ $inv->invoice_no }})</th><th class="text-end" style="width:160px">จำนวนเงิน</th></tr></thead>
        <tbody>
            @foreach ($inv->items as $it)<tr><td>{{ $it->description }}</td><td class="text-end">{{ baht($it->amount) }}</td></tr>@endforeach
        </tbody>
        <tfoot>
            <tr class="fw-bold"><td class="text-end">รับชำระครั้งนี้ ({{ $payment->methodLabel() }})</td><td class="text-end">{{ baht($payment->amount) }}</td></tr>
            <tr><td colspan="2" class="text-center">( {{ \App\Support\Thai::bahtText($payment->amount) }} )</td></tr>
            @if ($inv->balance() > 0)<tr><td class="text-end small text-muted">คงค้างหลังชำระ</td><td class="text-end small">{{ baht($inv->balance()) }}</td></tr>@endif
        </tfoot>
    </table>
    <div class="row mt-5 text-center">
        <div class="col-6 offset-6">
            <div>ลงชื่อ ....................................... ผู้รับเงิน</div>
            <div class="mt-1">( {{ $payment->receiver?->name }} )</div>
        </div>
    </div>
</div>
<div class="text-center no-print mb-4"><button onclick="print()" class="btn btn-primary"><i class="bi bi-printer"></i> พิมพ์</button></div>
</body>
</html>
