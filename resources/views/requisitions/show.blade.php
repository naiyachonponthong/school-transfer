@extends('layouts.app')
@section('title', 'ใบเบิกวัสดุ '.$req->req_no)

@section('content')
<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('requisitions.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    <span class="badge text-bg-{{ $req->statusColor() }} align-self-center fs-6">{{ $req->statusLabel() }}</span>
    <button onclick="print()" class="btn btn-light border ms-auto"><i class="bi bi-printer"></i> พิมพ์ใบเบิก</button>
</div>

<form method="POST" action="{{ route('requisitions.issue', $req) }}">
@csrf
<div class="card doc-page" style="max-width:820px;margin:auto"><div class="card-body p-4">
    <div class="d-flex justify-content-between small"><span></span><span>เลขที่ {{ $req->req_no }}</span></div>
    <div class="text-center mb-3"><h2 class="h6 fw-bold mb-0">ใบเบิกวัสดุ</h2><div class="small">{{ school('school_name') }}</div></div>
    <div class="small mb-2">ข้าพเจ้า <b>{{ $req->requester?->name ?? '-' }}</b> {{ $req->department ? 'กลุ่มสาระ/งาน '.$req->department : '' }} ขอเบิกวัสดุ{{ $req->purpose ? 'เพื่อ'.$req->purpose : '' }} ตามรายการต่อไปนี้ · วันที่ {{ thai_date($req->created_at, true) }}</div>
    <table class="table table-bordered table-sm small">
        <thead class="table-light text-center"><tr><th style="width:40px">ที่</th><th>รายการ</th><th style="width:110px">จำนวนที่ขอ</th><th style="width:140px">จำนวนที่จ่าย</th><th style="width:90px" class="no-print">คงคลัง</th></tr></thead>
        <tbody>
        @foreach ($req->items as $i => $item)
            <tr>
                <td class="text-center">{{ $i + 1 }}</td>
                <td>{{ $item->supply->name }}</td>
                <td class="text-center">{{ number_format($item->quantity) }} {{ $item->supply->unit }}</td>
                <td class="text-center">
                    @if ($manager && $req->status === 'pending')
                        <input type="number" min="0" max="{{ min($item->quantity, $item->supply->stock) }}" name="issued[{{ $item->id }}]" value="{{ min($item->quantity, $item->supply->stock) }}" class="form-control form-control-sm text-center no-print">
                    @else
                        {{ $item->issued !== null ? number_format($item->issued).' '.$item->supply->unit : '' }}
                    @endif
                </td>
                <td class="text-center no-print {{ $item->supply->stock < $item->quantity ? 'text-danger' : '' }}">{{ number_format($item->supply->stock) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @if ($req->review_note)<div class="small mb-2">หมายเหตุงานพัสดุ: {{ $req->review_note }}</div>@endif
    <div class="row g-3 mt-3">
        <x-sign class="col-4" role="ผู้เบิก" :name="$req->requester?->name" />
        <x-sign class="col-4" role="ผู้จ่าย (งานพัสดุ)" :name="$req->reviewer?->name" />
        <x-sign class="col-4" role="ผู้รับของ" />
    </div>
</div></div>

@if ($manager && $req->status === 'pending')
    <div class="card mt-3 no-print" style="max-width:820px;margin:auto"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <input name="review_note" class="form-control" style="max-width:360px" placeholder="หมายเหตุถึงผู้เบิก (ถ้ามี)">
        <button class="btn btn-success"><i class="bi bi-box-arrow-up"></i> จ่ายวัสดุและตัดสต็อก</button>
        <button formaction="{{ route('requisitions.reject', $req) }}" class="btn btn-outline-danger" data-confirm="ไม่อนุมัติใบเบิกนี้?">ไม่อนุมัติ</button>
        <span class="small text-muted">ปรับจำนวนที่จ่ายในตารางได้ (ไม่เกินคงคลัง · 0 = ไม่จ่ายรายการนั้น)</span>
    </div></div>
@endif
</form>

@if (! $manager && $req->status === 'pending' && $req->requester_id === auth()->id())
    <form method="POST" action="{{ route('requisitions.cancel', $req) }}" class="text-center mt-3 no-print" data-confirm="ยกเลิกใบเบิกนี้?">@csrf<button class="btn btn-sm btn-link text-danger">ยกเลิกใบเบิก</button></form>
@endif
@endsection
