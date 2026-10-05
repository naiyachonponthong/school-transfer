@extends('layouts.app')
@section('title', 'งบประมาณ')

@section('content')
@php
    $total = $rows->sum(fn ($r) => (float) $r['source']->amount);
    $allocated = $rows->sum('allocated');
    $committed = $rows->sum('committed');
    $pending = $rows->sum('pending');
@endphp
<div class="page-head">
    <div><h1>งบประมาณ</h1><div class="sub">ปีงบประมาณ {{ $year }} (1 ต.ค. {{ $year - 1 }} – 30 ก.ย. {{ $year }}) · {{ $projectCount }} โครงการ</div></div>
    <div class="actions">
        <form method="GET"><select name="year" class="form-select" data-autosubmit aria-label="ปีงบประมาณ">
            @foreach ($years as $y)<option value="{{ $y }}" @selected($y === $year)>ปีงบ {{ $y }}</option>@endforeach
        </select></form>
        <a href="{{ route('projects.index', ['year' => $year]) }}" class="btn btn-light border"><i class="bi bi-kanban"></i> โครงการ</a>
        <a href="{{ route('purchases.index') }}" class="btn btn-light border"><i class="bi bi-cart-check"></i> ขอซื้อ/ขอจ้าง</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSource"><i class="bi bi-plus-lg"></i> เพิ่มแหล่งเงิน</button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-bank"></i></div><div><div class="stat-value">{{ baht($total, 0) }}</div><div class="stat-label">วงเงินที่ได้รับ</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-info"><i class="bi bi-kanban"></i></div><div><div class="stat-value">{{ baht($allocated, 0) }}</div><div class="stat-label">จัดสรรให้โครงการแล้ว</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-lock"></i></div><div><div class="stat-value">{{ baht($committed, 0) }}</div><div class="stat-label">ผูกพันแล้ว · รออนุมัติ {{ baht($pending, 0) }}</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-success"><i class="bi bi-piggy-bank"></i></div><div><div class="stat-value">{{ baht($total - $allocated, 0) }}</div><div class="stat-label">ยังไม่ได้จัดสรร</div></div></div></div></div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-bank"></i> แหล่งเงิน</div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>แหล่งเงิน</th><th class="text-end">วงเงิน</th><th class="text-end">จัดสรรแล้ว</th><th class="text-end">ผูกพันแล้ว</th><th class="text-end">รออนุมัติ</th><th class="text-end">ยังไม่จัดสรร</th><th></th></tr></thead>
        <tbody>
        @forelse ($rows as $r)
            @php($s = $r['source'])
            <tr>
                <td><span class="fw-semibold">{{ $s->name }}</span><div class="small text-muted">{{ $s->note }}</div></td>
                <td class="text-end">{{ baht($s->amount) }}</td>
                <td class="text-end">{{ baht($r['allocated']) }}</td>
                <td class="text-end">{{ baht($r['committed']) }}</td>
                <td class="text-end">{{ baht($r['pending']) }}</td>
                <td class="text-end fw-semibold">{{ baht((float) $s->amount - $r['allocated']) }}</td>
                <td class="text-end"><button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#editSource{{ $s->id }}" aria-label="แก้ไข {{ $s->name }}"><i class="bi bi-pencil"></i></button></td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty py-4"><i class="bi bi-bank"></i>ยังไม่มีแหล่งเงินของปีงบประมาณนี้ กด "เพิ่มแหล่งเงิน" เพื่อเริ่ม</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<form method="POST" action="{{ route('budget.steps') }}" class="card">
    @csrf
    <div class="card-header"><i class="bi bi-diagram-3"></i> ขั้นอนุมัติใบขอซื้อ/ขอจ้าง</div>
    <div class="card-body row g-3">
        @foreach (\App\Models\PurchaseRequest::STEPS as $key => [$label, $who])
            <div class="col-md-6 col-xl-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="steps[]" value="{{ $key }}" id="step{{ $key }}" @checked(in_array($key, $steps, true))>
                    <label class="form-check-label" for="step{{ $key }}">{{ $loop->iteration }}. {{ $label }}</label>
                </div>
                <div class="form-text ms-4">{{ $who }}</div>
            </div>
        @endforeach
        <div class="col-12 small text-muted">ใบขอซื้อผ่านขั้นที่ติ๊กตามลำดับ 1 → 4 อนุมัติครบจึงผูกพันงบ · ผู้รับผิดชอบโครงการขอเองจะข้ามขั้นแรก · เปลี่ยนแล้วมีผลกับใบที่ยื่นหลังจากนี้</div>
    </div>
    <div class="card-footer bg-transparent text-end"><button class="btn btn-light border">บันทึกขั้นอนุมัติ</button></div>
</form>

@php($fields = fn ($s = null) => view('budget._source-fields', ['s' => $s, 'year' => $year])->render())
<div class="modal fade" id="addSource" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('budget.sources.store') }}" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">เพิ่มแหล่งเงิน</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
        <div class="modal-body row g-3">{!! $fields() !!}</div>
        <div class="modal-footer"><button class="btn btn-primary">เพิ่มแหล่งเงิน</button></div>
    </form></div>
</div>
@foreach ($rows as $r)
    <div class="modal fade" id="editSource{{ $r['source']->id }}" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('budget.sources.update', $r['source']) }}" id="sourceForm{{ $r['source']->id }}">
                @csrf @method('PUT')
                <div class="modal-header"><h5 class="modal-title">แก้ไขแหล่งเงิน</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
                <div class="modal-body row g-3">{!! $fields($r['source']) !!}</div>
            </form>
            <div class="modal-footer">
                @if ($r['allocated'] == 0)
                    <form method="POST" action="{{ route('budget.sources.destroy', $r['source']) }}" class="me-auto" data-confirm="ลบแหล่งเงิน {{ $r['source']->name }}?">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash"></i> ลบ</button></form>
                @endif
                <button class="btn btn-primary" form="sourceForm{{ $r['source']->id }}">บันทึก</button>
            </div>
        </div></div>
    </div>
@endforeach
@endsection
