@extends('layouts.app')
@section('title', $project->name)

@section('content')
@php
    $p = $project;
    $year = $p->fiscal_year;
    $budget = (float) $p->activities->flatMap->budgets->sum('amount');
    $spent = collect($usage)->sum('spent');
    $pending = collect($usage)->sum('pending');
@endphp
<div class="page-head">
    <div><h1>{{ $p->name }}</h1><div class="sub">{{ $p->code }} · ปีงบประมาณ {{ $p->fiscal_year }} · {{ \App\Models\Project::TRACKS[$p->track] ?? $p->track }}{{ $p->department ? ' · '.$p->department->name : '' }} · ผู้รับผิดชอบ {{ $p->owner?->name ?? '-' }}{{ $p->starts_on ? ' · '.thai_date($p->starts_on).($p->ends_on ? ' – '.thai_date($p->ends_on) : '') : '' }}</div></div>
    <div class="actions">
        <a href="{{ route('projects.index', ['year' => $p->fiscal_year]) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> โครงการ</a>
        @if ($canManage)
            <button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#editProject"><i class="bi bi-pencil"></i> แก้ไข</button>
            <button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#closeProject">{{ $p->isClosed() ? 'เปิดโครงการอีกครั้ง' : 'ปิดโครงการ' }}</button>
            @unless ($p->isClosed())<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#activityNew"><i class="bi bi-plus-lg"></i> เพิ่มกิจกรรม</button>@endunless
        @endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-cash-stack"></i></div><div><div class="stat-value">{{ baht($budget, 0) }}</div><div class="stat-label">งบของโครงการ · {{ $p->activities->count() }} กิจกรรม</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-scissors"></i></div><div><div class="stat-value">{{ baht($spent, 0) }}</div><div class="stat-label">ตัดงบแล้ว</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-info"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-value">{{ baht($pending, 0) }}</div><div class="stat-label">รอพิจารณา</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-success"><i class="bi bi-piggy-bank"></i></div><div><div class="stat-value">{{ baht($budget - $spent - $pending, 0) }}</div><div class="stat-label">คงเหลือ{{ $p->isClosed() ? ' · ปิดโครงการแล้ว' : '' }}</div></div></div></div></div>
</div>

@if ($p->objective || $p->summary)
    <div class="card mb-3"><div class="card-body small row g-3">
        @if ($p->objective)<div class="col-md-{{ $p->summary ? 6 : 12 }}"><div class="fw-semibold mb-1">วัตถุประสงค์/เป้าหมาย</div><div style="white-space:pre-line">{{ $p->objective }}</div></div>@endif
        @if ($p->summary)<div class="col-md-{{ $p->objective ? 6 : 12 }}"><div class="fw-semibold mb-1">สรุปผลโครงการ</div><div style="white-space:pre-line">{{ $p->summary }}</div></div>@endif
    </div></div>
@endif

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-list-task"></i> กิจกรรมและงบ</div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>กิจกรรม</th><th>งบแยกประเภทเงิน</th><th class="text-end">งบรวม</th><th class="text-end">ตัดงบแล้ว</th><th class="text-end">รอพิจารณา</th><th class="text-end">คงเหลือ</th><th></th></tr></thead>
        <tbody>
        @forelse ($p->activities as $a)
            @php $total = (float) $a->budgets->sum('amount'); @endphp
            <tr>
                <td><span class="fw-semibold">{{ $a->name }}</span>@if ($a->detail)<div class="small text-muted">{{ $a->detail }}</div>@endif</td>
                <td class="small">@forelse ($a->budgets->sortBy('budget_source_id') as $b)<div>{{ $b->source->name }} {{ baht($b->amount) }}</div>@empty<span class="text-muted">ยังไม่ได้ตั้งงบ</span>@endforelse</td>
                <td class="text-end">{{ baht($total) }}</td>
                <td class="text-end">{{ baht($usage[$a->id]['spent']) }}</td>
                <td class="text-end">{{ baht($usage[$a->id]['pending']) }}</td>
                <td class="text-end fw-semibold">{{ baht($total - $usage[$a->id]['spent'] - $usage[$a->id]['pending']) }}</td>
                <td class="text-end text-nowrap">
                    @unless ($p->isClosed() || $total <= 0)<a href="{{ route('budget-requests.create', ['activity' => $a->id]) }}" class="btn btn-sm btn-light border">ขอใช้งบ</a>@endunless
                    @if ($canManage)<button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#activity{{ $a->id }}" aria-label="แก้ไขกิจกรรม {{ $a->name }}"><i class="bi bi-pencil"></i></button>@endif
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty py-4"><i class="bi bi-list-task"></i>ยังไม่มีกิจกรรม{{ $canManage ? ' กด "เพิ่มกิจกรรม" แล้วใส่งบแยกประเภทเงิน' : '' }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-cart-check"></i> คำขอใช้งบของโครงการ</div>
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>เลขที่</th><th>วันที่</th><th>เรื่อง</th><th>กิจกรรม</th><th>ผู้ขอ</th><th class="text-end">ยอด</th><th>สถานะ</th></tr></thead>
        <tbody>
        @forelse ($requests as $r)
            <tr>
                <td class="text-nowrap"><a href="{{ route('budget-requests.show', $r) }}">{{ $r->req_no }}</a></td>
                <td class="small text-nowrap">{{ thai_date($r->created_at) }}</td>
                <td>{{ $r->title }}<div class="small text-muted">{{ $r->typeLabel() }}</div></td>
                <td class="small">{{ $r->activity->name }}</td>
                <td class="small">{{ $r->requester?->name ?? '-' }}</td>
                <td class="text-end">{{ baht($r->total) }}</td>
                <td><span class="badge bg-{{ $r->statusColor() }}">{{ $r->statusLabel() }}</span>@if ($r->currentStepLabel())<div class="small text-muted">รอ{{ $r->currentStepLabel() }}</div>@endif</td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty py-4"><i class="bi bi-cart"></i>ยังไม่มีคำขอใช้งบ</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

@if ($canManage)
    @php
        $activityForm = function ($a = null) use ($p, $sources, $sourceLeft) {
            return view('budget._activity-fields', ['a' => $a, 'sources' => $sources, 'sourceLeft' => $sourceLeft, 'p' => $p])->render();
        };
    @endphp
    <div class="modal fade" id="activityNew" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('projects.activities.store', $p) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">เพิ่มกิจกรรม</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body row g-3">{!! $activityForm() !!}</div>
            @if ($sources->isNotEmpty())<div class="modal-footer"><button class="btn btn-primary">เพิ่มกิจกรรม</button></div>@endif
        </form></div>
    </div>
    @foreach ($p->activities as $a)
        <div class="modal fade" id="activity{{ $a->id }}" tabindex="-1">
            <div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('projects.activities.update', [$p, $a]) }}" id="activityForm{{ $a->id }}">
                    @csrf @method('PUT')
                    <div class="modal-header"><h5 class="modal-title">แก้ไขกิจกรรม</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
                    <div class="modal-body row g-3">{!! $activityForm($a) !!}</div>
                </form>
                <div class="modal-footer">
                    @if ($usage[$a->id]['spent'] + $usage[$a->id]['pending'] == 0 && ! $requests->contains('project_activity_id', $a->id))
                        <form method="POST" action="{{ route('projects.activities.destroy', $a) }}" class="me-auto" data-confirm="ลบกิจกรรม {{ $a->name }}?">@csrf @method('DELETE')<button class="btn btn-outline-danger"><i class="bi bi-trash"></i> ลบ</button></form>
                    @endif
                    <button class="btn btn-primary" form="activityForm{{ $a->id }}">บันทึก</button>
                </div>
            </div></div>
        </div>
    @endforeach

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
                    <div class="small text-muted">เปิดแล้วขอใช้งบจากกิจกรรมของโครงการได้อีกครั้ง</div>
                @else
                    <div class="small text-muted mb-2">ปิดแล้วขอใช้งบเพิ่มไม่ได้ งบคงเหลือ {{ baht($budget - $spent - $pending) }} บาท</div>
                    <label class="form-label">สรุปผลโครงการ</label><textarea name="summary" rows="4" class="form-control" maxlength="5000" required></textarea>
                @endif
            </div>
            <div class="modal-footer"><button class="btn btn-primary">{{ $p->isClosed() ? 'เปิดโครงการ' : 'ปิดโครงการ' }}</button></div>
        </form></div>
    </div>
@endif
@endsection
