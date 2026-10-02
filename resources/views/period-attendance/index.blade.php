@extends('layouts.app')
@section('title', 'เช็คชื่อรายคาบ')

@section('content')
<div class="page-head">
    <div>
        <h1>เช็คชื่อรายคาบ</h1>
        <div class="sub">{{ \App\Support\Thai::fullDate($date) }} · เวลาเรียนต่ำกว่า {{ \App\Models\PeriodAttendance::MIN_PERCENT }}% = มส. (ไม่มีสิทธิ์สอบ)</div>
    </div>
    <div class="actions">
        <a href="{{ route('attendance.index') }}" class="btn btn-light border"><i class="bi bi-check2-square"></i> เช็คชื่อหน้าเสาธง</a>
    </div>
</div>

<form method="GET" class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div>
            <label class="form-label">วันที่</label>
            <div class="input-group">
                <a class="btn btn-light border" href="{{ route('period-attendance.index', ['date' => $date->copy()->subWeekday()->toDateString()]) }}" title="วันก่อนหน้า"><i class="bi bi-chevron-left"></i></a>
                <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" class="form-control" data-autosubmit>
                @if (! $date->isToday())
                    <a class="btn btn-light border" href="{{ route('period-attendance.index', ['date' => min($date->copy()->addWeekday(), today())->toDateString()]) }}" title="วันถัดไป"><i class="bi bi-chevron-right"></i></a>
                @endif
            </div>
        </div>
        @if (! $date->isToday())
            <a href="{{ route('period-attendance.index') }}" class="btn btn-link">กลับไปวันนี้</a>
        @endif
        @if ($slots->isNotEmpty())
            @php($doneCount = $slots->filter(fn ($s) => isset($done[$s->course_id.'-'.$s->period]))->count())
            <div class="ms-auto small text-end">
                <span class="{{ $doneCount === $slots->count() ? 'text-success' : 'text-muted' }}">
                    <i class="bi bi-check-circle{{ $doneCount === $slots->count() ? '-fill' : '' }}"></i> เช็คแล้ว {{ $doneCount }}/{{ $slots->count() }} คาบ
                </span>
            </div>
        @endif
    </div>
</form>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar3"></i> คาบสอน{{ $date->isToday() ? 'วันนี้' : 'วันที่เลือก' }}ตามตารางสอน</div>
            @if ($date->isWeekend())
                <div class="empty"><i class="bi bi-calendar-x"></i>วันที่เลือกเป็นวันหยุดเสาร์-อาทิตย์</div>
            @else
                @forelse ($slots as $slot)
                    @php($key = $slot->course_id.'-'.$slot->period)
                    <a href="{{ route('period-attendance.sheet', ['course' => $slot->course_id, 'period' => $slot->period, 'date' => $date->toDateString()]) }}"
                       class="d-flex align-items-center gap-3 px-3 py-2 border-bottom text-decoration-none text-body list-link">
                        <div class="text-center" style="width:54px">
                            <div class="fw-bold fs-5 lh-1">{{ $slot->period }}</div>
                            <div class="text-muted" style="font-size:.7rem">{{ $times[$slot->period - 1] ?? '' }}</div>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate">{{ $slot->course->subject->name }}</div>
                            <div class="small text-muted">{{ $slot->course->subject->code }} · ห้อง {{ $slot->classroom?->name() }}{{ $slot->room_name ? ' · '.$slot->room_name : '' }}</div>
                        </div>
                        @if (isset($done[$key]))
                            <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check-lg"></i> เช็คแล้ว {{ $done[$key] }} คน</span>
                        @else
                            <span class="btn btn-sm btn-primary">เช็คชื่อ</span>
                        @endif
                    </a>
                @empty
                    <div class="empty"><i class="bi bi-cup-hot"></i>{{ $date->isToday() ? 'วันนี้' : 'วันที่เลือก' }}ไม่มีคาบสอนในตารางสอน<br><span class="small">เลือกวิชาจากช่องด้านขวาเพื่อเช็คชื่อคาบสอนแทน/สอนชดเชยได้</span></div>
                @endforelse
            @endif
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-plus-circle"></i> เช็คคาบอื่น (สอนแทน / ชดเชย)</div>
            <form method="GET" class="card-body" data-sheet-picker>
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                <label class="form-label">รายวิชา</label>
                <select class="form-select mb-2" required data-course>
                    <option value="">- เลือกวิชา -</option>
                    @foreach ($courses as $c)
                        <option value="{{ route('period-attendance.sheet', $c) }}">{{ $c->classroom->name() }} · {{ $c->subject->name }}</option>
                    @endforeach
                </select>
                <label class="form-label">คาบที่</label>
                <select name="period" class="form-select mb-3">
                    @foreach ($times as $i => $t)
                        <option value="{{ $i + 1 }}">คาบ {{ $i + 1 }} ({{ $t }})</option>
                    @endforeach
                </select>
                <button class="btn btn-primary w-100"><i class="bi bi-arrow-right-circle"></i> ไปหน้าเช็คชื่อ</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-graph-down-arrow"></i> สรุปเวลาเรียน / เสี่ยง มส.</div>
            @forelse ($courses as $c)
                <a href="{{ route('period-attendance.report', $c) }}" class="d-flex align-items-center gap-2 px-3 py-2 border-bottom text-decoration-none text-body list-link">
                    <span class="badge bg-dark">{{ $c->classroom->name() }}</span>
                    <span class="flex-grow-1 text-truncate small">{{ $c->subject->name }}</span>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
            @empty
                <div class="empty"><i class="bi bi-journal-x"></i>ยังไม่มีรายวิชาที่คุณสอน</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // ฟอร์มเลือกวิชา: action ของฟอร์มคือ URL ของวิชาที่เลือก
    document.querySelector('[data-sheet-picker]')?.addEventListener('submit', (e) => {
        const url = e.target.querySelector('[data-course]').value;
        if (url) e.target.action = url;
    });
</script>
@endpush
