@extends('layouts.app')
@section('title', 'ตรวจสอบพัสดุประจำปี '.$year)

@section('content')
@php($R = \App\Models\AssetCheck::RESULTS)
<div class="page-head no-print">
    <div><h1>ตรวจสอบพัสดุประจำปี {{ $year }}</h1><div class="sub">สแกน QR บนครุภัณฑ์ทีละห้อง หรือเลือกผลในตารางด้านล่าง แล้วพิมพ์รายงานเสนอผู้บริหาร</div></div>
    <div class="actions">
        <a href="{{ route('asset-checks.scan', ['year' => $year]) }}" class="btn btn-primary"><i class="bi bi-qr-code-scan"></i> เริ่มสแกน</a>
        <button onclick="print()" class="btn btn-light border"><i class="bi bi-printer"></i> พิมพ์รายงาน</button>
    </div>
</div>

<form method="GET" class="card mb-3 no-print"><div class="card-body d-flex flex-wrap gap-2 align-items-end">
    <div><label class="form-label">ปี (พ.ศ.)</label><input type="number" name="year" value="{{ $year }}" class="form-control" style="width:120px" data-autosubmit></div>
    <div><label class="form-label">สถานที่</label><select name="location" class="form-select" data-autosubmit><option value="">ทุกห้อง</option>@foreach ($locations as $l)<option @selected(request('location') === $l)>{{ $l }}</option>@endforeach</select></div>
    <div class="ms-auto d-flex gap-2 flex-wrap">
        @foreach ($R as $k => [$label, $color])<span class="badge text-bg-{{ $color }} p-2">{{ $label }} {{ $counts[$k] }}</span>@endforeach
        <span class="badge text-bg-light border p-2">ยังไม่ตรวจ {{ $counts['unchecked'] }}</span>
    </div>
</div></form>

<div class="card doc-page">
    <div class="card-body p-3">
        <div class="text-center mb-2 print-only">
            <h2 class="h6 fw-bold mb-0">รายงานผลการตรวจสอบพัสดุประจำปี {{ $year }}</h2>
            <div class="small">{{ school('school_name') }}{{ request('location') ? ' · '.request('location') : '' }}</div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle small mb-2">
                <thead class="table-light"><tr><th>ที่</th><th>เลขครุภัณฑ์</th><th>รายการ</th><th>สถานที่</th><th class="text-end">ราคา</th><th>ผลการตรวจ</th><th class="no-print"></th></tr></thead>
                <tbody>
                @forelse ($assets as $i => $a)
                    @php($c = $checks->get($a->id))
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="text-nowrap"><a href="{{ route('assets.show', $a) }}" class="text-reset">{{ $a->code }}</a></td>
                        <td>{{ $a->name }}</td>
                        <td>{{ $a->location ?: '-' }}</td>
                        <td class="text-end">{{ number_format($a->price, 2) }}</td>
                        <td>@if ($c)<span class="badge text-bg-{{ $R[$c->result][1] }}">{{ $R[$c->result][0] }}</span>@if($c->note) <span class="text-muted">{{ $c->note }}</span>@endif @else<span class="text-muted">ยังไม่ตรวจ</span>@endif</td>
                        <td class="no-print text-nowrap">
                            <form method="POST" action="{{ route('asset-checks.record') }}" class="d-flex gap-1">
                                @csrf <input type="hidden" name="code" value="{{ $a->code }}"><input type="hidden" name="year" value="{{ $year }}">
                                @foreach ($R as $k => [$label, $color])<button name="result" value="{{ $k }}" class="btn btn-sm btn-outline-{{ $color }} py-0" title="{{ $label }}">{{ ['found' => 'พบ', 'damaged' => 'ชำรุด', 'missing' => 'ไม่พบ'][$k] }}</button>@endforeach
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">ไม่มีครุภัณฑ์</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="small mb-3">
            รวม {{ $assets->count() }} รายการ มูลค่า {{ number_format($assets->sum('price'), 2) }} บาท ·
            @foreach ($R as $k => [$label])
                {{ $label }} {{ $counts[$k] }} ·
            @endforeach
            ยังไม่ตรวจ {{ $counts['unchecked'] }}
        </div>
        <div class="row g-3 mt-2">
            <x-sign class="col-4" role="ประธานกรรมการตรวจสอบพัสดุ" />
            <x-sign class="col-4" role="กรรมการ" />
            <x-sign class="col-4" role="กรรมการ" />
        </div>
    </div>
</div>
@endsection
