@extends('layouts.app')
@section('title', 'สรุปการมาเรียน')

@section('content')
@php
    $total = $counts->flatten()->sum();
    $sum = fn ($k) => $counts->sum(fn ($c) => $c[$k] ?? 0);
@endphp
<div class="page-head">
    <div>
        <h1>สรุปการมาเรียนทั้งโรงเรียน</h1>
        <div class="sub">{{ \App\Support\Thai::fullDate($date) }}</div>
    </div>
    <form class="actions" method="GET">
        <input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" data-autosubmit>
        <button type="button" class="btn btn-light border no-print" onclick="print()"><i class="bi bi-printer"></i></button>
    </form>
</div>

<div class="row g-3 mb-3">
    @foreach (\App\Models\Attendance::STATUSES as $k => [$label, , $color])
        <div class="col-6 col-md">
            <div class="card"><div class="stat">
                <div class="stat-icon tint-{{ $color === 'primary' ? 'purple' : $color }}"><i class="bi bi-person"></i></div>
                <div><div class="stat-value">{{ $sum($k) }}</div><div class="stat-label">{{ $label }}</div></div>
            </div></div>
        </div>
    @endforeach
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-door-open"></i> รายห้อง</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>ห้อง</th><th>ครูประจำชั้น</th><th class="text-center">นร.</th><th class="text-center">มา</th><th class="text-center">สาย</th><th class="text-center">ขาด</th><th class="text-center">ลา/ป่วย</th><th class="text-end">%</th></tr></thead>
                    <tbody>
                    @foreach ($classrooms as $c)
                        @php($cc = $counts[$c->id] ?? collect())
                        @php($checked = $cc->sum())
                        @php($p = $checked ? round((($cc['present'] ?? 0) + ($cc['late'] ?? 0)) / $checked * 100) : null)
                        <tr data-href="{{ route('attendance.index', ['classroom' => $c->id, 'date' => $date->toDateString()]) }}" style="cursor:pointer">
                            <td class="fw-semibold">{{ $c->name() }}</td>
                            <td class="small">{{ $c->homeroomTeacher?->name ?? '-' }}</td>
                            <td class="text-center">{{ $c->students_count }}</td>
                            @if ($checked)
                                <td class="text-center text-success">{{ $cc['present'] ?? 0 }}</td>
                                <td class="text-center text-warning">{{ $cc['late'] ?? 0 }}</td>
                                <td class="text-center text-danger fw-semibold">{{ $cc['absent'] ?? 0 }}</td>
                                <td class="text-center text-info">{{ ($cc['leave'] ?? 0) + ($cc['sick'] ?? 0) }}</td>
                                <td class="text-end"><span class="badge bg-{{ $p >= 95 ? 'success' : ($p >= 85 ? 'primary' : 'warning') }}">{{ $p }}%</span></td>
                            @else
                                <td colspan="5" class="text-center"><span class="badge bg-danger-subtle text-danger-emphasis">ยังไม่เช็คชื่อ</span></td>
                            @endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-exclamation text-danger"></i> นักเรียนที่ไม่ได้มาปกติ <span class="badge bg-light text-dark border ms-auto">{{ $absentees->count() }}</span></div>
            @forelse ($absentees as $a)
                <a href="{{ route('students.show', $a->student) }}" class="d-flex align-items-center gap-2 px-3 py-2 border-bottom text-decoration-none text-body">
                    <span class="badge bg-{{ \App\Models\Attendance::color($a->status) }}" style="width:52px">{{ \App\Models\Attendance::label($a->status) }}</span>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $a->student->fullName() }}</div>
                        <div class="small text-muted">{{ $a->student->classroom?->name() }} เลขที่ {{ $a->student->number }} @if($a->note)· {{ $a->note }}@endif</div>
                    </div>
                </a>
            @empty
                <div class="empty"><i class="bi bi-emoji-smile"></i>{{ $total ? 'มาเรียนครบทุกคน' : 'ยังไม่มีข้อมูลเช็คชื่อ' }}</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
