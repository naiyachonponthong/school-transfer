@extends('layouts.app')
@section('title', 'บัญชีวัสดุ '.$supply->name)

@section('content')
<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ route('supplies.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> คลังวัสดุ</a>
    <button onclick="print()" class="btn btn-primary ms-auto"><i class="bi bi-printer"></i> พิมพ์</button>
</div>
<div class="card doc-page" style="max-width:900px;margin:auto"><div class="card-body p-4">
    <div class="text-center mb-3"><h2 class="h6 fw-bold mb-0">บัญชีวัสดุ</h2><div class="small">{{ school('school_name') }}</div></div>
    <div class="small mb-2">ชื่อวัสดุ <b>{{ $supply->name }}</b> · หน่วยนับ {{ $supply->unit }} · หมวด {{ $supply->category ?: '-' }} · คงเหลือ <b>{{ number_format($supply->stock) }}</b></div>
    <table class="table table-bordered table-sm small">
        <thead class="table-light text-center"><tr><th>วันที่</th><th>รายการ</th><th>รับ</th><th>จ่าย</th><th>คงเหลือ</th><th>ผู้บันทึก</th></tr></thead>
        <tbody>
        @forelse ($transactions as $t)
            <tr>
                <td class="text-nowrap">{{ thai_date($t->created_at) }}</td>
                <td>{{ \App\Models\SupplyTransaction::TYPES[$t->type] }}{{ $t->note ? ' · '.$t->note : '' }}@if($t->requisition) <a href="{{ route('requisitions.show', $t->requisition) }}" class="no-print">({{ $t->requisition->department ?: $t->requisition->req_no }})</a>@endif</td>
                <td class="text-end">{{ $t->quantity > 0 ? number_format($t->quantity) : '' }}</td>
                <td class="text-end">{{ $t->quantity < 0 ? number_format(-$t->quantity) : '' }}</td>
                <td class="text-end fw-semibold">{{ number_format($t->balance) }}</td>
                <td>{{ $t->user?->name ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted">ยังไม่มีรายการ</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
@endsection
