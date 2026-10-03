@extends('layouts.app')
@section('title', 'ปิดยอดรายวัน')

@section('content')
<div class="page-head">
    <div><h1>ปิดยอดรับเงินรายวัน</h1><div class="sub">{{ \App\Support\Thai::fullDate($date) }} · {{ $payments->count() }} ใบเสร็จ · รวม {{ baht($payments->sum('amount')) }} บาท</div></div>
    <div class="actions">
        <form method="GET"><input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control" data-autosubmit aria-label="วันที่"></form>
        <button onclick="print()" class="btn btn-light border no-print"><i class="bi bi-printer"></i> พิมพ์ใบนำส่ง</button>
    </div>
</div>

<div class="row g-3 mb-3">
    @foreach (\App\Models\Payment::METHODS as $k => $label)
        <div class="col-md-4"><div class="card"><div class="card-body"><div class="small text-muted">{{ $label }}</div><div class="fs-4 fw-bold">{{ baht($byMethod[$k]) }}</div></div></div></div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-receipt"></i> ใบเสร็จของวันนี้</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>เลขที่</th><th>นักเรียน</th><th>รายการ</th><th>วิธีชำระ</th><th class="text-end">จำนวนเงิน</th><th>ผู้รับเงิน</th></tr></thead>
                    <tbody>
                    @forelse ($payments as $p)
                        <tr>
                            <td class="text-nowrap"><a href="{{ route('invoices.show', $p->invoice) }}">{{ $p->receipt_no }}</a></td>
                            <td>{{ $p->invoice->student->fullName() }} <span class="text-muted small">{{ $p->invoice->student->classroom?->name() }}</span></td>
                            <td class="small">{{ $p->invoice->title }}</td>
                            <td class="small">{{ $p->methodLabel() }}</td>
                            <td class="text-end">{{ baht($p->amount) }}</td>
                            <td class="small">{{ $p->receiver?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty py-4"><i class="bi bi-hourglass"></i>ไม่มีการรับเงินในวันนี้</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-person-check"></i> แยกตามผู้รับเงิน</div>
            @forelse ($byReceiver as $r)
                <div class="d-flex px-3 py-2 border-bottom small"><span class="flex-grow-1">{{ $r['name'] }} <span class="text-muted">{{ $r['count'] }} ใบ</span></span><b>{{ baht($r['amount']) }}</b></div>
            @empty
                <div class="empty py-3">-</div>
            @endforelse
        </div>

        <div class="card mb-3 no-print">
            <div class="card-header"><i class="bi bi-lock"></i> สถานะ</div>
            <div class="card-body">
                @if ($closing)
                    <div class="text-success fw-semibold"><i class="bi bi-check-circle-fill"></i> ปิดยอดแล้ว {{ baht($closing->total()) }} บาท ({{ $closing->receipts }} ใบ)</div>
                    <div class="small text-muted">โดย {{ $closing->closer?->name }} เมื่อ {{ thai_datetime($closing->created_at) }}{{ $closing->note ? ' · '.$closing->note : '' }}</div>
                    @if (abs($closing->total() - $payments->sum('amount')) > 0.001)<div class="small text-danger mt-1">ยอดปัจจุบันไม่ตรงกับยอดตอนปิด</div>@endif
                    @if (auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('finance.reopen', $closing) }}" data-confirm="ยกเลิกการปิดยอดวันนี้?" class="mt-2">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger">ยกเลิกการปิดยอด</button></form>
                    @endif
                @else
                    <form method="POST" action="{{ route('finance.close') }}" data-confirm="ปิดยอดวันที่ {{ thai_date($date) }}? หลังปิดแล้วจะยกเลิกใบเสร็จของวันนี้ไม่ได้">
                        @csrf
                        <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                        <input name="note" class="form-control mb-2" placeholder="หมายเหตุ เช่น นำฝากธนาคารแล้ว">
                        <button class="btn btn-primary w-100"><i class="bi bi-lock"></i> ปิดยอดวันนี้</button>
                    </form>
                    <div class="small text-muted mt-2">ปิดยอดแล้วจะยกเลิกใบเสร็จของวันนี้ไม่ได้ จนกว่าผู้ดูแลระบบจะยกเลิกการปิดยอด</div>
                @endif
            </div>
        </div>

        <div class="card no-print">
            <div class="card-header"><i class="bi bi-clock-history"></i> ปิดยอดล่าสุด</div>
            @forelse ($recent as $c)
                <a href="{{ route('finance.closing', ['date' => $c->date->toDateString()]) }}" class="d-flex px-3 py-2 border-bottom small text-decoration-none text-body"><span class="flex-grow-1">{{ thai_date($c->date) }}</span><b>{{ baht($c->total()) }}</b></a>
            @empty
                <div class="empty py-3">ยังไม่เคยปิดยอด</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
