@extends('layouts.app')
@section('title', 'สรุปงานซ่อม '.$month)

@section('content')
<div class="page-head no-print">
    <div><h1>สรุปงานซ่อมรายเดือน</h1><div class="sub">{{ \App\Support\Thai::monthYear($start->month, $start->year) }}</div></div>
    <div class="actions">
        <form class="d-flex gap-2"><input type="month" name="month" value="{{ $month }}" class="form-control" data-autosubmit></form>
        <button onclick="print()" class="btn btn-light border"><i class="bi bi-printer"></i> พิมพ์</button>
    </div>
</div>
<div class="card doc-page"><div class="card-body p-3">
    <div class="text-center mb-3">
        <h2 class="h6 fw-bold mb-0">สรุปการแจ้งซ่อม {{ \App\Support\Thai::monthYear($start->month, $start->year) }}</h2>
        <div class="small">{{ school('school_name') }}</div>
    </div>
    <div class="row g-3 small mb-3">
        <div class="col-md-4"><div class="border rounded p-2 text-center"><div class="text-muted">แจ้งทั้งหมด</div><div class="fs-4 fw-bold">{{ $repairs->count() }}</div></div></div>
        <div class="col-md-4"><div class="border rounded p-2 text-center"><div class="text-muted">ค่าใช้จ่ายรวม</div><div class="fs-4 fw-bold">{{ number_format($cost, 2) }}</div></div></div>
        <div class="col-md-4"><div class="border rounded p-2 text-center"><div class="text-muted">เวลาเฉลี่ยจนซ่อมเสร็จ</div><div class="fs-4 fw-bold">{{ $avgDays !== null ? $avgDays.' วัน' : '-' }}</div></div></div>
    </div>
    <div class="row g-3 small">
        <div class="col-md-5">
            <table class="table table-cards table-bordered table-sm mb-2"><thead class="table-light"><tr><th>สถานะ</th><th class="text-end">จำนวน</th></tr></thead>
                @foreach (\App\Models\RepairRequest::STATUSES as $k => [$label])<tr><td>{{ $label }}</td><td class="text-end">{{ $byStatus[$k] ?? 0 }}</td></tr>@endforeach
            </table>
            <table class="table table-cards table-bordered table-sm"><thead class="table-light"><tr><th>สถานที่ที่แจ้งบ่อย</th><th class="text-end">ครั้ง</th></tr></thead>
                @foreach ($byLocation->take(10) as $loc => $n)<tr><td>{{ $loc }}</td><td class="text-end">{{ $n }}</td></tr>@endforeach
            </table>
        </div>
        <div class="col-md-7">
            <table class="table table-cards table-bordered table-sm"><thead class="table-light"><tr><th>เลขที่</th><th>รายการ</th><th>สถานะ</th><th class="text-end">ค่าใช้จ่าย</th></tr></thead>
                @forelse ($repairs as $r)
                    <tr><td class="text-nowrap">{{ $r->ticket_no }}</td><td>{{ $r->title }}<div class="text-muted">{{ $r->location }}</div></td><td>{{ $r->statusLabel() }}</td><td class="text-end">{{ $r->cost !== null ? number_format($r->cost, 2) : '' }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted">ไม่มีการแจ้งซ่อมในเดือนนี้</td></tr>
                @endforelse
            </table>
        </div>
    </div>
</div></div>
@endsection
