@extends('layouts.app')
@section('title', 'ตารางเรียน')

@section('content')
@php
    $palette = ['#eef2ff', '#ecfdf5', '#fff7ed', '#fdf2f8', '#ecfeff', '#f5f3ff', '#fefce8', '#f0fdf4', '#fef2f2', '#f1f5f9'];
    $colorFor = fn ($id) => $palette[$id % count($palette)];
    $nowDay = now()->dayOfWeekIso;
@endphp
<div class="page-head">
    <div>
        <h1>ตารางเรียน {{ $classroom ? 'ห้อง '.$classroom->name() : '' }}</h1>
        <div class="sub">{{ $term?->label() }}</div>
    </div>
    <div class="actions no-print">
        <form method="GET"><select name="classroom" class="form-select" data-autosubmit>
            @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected($classroom?->id === $c->id)>{{ $c->name() }}</option>@endforeach
        </select></form>
        @if (auth()->user()->isAdmin() && $classroom)
            @if ($editing)
                <a href="{{ route('timetable.index', ['classroom' => $classroom->id]) }}" class="btn btn-light border">ยกเลิก</a>
            @else
                <a href="{{ route('timetable.index', ['classroom' => $classroom->id, 'edit' => 1]) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> จัดตาราง</a>
            @endif
        @endif
        <button class="btn btn-light border" onclick="print()"><i class="bi bi-printer"></i></button>
    </div>
</div>

@if (! $term || ! $classroom)
    <div class="card"><div class="empty"><i class="bi bi-calendar-x"></i>ยังไม่มีภาคเรียนหรือห้องเรียน</div></div>
@else
    @if ($editing && $courses->isEmpty())
        <div class="alert alert-warning">ห้องนี้ยังไม่ได้เปิดรายวิชา <a href="{{ route('courses.index') }}" class="alert-link">เปิดรายวิชาก่อน</a> (หรือพิมพ์ข้อความอิสระ เช่น "ลูกเสือ" ได้)</div>
    @endif
    <form method="POST" action="{{ route('timetable.save') }}">
        @csrf
        <input type="hidden" name="classroom_id" value="{{ $classroom->id }}">
        <div class="card">
            <div class="table-responsive">
                <table class="table tt mb-0">
                    <thead>
                        <tr>
                            <th class="day">วัน / คาบ</th>
                            @foreach ($periods as $i => $time)
                                <th>คาบ {{ $i + 1 }}<div class="small text-muted fw-normal">{{ $time }}</div></th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                    @foreach (\App\Models\TimetableSlot::DAYS as $d => $dayName)
                        <tr>
                            <th class="day {{ $d === $nowDay ? 'text-primary' : '' }}">{{ $dayName }}</th>
                            @foreach ($periods as $i => $time)
                                @php($slot = $slots[$d.'-'.($i + 1)] ?? null)
                                <td class="p-1">
                                    @if ($editing)
                                        @php($val = $slot ? ($slot->course_id ? 'c:'.$slot->course_id : $slot->label) : '')
                                        <input class="form-control form-control-sm" name="slots[{{ $d }}][{{ $i + 1 }}]" value="{{ $val && str_starts_with($val, 'c:') ? '' : $val }}" list="courseList" placeholder="-"
                                               data-course-value="{{ $val }}">
                                    @elseif ($slot)
                                        <div class="slot" style="background:{{ $slot->course_id ? $colorFor($slot->course->subject_id) : '#f8fafc' }}">
                                            @if ($slot->course)
                                                <b>{{ $slot->course->subject->name }}</b>
                                                <small>{{ $slot->course->subject->code }}</small>
                                                <small>{{ $slot->course->teacher?->name }}</small>
                                            @else
                                                <b>{{ $slot->label }}</b>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @if ($editing)
            <datalist id="courseList">
                @foreach ($courses as $c)<option value="{{ $c->subject->code }} {{ $c->subject->name }}" data-id="{{ $c->id }}"></option>@endforeach
                <option value="ลูกเสือ/เนตรนารี"></option><option value="ชุมนุม"></option><option value="แนะแนว"></option><option value="โฮมรูม"></option>
            </datalist>
            <div class="d-flex gap-2 mt-3 align-items-center">
                <button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึกตาราง</button>
                <span class="small text-muted">พิมพ์รหัสหรือชื่อวิชาแล้วเลือกจากรายการ · ระบบเตือนถ้าครูสอนชนกับห้องอื่น</span>
            </div>
        @endif
    </form>
@endif
@endsection

@if ($editing ?? false)
@push('scripts')
<script>
// แปลงชื่อวิชาที่เลือกจาก datalist เป็น c:<id> ก่อนส่ง, ค่าอื่นส่งเป็นข้อความอิสระ
document.addEventListener('DOMContentLoaded', () => {
    const map = {}, rev = {};
    document.querySelectorAll('#courseList option[data-id]').forEach(o => { map[o.value] = 'c:' + o.dataset.id; rev['c:' + o.dataset.id] = o.value; });
    const inputs = document.querySelectorAll('[data-course-value]');
    inputs.forEach(i => { const v = i.dataset.courseValue; if (rev[v]) i.value = rev[v]; });
    document.querySelector('form[action$="timetable"]').addEventListener('submit', () => {
        inputs.forEach(i => { if (map[i.value]) i.value = map[i.value]; });
    });
});
</script>
@endpush
@endif
