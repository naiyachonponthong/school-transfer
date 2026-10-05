@extends('layouts.app')
@section('title', 'ขอซื้อ/ขอจ้าง')

@section('content')
<div class="page-head">
    <div><h1>ขอซื้อ/ขอจ้าง</h1><div class="sub">ขอใช้เงินจากงบของโครงการ ผ่านการอนุมัติตามลำดับขั้น{{ $awaiting->isNotEmpty() ? ' · รอคุณพิจารณา '.$awaiting->count().' ใบ' : '' }}</div></div>
    <div class="actions">
        <a href="{{ route('projects.index') }}" class="btn btn-light border"><i class="bi bi-kanban"></i> โครงการ</a>
        <a href="{{ route('purchases.create') }}" class="btn btn-primary"><i class="bi bi-cart-plus"></i> เขียนใบขอซื้อ/ขอจ้าง</a>
    </div>
</div>

@if ($awaiting->isNotEmpty())
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-hourglass-split"></i> รอคุณพิจารณา <span class="ms-2 badge bg-warning text-dark">{{ $awaiting->count() }}</span></div>
        @foreach ($awaiting as $r)
            <a href="{{ route('purchases.show', $r) }}" class="d-flex flex-wrap align-items-center gap-2 px-3 py-2 border-bottom text-decoration-none text-body">
                <div class="flex-grow-1"><span class="fw-semibold">{{ $r->title }}</span><div class="small text-muted">{{ $r->req_no }} · {{ $r->budget->project->name }} · ขอโดย {{ $r->requester?->name ?? '-' }} · {{ thai_date($r->created_at) }}</div></div>
                <span class="small text-muted">ขั้น{{ $r->currentStepLabel() }}</span>
                <span class="fw-semibold">{{ baht($r->total) }}</span>
            </a>
        @endforeach
    </div>
@endif

@if ($canSeeAll)
    <ul class="nav nav-pills mb-3 gap-1">
        <li class="nav-item"><a class="nav-link {{ $tab === 'mine' ? 'active' : '' }}" href="{{ route('purchases.index', ['tab' => 'mine']) }}">ที่ฉันขอ</a></li>
        <li class="nav-item"><a class="nav-link {{ $tab === 'all' ? 'active' : '' }}" href="{{ route('purchases.index', ['tab' => 'all']) }}">ทั้งหมด</a></li>
    </ul>
@endif

<div class="card">
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>เลขที่</th><th>วันที่</th><th>เรื่อง</th><th>โครงการ</th>@if ($tab === 'all')<th>ผู้ขอ</th>@endif<th class="text-end">ยอด</th><th>สถานะ</th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
            <tr>
                <td class="text-nowrap"><a href="{{ route('purchases.show', $r) }}">{{ $r->req_no }}</a></td>
                <td class="small text-nowrap">{{ thai_date($r->created_at) }}</td>
                <td>{{ $r->title }}<div class="small text-muted">{{ \App\Models\PurchaseRequest::KINDS[$r->kind] }} · {{ $r->budget->label() }}</div></td>
                <td class="small">{{ $r->budget->project->name }}</td>
                @if ($tab === 'all')<td class="small">{{ $r->requester?->name ?? '-' }}</td>@endif
                <td class="text-end">{{ baht($r->total) }}</td>
                <td><span class="badge bg-{{ $r->statusColor() }}">{{ $r->statusLabel() }}</span>@if ($r->currentStepLabel())<div class="small text-muted">รอ{{ $r->currentStepLabel() }}</div>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty py-4"><i class="bi bi-cart"></i>ยังไม่มีใบขอซื้อ/ขอจ้าง กด "เขียนใบขอซื้อ/ขอจ้าง" เพื่อเริ่ม</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    @if ($requests->hasPages())<div class="card-footer bg-transparent">{{ $requests->links() }}</div>@endif
</div>
@endsection
