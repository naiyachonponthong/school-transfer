@extends('layouts.app')
@section('title', 'การมาทำงานของครู')

@section('content')
@php
    $came = $records->whereIn('status', ['present', 'late'])->count();
    $late = $records->where('status', 'late')->count();
    $away = $records->whereIn('status', ['leave', 'duty'])->count();
@endphp
<div class="page-head">
    <div><h1>การมาปฏิบัติงานของครูและบุคลากร</h1><div class="sub">{{ \App\Support\Thai::fullDate($date) }}</div></div>
    <form class="actions" method="GET"><input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" data-autosubmit></form>
</div>
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-people"></i></div><div><div class="stat-value">{{ $staff->count() }}</div><div class="stat-label">บุคลากรทั้งหมด</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-success"><i class="bi bi-person-check"></i></div><div><div class="stat-value">{{ $came }}</div><div class="stat-label">มาปฏิบัติงาน</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-alarm"></i></div><div><div class="stat-value">{{ $late }}</div><div class="stat-label">มาสาย</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-info"><i class="bi bi-briefcase"></i></div><div><div class="stat-value">{{ $away }}</div><div class="stat-label">ลา/ไปราชการ · ไม่ลงเวลา {{ $staff->count() - $records->count() }}</div></div></div></div></div>
</div>
<div class="card">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>ชื่อ</th><th>ตำแหน่ง</th><th>เข้า</th><th>ออก</th><th>สถานะ</th></tr></thead>
            <tbody>
            @foreach ($staff as $s)
                @php($r = $records[$s->id] ?? null)
                <tr>
                    <td class="fw-semibold">{{ $s->name }}</td>
                    <td class="small text-muted">{{ $s->position ?: $s->roleLabel() }}</td>
                    <td>{{ $r?->check_in ? substr($r->check_in, 0, 5) : '-' }}@if ($r?->source === 'gate') <i class="bi bi-person-bounding-box text-muted" title="สแกนที่ประตู"></i>@endif</td>
                    <td>{{ $r?->check_out ? substr($r->check_out, 0, 5) : '-' }}</td>
                    <td>
                        @if ($r)
                            <span class="badge bg-{{ \App\Models\StaffAttendance::STATUSES[$r->status][1] ?? 'secondary' }}">{{ \App\Models\StaffAttendance::STATUSES[$r->status][0] ?? $r->status }}</span> <span class="small text-muted">{{ $r->note }}</span>
                        @else
                            <span class="badge bg-light text-muted border">ยังไม่ลงเวลา</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
