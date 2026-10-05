@extends('layouts.app')
@section('title', $project->name)

@section('content')
@php
    $p = $project;
    $year = $p->fiscal_year;
    $budget = $lines->sum(fn ($r) => (float) $r['line']->amount);
    $committed = $lines->sum('committed');
    $pending = $lines->sum('pending');
@endphp
<div class="page-head">
    <div><h1>{{ $p->name }}</h1><div class="sub">{{ $p->code }} · ปีงบประมาณ {{ $p->fiscal_year }}{{ $p->department ? ' · '.$p->department->name : '' }} · ผู้รับผิดชอบ {{ $p->owner?->name ?? '-' }}{{ $p->starts_on ? ' · '.thai_date($p->starts_on).($p->ends_on ? ' – '.thai_date($p->ends_on) : '') : '' }}</div></div>
    <div class="actions">
        <a href="{{ route('projects.index', ['year' => $p->fiscal_year]) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> โครงการ</a>
        @if ($canManage)
            <button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#editProject"><i class="bi bi-pencil"></i> แก้ไข</button>
            <button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#closeProject">{{ $p->isClosed() ? 'เปิดโครงการอีกครั้ง' : 'ปิดโครงการ' }}</button>
        @endif
        @unless ($p->isClosed() || $lines->isEmpty())<a href="{{ route('purchases.create', ['line' => $lines->first()['line']->id]) }}" class="btn btn-primary"><i class="bi bi-cart-plus"></i> ขอซื้อ/ขอจ้าง</a>@endunless
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-cash-stack"></i></div><div><div class="stat-value">{{ baht($budget, 0) }}</div><div class="stat-label">งบของโครงการ</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-lock"></i></div><div><div class="stat-value">{{ baht($committed, 0) }}</div><div class="stat-label">ผูกพันแล้ว</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-info"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-value">{{ baht($pending, 0) }}</div><div class="stat-label">รออนุมัติ</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-success"><i class="bi bi-piggy-bank"></i></div><div><div class="stat-value">{{ baht($budget - $committed - $pending, 0) }}</div><div class="stat-label">คงเหลือ{{ $p->isClosed() ? ' · ปิดโครงการแล้ว' : '' }}</div></div></div></div></div>
</div>

@if ($p->objective || $p->summary)
    <div class="card mb-3"><div class="card-body small row g-3">
        @if ($p->objective)<div class="col-md-{{ $p->summary ? 6 : 12 }}"><div class="fw-semibold mb-1">วัตถุประสงค์/เป้าหมาย</div><div style="white-space:pre-line">{{ $p->objective }}</div></div>@endif
        @if ($p->summary)<div class="col-md-{{ $p->objective ? 6 : 12 }}"><div class="fw-semibold mb-1">สรุปผลโครงการ</div><div style="white-space:pre-line">{{ $p->summary }}</div></div>@endif
    </div></div>
@endif

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-list-columns"></i> งบของโครงการ
        @if ($canManage && ! $p->isClosed())<button class="btn btn-sm btn-light border ms-auto" data-bs-toggle="modal" data-bs-target="#addLine"><i class="bi bi-plus-lg"></i> เพิ่ม/แก้งบ</button>@endif
    </div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>หมวดรายจ่าย</th><th>แหล่งเงิน</th><th class="text-end">งบ</th><th class="text-end">ผูกพันแล้ว</th><th class="text-end">รออนุมัติ</th><th class="text-end">คงเหลือ</th><th></th></tr></thead>
        <tbody>
        @forelse ($lines as $r)
            @php($l = $r['line'])
            <tr>
                <td>{{ $l->categoryLabel() }}</td>
                <td class="small">{{ $l->source->name }}</td>
                <td class="text-end">{{ baht($l->amount) }}</td>
                <td class="text-end">{{ baht($r['committed']) }}</td>
                <td class="text-end">{{ baht($r['pending']) }}</td>
                <td class="text-end fw-semibold">{{ baht((float) $l->amount - $r['committed'] - $r['pending']) }}</td>
                <td class="text-end text-nowrap">
                    @unless ($p->isClosed())<a href="{{ route('purchases.create', ['line' => $l->id]) }}" class="btn btn-sm btn-light border">ขอซื้อ/ขอจ้าง</a>@endunless
                    @if ($canManage && $r['committed'] + $r['pending'] == 0)
                        <form method="POST" action="{{ route('projects.lines.destroy', $l) }}" class="d-inline" data-confirm="ลบงบ {{ $l->label() }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" aria-label="ลบ {{ $l->label() }}"><i class="bi bi-trash"></i></button></form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty py-4"><i class="bi bi-list-columns"></i>ยังไม่ได้ตั้งงบ{{ $canManage ? ' กด "เพิ่ม/แก้งบ" เพื่อจัดสรรจากแหล่งเงิน' : '' }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-cart-check"></i> ใบขอซื้อ/ขอจ้างของโครงการ</div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>เลขที่</th><th>วันที่</th><th>เรื่อง</th><th>งบที่ใช้</th><th>ผู้ขอ</th><th class="text-end">ยอด</th><th>สถานะ</th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
            <tr>
                <td class="text-nowrap"><a href="{{ route('purchases.show', $r) }}">{{ $r->req_no }}</a></td>
                <td class="small text-nowrap">{{ thai_date($r->created_at) }}</td>
                <td>{{ $r->title }}</td>
                <td class="small">{{ $r->budget->label() }}</td>
                <td class="small">{{ $r->requester?->name ?? '-' }}</td>
                <td class="text-end">{{ baht($r->total) }}</td>
                <td><span class="badge bg-{{ $r->statusColor() }}">{{ $r->statusLabel() }}</span>@if ($r->currentStepLabel())<div class="small text-muted">รอ{{ $r->currentStepLabel() }}</div>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty py-4"><i class="bi bi-cart"></i>ยังไม่มีใบขอซื้อ/ขอจ้าง</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

@if ($canManage)
    <div class="modal fade" id="editProject" tabindex="-1">
        <div class="modal-dialog modal-lg"><form method="POST" action="{{ route('projects.update', $p) }}" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">แก้ไขโครงการ</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body row g-3">@include('budget._project-fields', ['p' => $p])</div>
            <div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div>
        </form></div>
    </div>
    <div class="modal fade" id="closeProject" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('projects.close', $p) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">{{ $p->isClosed() ? 'เปิดโครงการอีกครั้ง' : 'ปิดโครงการ' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body">
                @if ($p->isClosed())
                    <div class="small text-muted">เปิดแล้วขอซื้อ/ขอจ้างจากงบของโครงการได้อีกครั้ง</div>
                @else
                    <div class="small text-muted mb-2">ปิดแล้วขอซื้อ/ขอจ้างเพิ่มไม่ได้ งบคงเหลือ {{ baht($budget - $committed - $pending) }} บาท</div>
                    <label class="form-label">สรุปผลโครงการ</label><textarea name="summary" rows="4" class="form-control" maxlength="5000" required></textarea>
                @endif
            </div>
            <div class="modal-footer"><button class="btn btn-primary">{{ $p->isClosed() ? 'เปิดโครงการ' : 'ปิดโครงการ' }}</button></div>
        </form></div>
    </div>
    <div class="modal fade" id="addLine" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('projects.lines.save', $p) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">เพิ่ม/แก้งบของโครงการ</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body row g-3">
                @if ($sources->isEmpty())
                    <div class="col-12 small text-muted">ยังไม่มีแหล่งเงินของปีงบประมาณ {{ $p->fiscal_year }} ให้เพิ่มที่<a href="{{ route('budget.index', ['year' => $p->fiscal_year]) }}">หน้างบประมาณ</a>ก่อน</div>
                @else
                    <div class="col-12">
                        <label class="form-label">แหล่งเงิน</label>
                        <select name="budget_source_id" class="form-select" required>
                            @foreach ($sources as $s)<option value="{{ $s->id }}">{{ $s->name }} (เหลือจัดสรรได้ {{ baht(max(0, (float) $s->amount - $s->allocated())) }})</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">หมวดรายจ่าย</label>
                        <select name="category" class="form-select">
                            @foreach (\App\Models\ProjectBudget::CATEGORIES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6"><label class="form-label">จำนวนเงิน (บาท)</label><input type="number" name="amount" class="form-control" min="0" step="0.01" inputmode="decimal" required></div>
                    <div class="col-12 small text-muted">แหล่งเงินและหมวดที่มีอยู่แล้ว = แก้ยอดของบรรทัดนั้น ลดต่ำกว่ายอดที่ผูกพันและรออนุมัติไม่ได้</div>
                @endif
            </div>
            @if ($sources->isNotEmpty())<div class="modal-footer"><button class="btn btn-primary">บันทึกงบ</button></div>@endif
        </form></div>
    </div>
@endif
@endsection
