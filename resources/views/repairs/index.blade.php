@extends('layouts.app')
@section('title', 'แจ้งซ่อม')

@section('content')
<div class="page-head">
    <div><h1>แจ้งซ่อม</h1><div class="sub">{{ $manager ? 'งานซ่อมทั้งหมดของโรงเรียน' : 'รายการที่คุณแจ้ง' }}</div></div>
    <div class="actions">
        @if ($manager)<a href="{{ route('repairs.report') }}" class="btn btn-light border"><i class="bi bi-bar-chart"></i> สรุปรายเดือน</a>@endif
        <a href="{{ route('repairs.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> แจ้งซ่อม</a>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
    @php($open = collect(\App\Models\RepairRequest::OPEN)->sum(fn ($s) => $counts[$s] ?? 0))
    <a href="{{ route('repairs.index', ['status' => 'open']) }}" class="btn btn-sm {{ $status === 'open' ? 'btn-dark' : 'btn-light border' }}">ยังไม่เสร็จ {{ $open }}</a>
    @foreach (\App\Models\RepairRequest::STATUSES as $k => [$label])
        <a href="{{ route('repairs.index', ['status' => $k]) }}" class="btn btn-sm {{ $status === $k ? 'btn-dark' : 'btn-light border' }}">{{ $label }} {{ $counts[$k] ?? 0 }}</a>
    @endforeach
    <a href="{{ route('repairs.index', ['status' => 'all']) }}" class="btn btn-sm {{ $status === 'all' ? 'btn-dark' : 'btn-light border' }}">ทั้งหมด</a>
    <form class="ms-auto"><input type="hidden" name="status" value="{{ $status }}"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="ค้นหา เลขที่ อาการ สถานที่"></form>
</div>

<div class="card">
    <div class="list-group list-group-flush">
        @forelse ($repairs as $r)
            <a href="{{ route('repairs.show', $r) }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-center">
                @if ($r->photo)<img src="{{ $r->photoUrl() }}" alt="" class="rounded flex-shrink-0" style="width:52px;height:52px;object-fit:cover">@else<span class="app-ico flex-shrink-0" style="width:52px;height:52px"><i class="bi bi-tools"></i></span>@endif
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold">@if($r->priority === 'urgent' && $r->isOpen())<span class="badge text-bg-danger">ด่วน</span> @endif{{ $r->title }}</div>
                    <div class="small text-muted">{{ $r->ticket_no }} · {{ $r->location ?: '-' }}{{ $r->asset ? ' · '.$r->asset->code : '' }} · {{ thai_date($r->created_at) }}{{ $manager ? ' · '.($r->reporter?->name ?? '-') : '' }}{{ $r->assignee ? ' → '.$r->assignee->name : '' }}</div>
                </div>
                <span class="badge text-bg-{{ $r->statusColor() }}">{{ $r->statusLabel() }}</span>
            </a>
        @empty
            <div class="list-group-item"><div class="empty"><i class="bi bi-tools"></i>ไม่มีรายการ</div></div>
        @endforelse
    </div>
</div>
<div class="mt-3">{{ $repairs->links() }}</div>
@endsection
