@extends('layouts.app')
@section('title', 'เบิกวัสดุ')

@section('content')
<div class="page-head">
    <div><h1>เบิกวัสดุ</h1><div class="sub">{{ $manager ? 'ใบเบิกทั้งหมด (รอจ่ายขึ้นก่อน)' : 'ใบเบิกของคุณ' }}</div></div>
    <div class="actions">
        @if ($manager)<a href="{{ route('supplies.index') }}" class="btn btn-light border"><i class="bi bi-boxes"></i> คลังวัสดุ</a>@endif
        <a href="{{ route('requisitions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> เขียนใบเบิก</a>
    </div>
</div>
<div class="d-flex gap-2 mb-3">
    <a href="{{ route('requisitions.index') }}" class="btn btn-sm {{ request('status') ? 'btn-light border' : 'btn-dark' }}">ทั้งหมด</a>
    @foreach (\App\Models\SupplyRequisition::STATUSES as $k => [$label])<a href="{{ route('requisitions.index', ['status' => $k]) }}" class="btn btn-sm {{ request('status') === $k ? 'btn-dark' : 'btn-light border' }}">{{ $label }}</a>@endforeach
</div>
<div class="card">
    <div class="list-group list-group-flush">
        @forelse ($requisitions as $r)
            <a href="{{ route('requisitions.show', $r) }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-center">
                <div class="flex-grow-1">
                    <div class="fw-semibold">{{ $r->req_no }} <span class="fw-normal text-muted small">· {{ $r->department ?: '-' }}{{ $manager ? ' · '.($r->requester?->name ?? '-') : '' }} · {{ thai_date($r->created_at) }}</span></div>
                    <div class="small text-muted">{{ $r->items->map(fn ($i) => $i->supply->name.' '.$i->quantity.' '.$i->supply->unit)->implode(' · ') }}</div>
                </div>
                <span class="badge text-bg-{{ $r->statusColor() }}">{{ $r->statusLabel() }}</span>
            </a>
        @empty
            <div class="list-group-item"><div class="empty"><i class="bi bi-bag"></i>ยังไม่มีใบเบิก</div></div>
        @endforelse
    </div>
</div>
<div class="mt-3">{{ $requisitions->links() }}</div>
@endsection
