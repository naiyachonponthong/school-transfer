@extends('layouts.app')
@section('title', 'รายงานการเงิน')

@section('content')
@php($tabs = ['receipts' => 'ทะเบียนใบเสร็จ', 'category' => 'รายรับตามหมวด', 'outstanding' => 'ลูกหนี้ค้างชำระ'])
@php($query = ['tab' => $tab, 'from' => $from->toDateString(), 'to' => $to->toDateString()])
<div class="page-head">
    <div><h1>รายงานการเงิน</h1><div class="sub">{{ $tabs[$tab] }}{{ $tab !== 'outstanding' ? ' · '.thai_date($from).' – '.thai_date($to) : ' · ณ วันนี้' }}</div></div>
    <div class="actions">
        <a href="{{ route('finance.reports', $query + ['export' => 1]) }}" class="btn btn-light border"><i class="bi bi-file-earmark-spreadsheet"></i> ส่งออก Excel</a>
        <button onclick="print()" class="btn btn-light border"><i class="bi bi-printer"></i> พิมพ์</button>
    </div>
</div>

<ul class="nav nav-pills mb-3 no-print">
    @foreach ($tabs as $k => $label)
        <li class="nav-item"><a class="nav-link {{ $tab === $k ? 'active' : '' }}" href="{{ route('finance.reports', ['tab' => $k, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">{{ $label }}</a></li>
    @endforeach
</ul>

@if ($tab !== 'outstanding')
    <form method="GET" class="d-flex flex-wrap gap-2 mb-3 no-print">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control" style="max-width:180px" aria-label="ตั้งแต่วันที่">
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control" style="max-width:180px" aria-label="ถึงวันที่">
        <button class="btn btn-primary"><i class="bi bi-search"></i> ดูรายงาน</button>
    </form>
@endif

<div class="d-flex flex-wrap gap-4 mb-3">
    @foreach ($summary as $label => $value)
        <div><div class="small text-muted">{{ $label }}</div><div class="fs-5 fw-bold">{{ $value }}</div></div>
    @endforeach
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead><tr>@foreach ($head as $h)<th class="text-nowrap">{{ $h }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>@foreach ($row as $cell)<td class="{{ is_numeric($cell) && ! $loop->first ? 'text-end' : '' }}">{{ is_numeric($cell) && str_contains((string) $cell, '.') ? number_format((float) $cell, 2) : $cell }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ count($head) }}"><div class="empty py-4"><i class="bi bi-inbox"></i>ไม่มีข้อมูลในช่วงนี้</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
