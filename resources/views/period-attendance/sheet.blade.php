@extends('layouts.app')
@section('title', 'เช็คชื่อ '.$course->label())

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $course->label() }} · {{ $course->classroom->name() }}</h1>
        <div class="sub">คาบที่ {{ $period }}{{ $time ? ' ('.$time.')' : '' }} · {{ \App\Support\Thai::fullDate($date) }}</div>
    </div>
    <div class="actions">
        <a href="{{ route('period-attendance.index', ['date' => $date->toDateString()]) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
        <a href="{{ route('period-attendance.report', $course) }}" class="btn btn-light border"><i class="bi bi-graph-down-arrow"></i> สรุปเวลาเรียน</a>
    </div>
</div>

@if ($records->isEmpty() && $daily->isNotEmpty())
    <div class="alert alert-info small"><i class="bi bi-magic"></i> ดึงผลเช็คชื่อหน้าเสาธงมาให้แล้ว (ขาด/ลา/ป่วยทั้งวัน) — กด "คนที่เหลือ = มา" แล้วบันทึกได้เลย</div>
@endif

<form method="POST" action="{{ route('period-attendance.save', $course) }}" id="attendanceForm">
    @csrf
    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
    <input type="hidden" name="period" value="{{ $period }}">

    <div class="att-toolbar">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-success" data-mark-all="present" data-only-empty><i class="bi bi-check2-all"></i> คนที่เหลือ = มา</button>
            <div class="att-summary ms-lg-2">
                <span class="pill bg-success-subtle text-success-emphasis">มา <span data-count="present">0</span></span>
                <span class="pill bg-warning-subtle text-warning-emphasis">สาย <span data-count="late">0</span></span>
                <span class="pill bg-danger-subtle text-danger-emphasis">ขาด <span data-count="absent">0</span></span>
                <span class="pill bg-info-subtle text-info-emphasis">ลา <span data-count="leave">0</span></span>
                <span class="pill bg-purple-subtle text-purple-emphasis">ป่วย <span data-count="sick">0</span></span>
                <span class="pill bg-light text-muted border">ยังไม่เช็ค <span data-count="none">0</span></span>
            </div>
            <div class="ms-auto small text-muted d-none d-lg-block">
                <i class="bi bi-keyboard"></i> คีย์ลัด: <kbd>1</kbd> มา <kbd>2</kbd> สาย <kbd>3</kbd> ขาด <kbd>4</kbd> ลา <kbd>5</kbd> ป่วย
            </div>
        </div>
    </div>

    <div class="att-list">
        @foreach ($students as $s)
            @php
                $rec = $records[$s->id] ?? null;
                $day = $daily[$s->id] ?? null;
                // ค่าตั้งต้น: ที่บันทึกไว้แล้ว > ขาด/ลา/ป่วยทั้งวันจากเช็คชื่อหน้าเสาธง
                $value = $rec?->status ?? (in_array($day?->status, ['absent', 'leave', 'sick']) ? $day->status : null);
                $sum = $summary[$s->id] ?? null;
            @endphp
            <div class="att-row">
                <div class="num">{{ $s->number ?? '-' }}</div>
                <span class="sb-avatar sm">@if($s->photoUrl())<img src="{{ $s->photoUrl() }}" alt="">@else{{ $s->initials() }}@endif</span>
                <div class="who">
                    <div class="name">{{ $s->fullName() }} @if($s->nickname)<span class="text-muted fw-normal">({{ $s->nickname }})</span>@endif</div>
                    <div class="meta">
                        {{ $s->student_code }}
                        @if ($day)
                            · หน้าเสาธง: <span class="text-{{ in_array($day->status, ['present', 'late']) ? 'success' : 'danger' }}">{{ \App\Models\Attendance::STATUSES[$day->status][0] ?? $day->status }}</span>
                        @endif
                        @if ($sum)
                            · เวลาเรียน <span class="{{ $sum['ms'] ? 'text-danger fw-semibold' : '' }}">{{ $sum['percent'] }}%</span>
                            @if ($sum['ms'])<span class="badge bg-danger">มส.</span>@endif
                        @endif
                    </div>
                </div>
                <div class="att-choices">
                    @foreach (\App\Models\Attendance::STATUSES as $key => [$label])
                        <input type="radio" name="status[{{ $s->id }}]" value="{{ $key }}" id="st{{ $s->id }}{{ $key }}" @checked($value === $key)>
                        <label for="st{{ $s->id }}{{ $key }}" class="c-{{ $key }}">{{ $label }}</label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="att-save-bar">
        <div class="d-flex align-items-center gap-3 bg-white border rounded-4 p-2 ps-3 shadow-sm">
            <div class="small">
                <span class="fw-semibold">เข้าเรียน <span data-count="pct">-</span></span>
                <span id="unsaved" class="text-warning ms-2 d-none"><i class="bi bi-dot"></i>ยังไม่บันทึก</span>
            </div>
            <button class="btn btn-primary btn-lg ms-auto px-4"><i class="bi bi-save"></i> บันทึกคาบนี้</button>
        </div>
    </div>
</form>
@endsection
