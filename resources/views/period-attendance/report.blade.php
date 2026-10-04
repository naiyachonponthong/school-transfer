@extends('layouts.app')
@section('title', 'สรุปเวลาเรียน '.$course->subject->name)

@section('content')
@php
    $min = \App\Models\PeriodAttendance::MIN_PERCENT;
    $withData = collect($summary);
    $msCount = $withData->where('ms', true)->count();
    $avg = $withData->whereNotNull('percent')->avg('percent');
@endphp
<div class="page-head">
    <div>
        <h1>สรุปเวลาเรียน · {{ $course->label() }}</h1>
        <div class="sub">{{ $course->subject->code }} · ห้อง {{ $course->classroom->name() }} · {{ $course->term?->label() }} · ครู{{ $course->teacher?->name ?? '-' }}</div>
    </div>
    <div class="actions no-print">
        <a href="{{ route('period-attendance.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> เช็คชื่อรายคาบ</a>
        <button class="btn btn-light border" onclick="print()"><i class="bi bi-printer"></i> พิมพ์</button>
    </div>
</div>

<div class="print-only text-center mb-2">
    <h5 class="mb-0">{{ school('school_name') }}</h5>
    <div>สรุปเวลาเรียนรายวิชา {{ $course->subject->code }} {{ $course->subject->name }} ห้อง {{ $course->classroom->name() }}</div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-4"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-clock-history"></i></div><div><div class="stat-value">{{ $sessions }}</div><div class="stat-label">คาบที่สอนไปแล้ว</div></div></div></div></div>
    <div class="col-6 col-md-4"><div class="card"><div class="stat"><div class="stat-icon tint-success"><i class="bi bi-person-check"></i></div><div><div class="stat-value">{{ $avg !== null ? round($avg, 1).'%' : '-' }}</div><div class="stat-label">เข้าเรียนเฉลี่ย</div></div></div></div></div>
    <div class="col-12 col-md-4"><div class="card"><div class="stat"><div class="stat-icon tint-danger"><i class="bi bi-exclamation-triangle"></i></div><div><div class="stat-value {{ $msCount ? 'text-danger' : '' }}">{{ $msCount }}</div><div class="stat-label">คน ต่ำกว่า {{ $min }}% (มส.)</div></div></div></div></div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:60px">เลขที่</th>
                    <th>ชื่อ-สกุล</th>
                    <th class="text-center d-none d-md-table-cell">คาบ</th>
                    <th class="text-center">มา</th>
                    <th class="text-center d-none d-md-table-cell">สาย</th>
                    <th class="text-center">ขาด</th>
                    <th class="text-center d-none d-md-table-cell">ลา/ป่วย</th>
                    <th style="min-width:150px">เวลาเรียน</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($students as $s)
                @php($r = $summary[$s->id] ?? null)
                <tr class="{{ ($r['ms'] ?? false) ? 'table-danger' : '' }}">
                    <td class="text-muted">{{ $s->number ?? '-' }}</td>
                    <td style="min-width:150px"><a href="{{ route('students.show', $s) }}" class="text-body">{{ $s->fullName() }}</a></td>
                    <td class="text-center d-none d-md-table-cell">{{ $r['total'] ?? 0 }}</td>
                    <td class="text-center">{{ $r['came'] ?? 0 }}</td>
                    <td class="text-center d-none d-md-table-cell">{{ $r['late'] ?? 0 }}</td>
                    <td class="text-center {{ ($r['absent'] ?? 0) >= 3 ? 'text-danger fw-bold' : '' }}">{{ $r['absent'] ?? 0 }}</td>
                    <td class="text-center d-none d-md-table-cell">{{ $r['leave'] ?? 0 }}</td>
                    <td>
                        @if ($r && $r['percent'] !== null)
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height:8px">
                                    <div class="progress-bar bg-{{ $r['ms'] ? 'danger' : ($r['percent'] < 90 ? 'warning' : 'success') }}" style="width:{{ $r['percent'] }}%"></div>
                                </div>
                                <span class="small fw-semibold" style="width:48px">{{ $r['percent'] }}%</span>
                                @if ($r['ms'])<span class="badge bg-danger">มส.</span>@endif
                            </div>
                        @else
                            <span class="text-muted small">ยังไม่มีข้อมูล</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">ไม่มีนักเรียน</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="small text-muted mt-2">
    <i class="bi bi-info-circle"></i> ร้อยละเวลาเรียน = (มา + สาย) ÷ คาบที่เช็คชื่อ · ลา/ป่วยไม่นับเป็นเวลาเรียน · ต่ำกว่า {{ $min }}% ไม่มีสิทธิ์สอบปลายภาค (มส.)
</div>
@endsection
