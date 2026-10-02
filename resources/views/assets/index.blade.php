@extends('layouts.app')
@section('title', 'ครุภัณฑ์')

@section('content')
@php($inService = $summary->sum('n'))
<div class="page-head">
    <div><h1>ทะเบียนครุภัณฑ์</h1><div class="sub">{{ number_format($inService) }} รายการ · มูลค่ารวม {{ number_format($summary->sum('total'), 2) }} บาท (ไม่นับที่จำหน่ายแล้ว)</div></div>
    <div class="actions">
        <a href="{{ route('asset-checks.index') }}" class="btn btn-light border"><i class="bi bi-clipboard-check"></i> ตรวจสอบพัสดุ</a>
        <a href="{{ route('assets.labels', request()->only('location', 'category')) }}" class="btn btn-light border"><i class="bi bi-qr-code"></i> พิมพ์สติกเกอร์ QR</a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-light border"><i class="bi bi-filetype-csv"></i> ส่งออก</a>
        <a href="{{ route('assets.import') }}" class="btn btn-light border"><i class="bi bi-clipboard-plus"></i> นำเข้าจาก Excel</a>
        <a href="{{ route('assets.numbering') }}" class="btn btn-light border"><i class="bi bi-123"></i> รูปแบบเลข</a>
        <a href="{{ route('assets.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> เพิ่มครุภัณฑ์</a>
    </div>
</div>

@if ($created = session('created_ids'))
    <div class="alert alert-success d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-collection"></i><div class="flex-grow-1">เพิ่มครุภัณฑ์ชุดใหม่ {{ count($created) }} รายการแล้ว — ติดสติกเกอร์ QR ให้ครบทุกชิ้น</div>
        <a href="{{ route('assets.labels', ['ids' => implode(',', $created)]) }}" class="btn btn-sm btn-success"><i class="bi bi-qr-code"></i> พิมพ์สติกเกอร์ชุดนี้</a>
    </div>
@endif

<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach (\App\Models\Asset::STATUSES as $k => [$label, $color])
        @if ($k !== 'disposed' && isset($summary[$k]))
            <a href="{{ route('assets.index', ['status' => $k]) }}" class="badge rounded-pill text-bg-{{ $color }} text-decoration-none p-2">{{ $label }} {{ $summary[$k]->n }}</a>
        @endif
    @endforeach
</div>

<form method="GET" class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div class="flex-grow-1" style="min-width:200px"><label class="form-label">ค้นหา</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="เลขครุภัณฑ์ ชื่อ หมายเลขเครื่อง"></div>
        <div><label class="form-label">ประเภท</label><select name="category" class="form-select" data-autosubmit><option value="">ทั้งหมด</option>@foreach (array_keys(\App\Models\Asset::CATEGORIES) as $c)<option @selected(request('category') === $c)>{{ $c }}</option>@endforeach</select></div>
        <div><label class="form-label">สถานที่</label><select name="location" class="form-select" data-autosubmit><option value="">ทั้งหมด</option>@foreach ($locations as $l)<option @selected(request('location') === $l)>{{ $l }}</option>@endforeach</select></div>
        <div><label class="form-label">สถานะ</label><select name="status" class="form-select" data-autosubmit><option value="">ใช้อยู่ทั้งหมด</option>@foreach (\App\Models\Asset::STATUSES as $k => [$label])<option value="{{ $k }}" @selected(request('status') === $k)>{{ $label }}</option>@endforeach</select></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>เลขครุภัณฑ์</th><th>ชื่อ</th><th>สถานที่</th><th class="text-end">ราคา</th><th class="text-end">มูลค่าสุทธิ</th><th>สถานะ</th></tr></thead>
            <tbody>
            @forelse ($assets as $a)
                <tr>
                    <td class="fw-semibold text-nowrap"><a href="{{ route('assets.show', $a) }}" class="text-reset">{{ $a->code }}</a></td>
                    <td>{{ $a->name }}<div class="small text-muted">{{ collect([$a->category, $a->brand])->filter()->implode(' · ') }}</div></td>
                    <td class="small">{{ $a->location ?: '-' }}</td>
                    <td class="text-end small">{{ number_format($a->price, 2) }}</td>
                    <td class="text-end small">{{ $a->bookValue() !== null ? number_format($a->bookValue(), 2) : '-' }}</td>
                    <td><span class="badge text-bg-{{ $a->statusColor() }}">{{ $a->statusLabel() }}</span></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><i class="bi bi-box-seam"></i>ยังไม่มีครุภัณฑ์ — เพิ่มทีละรายการ หรือนำเข้าจาก Excel</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $assets->links() }}</div>
@endsection
