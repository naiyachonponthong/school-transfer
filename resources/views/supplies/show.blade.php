@extends('layouts.app')
@section('title', 'บัญชีวัสดุ '.$supply->name)

@section('content')
<div class="page-head no-print">
    <div><h1>{{ $supply->name }}</h1><div class="sub">{{ $supply->code ? $supply->code.' · ' : '' }}{{ $supply->category ?: 'ไม่ระบุหมวด' }}</div></div>
    <div class="actions">
        <a href="{{ route('supplies.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> คลังวัสดุ</a>
        <a href="{{ route('supplies.edit', $supply) }}" class="btn btn-light border"><i class="bi bi-pencil"></i> แก้ไข</a>
        <button class="btn btn-soft" data-bs-toggle="modal" data-bs-target="#move" data-move-url="{{ route('supplies.move', $supply) }}" data-move-name="{{ $supply->name }}" data-move-unit="{{ $supply->unit }}" data-move-stock="{{ $supply->stock }}" data-move-price="{{ $supply->unit_price }}"><i class="bi bi-box-arrow-in-down"></i> รับเข้า / ปรับยอด</button>
        <button onclick="print()" class="btn btn-primary"><i class="bi bi-printer"></i> พิมพ์บัญชีวัสดุ</button>
    </div>
</div>

<div class="row g-3 mb-3 no-print">
    <div class="col-md-4 col-lg-3"><div class="media-thumb lg">@if($supply->photoUrl())<img src="{{ $supply->photoUrl() }}" alt="">@else<i class="bi bi-box"></i>@endif</div></div>
    <div class="col-md-8 col-lg-9">
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3"><div class="card"><div class="stat"><div class="stat-icon {{ $supply->isLow() ? 'tint-danger' : 'tint-success' }}"><i class="bi bi-stack"></i></div><div><div class="stat-value">{{ number_format($supply->stock) }}</div><div class="stat-label">คงเหลือ ({{ $supply->unit }})</div></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-bell"></i></div><div><div class="stat-value">{{ $supply->min_stock ?: '-' }}</div><div class="stat-label">แจ้งเตือนเมื่อเหลือ</div></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card"><div class="stat"><div class="stat-icon tint-blue"><i class="bi bi-tag"></i></div><div><div class="stat-value fs-5">{{ number_format($supply->unit_price, 2) }}</div><div class="stat-label">ราคา/หน่วย</div></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="card"><div class="stat"><div class="stat-icon tint-teal"><i class="bi bi-cash-stack"></i></div><div><div class="stat-value fs-5">{{ number_format($supply->value(), 2) }}</div><div class="stat-label">มูลค่าคงคลัง</div></div></div></div></div>
        </div>
        <div class="card"><div class="card-body small">
            <div><span class="text-muted">ที่เก็บ:</span> {{ $supply->storage_location ?: '-' }}</div>
            @if ($supply->description)<div class="mt-1"><span class="text-muted">รายละเอียด:</span> {!! nl2br(e($supply->description)) !!}</div>@endif
            @if ($supply->isLow())<div class="mt-2 text-danger fw-semibold"><i class="bi bi-exclamation-triangle-fill"></i> ใกล้หมด ควรจัดซื้อเพิ่ม</div>@endif
        </div></div>
    </div>
</div>

<div class="card doc-page"><div class="card-body p-3">
    <div class="text-center mb-2"><h2 class="h6 fw-bold mb-0">บัญชีวัสดุ</h2><div class="small">{{ school('school_name') }}</div></div>
    <div class="small mb-2">ชื่อวัสดุ <b>{{ $supply->name }}</b>{{ $supply->code ? ' · รหัส '.$supply->code : '' }} · หน่วยนับ {{ $supply->unit }} · ที่เก็บ {{ $supply->storage_location ?: '-' }} · คงเหลือ <b>{{ number_format($supply->stock) }}</b></div>
    <div class="table-responsive">
        <table class="table table-bordered table-sm small mb-0">
            <thead class="table-light text-center"><tr><th>วันที่</th><th>รายการ</th><th>รับ</th><th>จ่าย</th><th>คงเหลือ</th><th>ผู้บันทึก</th></tr></thead>
            <tbody>
            @forelse ($transactions as $t)
                <tr>
                    <td class="text-nowrap">{{ thai_date($t->created_at) }}</td>
                    <td>{{ \App\Models\SupplyTransaction::TYPES[$t->type] }}{{ $t->note ? ' · '.$t->note : '' }}@if($t->requisition) <a href="{{ route('requisitions.show', $t->requisition) }}" class="no-print">({{ $t->requisition->department ?: $t->requisition->req_no }})</a>@endif</td>
                    <td class="text-end text-success">{{ $t->quantity > 0 ? number_format($t->quantity) : '' }}</td>
                    <td class="text-end text-danger">{{ $t->quantity < 0 ? number_format(-$t->quantity) : '' }}</td>
                    <td class="text-end fw-semibold">{{ number_format($t->balance) }}</td>
                    <td>{{ $t->user?->name ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-3">ยังไม่มีรายการ</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div></div>

@include('supplies._move-modal')
@endsection
