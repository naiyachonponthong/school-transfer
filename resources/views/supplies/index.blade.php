@extends('layouts.app')
@section('title', 'คลังวัสดุ')

@section('content')
@php($active = $supplies->where('is_active', true))
<div class="page-head">
    <div><h1>คลังวัสดุสิ้นเปลือง</h1><div class="sub">รับเข้า · จ่ายตามใบเบิก · บัญชีวัสดุ</div></div>
    <div class="actions">
        <a href="{{ route('supplies.report') }}" class="btn btn-light border"><i class="bi bi-bar-chart"></i> สรุปรายเดือน</a>
        <a href="{{ route('supplies.numbering') }}" class="btn btn-light border"><i class="bi bi-123"></i> รูปแบบรหัส</a>
        <a href="{{ route('supplies.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> เพิ่มวัสดุ</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-boxes"></i></div><div><div class="stat-value">{{ number_format($active->count()) }}</div><div class="stat-label">รายการวัสดุ</div></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card"><div class="stat"><div class="stat-icon tint-teal"><i class="bi bi-cash-stack"></i></div><div><div class="stat-value fs-5">{{ number_format($totalValue, 2) }}</div><div class="stat-label">มูลค่าคงคลัง (บาท)</div></div></div></div></div>
    <div class="col-6 col-lg-3"><a href="{{ route('supplies.index', ['low' => 1]) }}" class="card text-reset text-decoration-none"><div class="stat"><div class="stat-icon tint-danger"><i class="bi bi-exclamation-triangle"></i></div><div><div class="stat-value">{{ $lowCount }}</div><div class="stat-label">ใกล้หมด</div></div></div></a></div>
    <div class="col-6 col-lg-3"><a href="{{ route('requisitions.index', ['status' => 'pending']) }}" class="card text-reset text-decoration-none"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-bag-check"></i></div><div><div class="stat-value">{{ $pending }}</div><div class="stat-label">ใบเบิกรอจ่าย</div></div></div></a></div>
</div>

<form method="GET" class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
    <div class="flex-grow-1" style="min-width:220px"><label class="form-label">ค้นหา</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="ชื่อหรือรหัสวัสดุ"></div>
    <div><label class="form-label">หมวด</label><select name="category" class="form-select" data-autosubmit><option value="">ทั้งหมด</option>@foreach ($categories as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select></div>
    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="low" value="1" id="low" @checked(request('low')) data-autosubmit><label class="form-check-label" for="low">เฉพาะใกล้หมด</label></div>
</div></form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-cards table-hover align-middle mb-0">
            <thead><tr><th>วัสดุ</th><th>หมวด / ที่เก็บ</th><th style="width:220px">คงเหลือ</th><th class="text-end">ราคา/หน่วย</th><th class="text-end">มูลค่า</th><th class="text-end" style="width:170px"></th></tr></thead>
            <tbody>
            @forelse ($supplies as $s)
                <tr class="{{ $s->is_active ? '' : 'opacity-50' }}">
                    <td class="tc-title">
                        <a href="{{ route('supplies.show', $s) }}" class="d-flex gap-2 align-items-center text-reset text-decoration-none">
                            <span class="media-thumb">@if($s->photoUrl())<img src="{{ $s->photoUrl() }}" alt="">@else<i class="bi bi-box"></i>@endif</span>
                            <span><span class="fw-semibold">{{ $s->name }}</span>@unless($s->is_active) <span class="badge text-bg-light border">เลิกใช้</span>@endunless<div class="small text-muted">{{ $s->code ?: '' }}</div></span>
                        </a>
                    </td>
                    <td class="small">{{ $s->category ?: '-' }}<div class="text-muted">{{ $s->storage_location }}</div></td>
                    <td>
                        <div class="d-flex justify-content-between small"><b class="{{ $s->isLow() ? 'text-danger' : '' }}">{{ number_format($s->stock) }} {{ $s->unit }}</b><span class="text-muted">{{ $s->min_stock ? 'ขั้นต่ำ '.$s->min_stock : '' }}</span></div>
                        @if ($s->min_stock)<div class="progress mt-1" style="height:6px"><div class="progress-bar bg-{{ $s->isLow() ? 'danger' : ($s->level() < 60 ? 'warning' : 'success') }}" style="width:{{ max(3, $s->level()) }}%"></div></div>@endif
                    </td>
                    <td class="text-end small">{{ $s->unit_price ? number_format($s->unit_price, 2) : '-' }}</td>
                    <td class="text-end small">{{ $s->unit_price ? number_format($s->value(), 2) : '-' }}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-soft" data-bs-toggle="modal" data-bs-target="#move" data-move-url="{{ route('supplies.move', $s) }}" data-move-name="{{ $s->name }}" data-move-unit="{{ $s->unit }}" data-move-stock="{{ $s->stock }}" data-move-price="{{ $s->unit_price }}"><i class="bi bi-box-arrow-in-down"></i> รับเข้า</button>
                        <a href="{{ route('supplies.edit', $s) }}" class="btn btn-sm btn-light border" title="แก้ไข"><i class="bi bi-pencil"></i></a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><i class="bi bi-boxes"></i>ยังไม่มีวัสดุ<div class="mt-2"><a href="{{ route('supplies.create') }}" class="btn btn-primary btn-sm">เพิ่มวัสดุรายการแรก</a></div></div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@include('supplies._move-modal')
@endsection
