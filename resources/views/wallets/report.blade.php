@extends('layouts.app')
@section('title', 'รายงานกระเป๋าเงิน')

@section('content')
@php
    $salesTotal = $byShop->sum('total');
@endphp
<div class="page-head">
    <div><h1>รายงานกระเป๋าเงินและการขาย</h1><div class="sub">{{ thai_date($from) }}{{ $from->isSameDay($to) ? '' : ' – '.thai_date($to) }}</div></div>
    <form class="actions d-print-none" method="GET">
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control" aria-label="ตั้งแต่วันที่">
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control" aria-label="ถึงวันที่">
        <button class="btn btn-primary">ดู</button>
        <button type="button" class="btn btn-light border" onclick="window.print()"><i class="bi bi-printer"></i> พิมพ์</button>
        <a href="{{ route('wallets.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-info"><i class="bi bi-basket"></i></div><div><div class="stat-value">{{ baht($salesTotal) }}</div><div class="stat-label">ยอดขาย (ต้องจ่ายให้ร้าน)</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-success"><i class="bi bi-cash-coin"></i></div><div><div class="stat-value">{{ baht($topups['cash']->total ?? 0) }}</div><div class="stat-label">เติมเงินสด {{ $topups['cash']->n ?? 0 }} ครั้ง</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-success"><i class="bi bi-qr-code"></i></div><div><div class="stat-value">{{ baht($topups['transfer']->total ?? 0) }}</div><div class="stat-label">เติมด้วยการโอน {{ $topups['transfer']->n ?? 0 }} ครั้ง</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-wallet2"></i></div><div><div class="stat-value">{{ baht($outstanding) }}</div><div class="stat-label">เงินคงค้างในกระเป๋า (ณ ตอนนี้)</div></div></div></div></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-lg-5"><div class="card h-100">
        <div class="card-header"><i class="bi bi-shop"></i> ยอดขายแยกร้าน</div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>ร้าน</th><th class="text-end">รายการ</th><th class="text-end">ยอดขาย</th></tr></thead>
            <tbody>
            @forelse ($byShop as $row)
                <tr><td>{{ $row['name'] }}</td><td class="text-end">{{ $row['count'] }}</td><td class="text-end fw-semibold">{{ baht($row['total']) }}</td></tr>
            @empty
                <tr><td colspan="3"><div class="empty py-4"><i class="bi bi-shop"></i>ไม่มีการขายในช่วงนี้</div></td></tr>
            @endforelse
            </tbody>
            @if ($byShop->isNotEmpty())<tfoot><tr><th>รวม</th><th class="text-end">{{ $byShop->sum('count') }}</th><th class="text-end">{{ baht($salesTotal) }}</th></tr></tfoot>@endif
        </table></div>
        @if ($withdrawn != 0)<div class="card-footer small text-muted">ถอนเงินคืนผู้ปกครองในช่วงนี้ {{ baht(abs($withdrawn)) }} บาท</div>@endif
    </div></div>
    <div class="col-lg-7"><div class="card h-100">
        <div class="card-header"><i class="bi bi-grid"></i> ยอดขายแยกสินค้า</div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>สินค้า</th><th>ร้าน</th><th class="text-end">จำนวน</th><th class="text-end">ยอดขาย</th><th class="text-end">กำไรขั้นต้น</th></tr></thead>
            <tbody>
            @forelse ($products as $row)
                <tr><td>{{ $row['name'] }}</td><td class="small text-muted">{{ $row['shop'] }}</td><td class="text-end">{{ $row['qty'] }}</td><td class="text-end">{{ baht($row['sum']) }}</td><td class="text-end small">{{ $row['cost'] === null ? '-' : baht($row['sum'] - $row['cost']) }}</td></tr>
            @empty
                <tr><td colspan="5"><div class="empty py-4"><i class="bi bi-grid"></i>ไม่มีการขายในช่วงนี้</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div></div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-receipt"></i> รายการขายทั้งหมด <span class="ms-2 small text-muted fw-normal">ยกเลิก {{ $voided->count() }} รายการ</span></div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>เวลา</th><th>ร้าน</th><th>นักเรียน</th><th>รายการ</th><th>ผู้ขาย</th><th class="text-end">ยอด</th><th class="d-print-none"></th></tr></thead>
        <tbody>
        @forelse ($sales as $s)
            <tr class="{{ $s->voided_at ? 'text-muted' : '' }}">
                <td class="small text-nowrap">{{ thai_datetime($s->created_at) }}</td>
                <td class="small">{{ $s->shop->name }}</td>
                <td><a href="{{ route('wallets.student', $s->wallet->student) }}">{{ $s->wallet->student->fullName() }}</a></td>
                <td class="small">{{ $s->itemsLabel() }}@if ($s->voided_at)<div class="text-danger">ยกเลิก: {{ $s->void_reason }}</div>@endif</td>
                <td class="small">{{ $s->cashier?->name ?? '-' }}</td>
                <td class="text-end {{ $s->voided_at ? 'text-decoration-line-through' : 'fw-semibold' }}">{{ baht($s->total) }}</td>
                <td class="text-end d-print-none">@if (! $s->voided_at)<button class="btn btn-sm btn-light border text-danger" data-bs-toggle="modal" data-bs-target="#void{{ $s->id }}">ยกเลิก</button>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty py-4"><i class="bi bi-receipt"></i>ไม่มีการขายในช่วงนี้</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

@foreach ($sales->whereNull('voided_at') as $s)
    <div class="modal fade" id="void{{ $s->id }}" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('pos.void', $s) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">ยกเลิกรายการ {{ baht($s->total) }} บาท</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body">
                <div class="small text-muted mb-2">{{ $s->wallet->student->fullName() }} · {{ $s->itemsLabel() }} · เงินจะคืนเข้ากระเป๋านักเรียน</div>
                <label class="form-label">เหตุผล</label><input name="reason" class="form-control" maxlength="200" required>
            </div>
            <div class="modal-footer"><button class="btn btn-danger">ยกเลิกรายการนี้</button></div>
        </form></div>
    </div>
@endforeach
@endsection
