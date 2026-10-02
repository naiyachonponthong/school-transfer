@extends('layouts.app')
@section('title', 'สรุปการจ่ายวัสดุ '.$month)

@section('content')
<div class="page-head no-print">
    <div><h1>สรุปการจ่ายวัสดุ</h1><div class="sub">{{ \App\Support\Thai::monthYear($start->month, $start->year) }}</div></div>
    <div class="actions"><form><input type="month" name="month" value="{{ $month }}" class="form-control" data-autosubmit></form><button onclick="print()" class="btn btn-light border"><i class="bi bi-printer"></i> พิมพ์</button></div>
</div>
<div class="card doc-page"><div class="card-body p-3">
    <div class="text-center mb-3"><h2 class="h6 fw-bold mb-0">สรุปการจ่ายวัสดุ {{ \App\Support\Thai::monthYear($start->month, $start->year) }}</h2><div class="small">{{ school('school_name') }}</div></div>
    <div class="row g-3 small">
        <div class="col-md-5">
            <table class="table table-bordered table-sm"><thead class="table-light"><tr><th>วัสดุ</th><th class="text-end">จ่ายรวม</th></tr></thead>
                @forelse ($byItem as $row)<tr><td>{{ $row['supply']->name }}</td><td class="text-end">{{ number_format($row['qty']) }} {{ $row['supply']->unit }}</td></tr>@empty<tr><td colspan="2" class="text-center text-muted">ไม่มีการจ่ายในเดือนนี้</td></tr>@endforelse
            </table>
        </div>
        <div class="col-md-7">
            <table class="table table-bordered table-sm"><thead class="table-light"><tr><th>หน่วยงาน</th><th>รายการที่เบิก</th></tr></thead>
                @foreach ($byDepartment as $dept => $items)
                    <tr><td>{{ $dept }}</td><td>{{ $items->map(fn ($q, $id) => $supplies[$id]->name.' '.number_format($q).' '.$supplies[$id]->unit)->implode(' · ') }}</td></tr>
                @endforeach
            </table>
        </div>
    </div>
</div></div>
@endsection
