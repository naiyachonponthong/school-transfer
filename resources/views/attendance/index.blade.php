@extends('layouts.app')
@section('title', 'เช็คชื่อนักเรียน')

@section('content')
<div class="page-head">
    <div>
        <h1>เช็คชื่อนักเรียน</h1>
        <div class="sub">{{ \App\Support\Thai::fullDate($date) }}</div>
    </div>
    <div class="actions">
        <a href="{{ route('attendance.report', ['classroom' => $classroom?->id]) }}" class="btn btn-light border"><i class="bi bi-table"></i> สรุปรายเดือน</a>
    </div>
</div>

<form method="GET" class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div style="min-width:180px">
            <label class="form-label">ห้องเรียน</label>
            <select name="classroom" class="form-select" data-autosubmit>
                @foreach ($classrooms as $c)
                    <option value="{{ $c->id }}" @selected($classroom?->id === $c->id)>{{ $c->name() }}{{ $c->isManagedBy(auth()->user()) && ! auth()->user()->isAdmin() ? ' ★ ห้องของฉัน' : '' }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="form-label">วันที่</label>
            <div class="input-group">
                <a class="btn btn-light border" href="{{ route('attendance.index', ['classroom' => $classroom?->id, 'date' => $date->copy()->subWeekday()->toDateString()]) }}" title="วันก่อนหน้า"><i class="bi bi-chevron-left"></i></a>
                <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control" data-autosubmit>
                @if (! $date->isToday())
                    <a class="btn btn-light border" href="{{ route('attendance.index', ['classroom' => $classroom?->id, 'date' => min($date->copy()->addWeekday(), today())->toDateString()]) }}" title="วันถัดไป"><i class="bi bi-chevron-right"></i></a>
                @endif
            </div>
        </div>
        @if (! $date->isToday())
            <a href="{{ route('attendance.index', ['classroom' => $classroom?->id]) }}" class="btn btn-link">กลับไปวันนี้</a>
        @endif
        @if ($classroom)
            <div class="ms-auto small text-muted text-end">
                ครูประจำชั้น: {{ $classroom->homeroomTeacher?->name ?? '-' }}<br>
                @if ($records->isNotEmpty())
                    <span class="text-success"><i class="bi bi-check-circle-fill"></i> เช็คแล้ว {{ $records->count() }}/{{ $students->count() }} คน</span>
                @else
                    <span class="text-danger"><i class="bi bi-circle"></i> ยังไม่ได้เช็คชื่อ</span>
                @endif
            </div>
        @endif
    </div>
</form>

@if (! $classroom)
    <div class="card"><div class="empty"><i class="bi bi-door-closed"></i>ยังไม่มีห้องเรียนในปีการศึกษานี้</div></div>
@elseif ($students->isEmpty())
    <div class="card"><div class="empty"><i class="bi bi-people"></i>ห้องนี้ยังไม่มีนักเรียน <a href="{{ route('students.create', ['classroom' => $classroom->id]) }}">เพิ่มนักเรียน</a></div></div>
@elseif ($date->isWeekend())
    <div class="alert alert-secondary"><i class="bi bi-calendar-x"></i> วันที่เลือกเป็นวันหยุดเสาร์-อาทิตย์</div>
@else
<form method="POST" action="{{ route('attendance.store') }}" id="attendanceForm">
    @csrf
    <input type="hidden" name="classroom_id" value="{{ $classroom->id }}">
    <input type="hidden" name="date" value="{{ $date->toDateString() }}">

    <div class="att-toolbar">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <button type="button" class="btn btn-success" data-mark-all="present" data-only-empty><i class="bi bi-check2-all"></i> คนที่เหลือ = มา</button>
            <button type="button" class="btn btn-outline-success" data-mark-all="present">ทุกคนมา</button>
            <div class="att-summary ms-lg-2">
                <span class="pill bg-success-subtle text-success-emphasis">มา <span data-count="present">0</span></span>
                <span class="pill bg-warning-subtle text-warning-emphasis">สาย <span data-count="late">0</span></span>
                <span class="pill bg-danger-subtle text-danger-emphasis">ขาด <span data-count="absent">0</span></span>
                <span class="pill bg-info-subtle text-info-emphasis">ลา <span data-count="leave">0</span></span>
                <span class="pill bg-purple-subtle text-purple-emphasis">ป่วย <span data-count="sick">0</span></span>
                <span class="pill bg-light text-muted border">ยังไม่เช็ค <span data-count="none">0</span></span>
            </div>
            <div class="ms-auto small text-muted d-none d-lg-block">
                <i class="bi bi-keyboard"></i> คีย์ลัด: <kbd>1</kbd> มา <kbd>2</kbd> สาย <kbd>3</kbd> ขาด <kbd>4</kbd> ลา <kbd>5</kbd> ป่วย · <kbd>↑</kbd><kbd>↓</kbd> เลื่อน
            </div>
        </div>
    </div>

    <div class="att-list">
        @foreach ($students as $s)
            @php($rec = $records[$s->id] ?? null)
            <div class="att-row">
                <div class="num">{{ $s->number ?? '-' }}</div>
                <span class="sb-avatar sm">@if($s->photoUrl())<img src="{{ $s->photoUrl() }}" alt="">@else{{ $s->initials() }}@endif</span>
                <div class="who">
                    <div class="name">{{ $s->fullName() }} @if($s->nickname)<span class="text-muted fw-normal">({{ $s->nickname }})</span>@endif</div>
                    <div class="meta">
                        {{ $s->student_code }}
                        @if ($pl = $pendingLeaves[$s->id] ?? null)
                            · <a href="{{ route('leaves.index') }}" class="badge bg-info-subtle text-info-emphasis text-decoration-none" title="{{ $pl->reason }}"><i class="bi bi-envelope-paper"></i> ส่งใบ{{ $pl->typeLabel() }}แล้ว รออนุมัติ</a>
                        @endif
                        @if ($rec?->note)· <span class="text-info"><i class="bi bi-chat-left-text"></i> {{ $rec->note }}</span>@endif
                        @if ($rec?->checked_at)· {{ substr($rec->checked_at, 0, 5) }} น.@endif
                    </div>
                </div>
                <div class="att-choices">
                    @foreach (\App\Models\Attendance::STATUSES as $key => [$label])
                        <input type="radio" name="status[{{ $s->id }}]" value="{{ $key }}" id="st{{ $s->id }}{{ $key }}" @checked($rec?->status === $key)>
                        <label for="st{{ $s->id }}{{ $key }}" class="c-{{ $key }}">{{ $label }}</label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <div class="att-save-bar">
        <div class="d-flex align-items-center gap-3 bg-white border rounded-4 p-2 ps-3 shadow-sm">
            <div class="small">
                <span class="fw-semibold">มาเรียน <span data-count="pct">-</span></span>
                <span id="unsaved" class="text-warning ms-2 d-none"><i class="bi bi-dot"></i>ยังไม่บันทึก</span>
            </div>
            <button class="btn btn-primary btn-lg ms-auto px-4"><i class="bi bi-save"></i> บันทึกการเช็คชื่อ</button>
        </div>
    </div>
</form>
@endif
@endsection
