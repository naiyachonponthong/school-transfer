@extends('layouts.app')
@section('title', 'โครงการ')

@section('content')
<div class="page-head">
    <div><h1>โครงการ</h1><div class="sub">ปีงบประมาณ {{ $year }} · {{ $projects->count() }} โครงการ · งบรวม {{ baht($projects->sum('budget'), 0) }} บาท{{ $canManage ? '' : ' · แสดงโครงการที่คุณรับผิดชอบ' }}</div></div>
    <div class="actions">
        <form method="GET"><select name="year" class="form-select" data-autosubmit aria-label="ปีงบประมาณ">
            @foreach ($years as $y)<option value="{{ $y }}" @selected($y === $year)>ปีงบประมาณ {{ $y }}</option>@endforeach
        </select></form>
        @if ($canManage)<a href="{{ route('budget.index', ['year' => $year]) }}" class="btn btn-light border"><i class="bi bi-bank"></i> งบประมาณ</a>@endif
        <a href="{{ route('budget-requests.index') }}" class="btn btn-light border"><i class="bi bi-cart-check"></i> คำขอใช้งบ</a>
        @if ($canManage)<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProject"><i class="bi bi-plus-lg"></i> ตั้งโครงการ</button>@endif
    </div>
</div>

<div class="card">
    <div class="table-responsive"><table class="table align-middle mb-0">
        <thead><tr><th>โครงการ</th><th>ผู้รับผิดชอบ</th><th class="text-end">งบ</th><th style="width:22%">การใช้งบ</th><th class="text-end">ตัดงบแล้ว</th><th class="text-end">คงเหลือ</th><th>สถานะ</th></tr></thead>
        <tbody>
        @forelse ($projects as $row)
            @php
                $p = $row['project'];
                $used = $row['spent'] + $row['pending'];
                $pct = $row['budget'] > 0 ? min(100, $used / $row['budget'] * 100) : 0;
            @endphp
            <tr>
                <td><a href="{{ route('projects.show', $p) }}" class="fw-semibold">{{ $p->name }}</a><div class="small text-muted">{{ $p->code }}{{ $p->department ? ' · '.$p->department->name : '' }}</div></td>
                <td class="small">{{ $p->owner?->name ?? '-' }}</td>
                <td class="text-end">{{ baht($row['budget']) }}</td>
                <td title="ตัดงบแล้ว {{ baht($row['spent']) }} · รอพิจารณา {{ baht($row['pending']) }} จากงบ {{ baht($row['budget']) }}">
                    <div class="progress" style="height:10px" role="progressbar" aria-label="การใช้งบของ {{ $p->name }}" aria-valuenow="{{ round($pct) }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:{{ $pct }}%"></div></div>
                    <div class="small text-muted">{{ round($pct) }}%{{ $row['pending'] > 0 ? ' · รอพิจารณา '.baht($row['pending'], 0) : '' }}</div>
                </td>
                <td class="text-end">{{ baht($row['spent']) }}</td>
                <td class="text-end fw-semibold">{{ baht($row['budget'] - $used) }}</td>
                <td><span class="badge bg-{{ \App\Models\Project::STATUSES[$p->status][1] }}">{{ \App\Models\Project::STATUSES[$p->status][0] }}</span></td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty py-4"><i class="bi bi-kanban"></i>{{ $canManage ? 'ยังไม่มีโครงการของปีงบประมาณนี้ กด "ตั้งโครงการ" เพื่อเริ่ม' : 'ยังไม่มีโครงการที่คุณรับผิดชอบในปีงบประมาณนี้' }}</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

@if ($canManage)
    <div class="modal fade" id="addProject" tabindex="-1">
        <div class="modal-dialog modal-lg"><form method="POST" action="{{ route('projects.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">ตั้งโครงการ</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body row g-3">@include('budget._project-fields', ['p' => null])<div class="col-12 small text-muted">ระบบออกรหัสโครงการให้เอง ตั้งแล้วเพิ่มกิจกรรมและงบแยกประเภทเงินได้ในหน้าโครงการ</div></div>
            <div class="modal-footer"><button class="btn btn-primary">ตั้งโครงการ</button></div>
        </form></div>
    </div>
@endif
@endsection
