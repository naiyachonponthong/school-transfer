@extends('layouts.app')
@section('title', 'สอนแทน')

@section('content')
<div class="page-head">
    <div><h1>สอนแทน</h1><div class="sub">{{ \App\Support\Thai::fullDate($date) }} · คาบของครูที่มีใบลาอนุมัติแล้วในวันนี้ {{ $needs->count() }} คาบ · จัดแล้ว {{ $needs->filter(fn ($s) => isset($assigned[$s->id]))->count() }}</div></div>
    <div class="actions">
        <form method="GET"><input type="date" name="date" value="{{ $date->toDateString() }}" class="form-control" data-autosubmit aria-label="วันที่"></form>
    </div>
</div>

@error('substitute_id')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>คาบ</th><th>ห้อง</th><th>รายวิชา</th><th>ครูที่ลา</th><th style="min-width:320px">ครูสอนแทน</th></tr></thead>
            <tbody>
            @forelse ($needs as $slot)
                @php($current = $assigned[$slot->id] ?? null)
                <tr>
                    <td class="text-nowrap">คาบ {{ $slot->period }}<div class="small text-muted">{{ $periods[$slot->period - 1] ?? '' }}</div></td>
                    <td>{{ $slot->classroom->name() }}</td>
                    <td>{{ $slot->course->subject->name }}</td>
                    <td>{{ $slot->course->teacher?->name }}</td>
                    <td>
                        <form method="POST" action="{{ route('substitutions.store') }}" class="d-flex gap-2">
                            @csrf
                            <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                            <input type="hidden" name="timetable_slot_id" value="{{ $slot->id }}">
                            <select name="substitute_id" class="form-select form-select-sm" aria-label="ครูสอนแทน คาบ {{ $slot->period }} ห้อง {{ $slot->classroom->name() }}">
                                <option value="">— ยังไม่จัด —</option>
                                @foreach ($free($slot) as $t)<option value="{{ $t->id }}" @selected($current?->substitute_id === $t->id)>{{ $t->name }}</option>@endforeach
                            </select>
                            <button class="btn btn-sm btn-primary text-nowrap">บันทึก</button>
                        </form>
                        @if ($current)<div class="small text-success mt-1"><i class="bi bi-check-circle"></i> {{ $current->substitute?->name }}</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty"><i class="bi bi-calendar-check"></i>วันนี้ไม่มีคาบที่ต้องจัดสอนแทน</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent small text-muted">รายชื่อในช่องเลือกคือครูที่ไม่ได้ลาและไม่มีสอนในคาบนั้น · ครูสอนแทนเช็คชื่อรายคาบของวิชานั้นได้ในวันดังกล่าว และได้รับแจ้งทาง LINE</div>
</div>

@if ($monthCounts->isNotEmpty())
<div class="card">
    <div class="card-header"><i class="bi bi-bar-chart"></i> จำนวนคาบสอนแทนเดือนนี้</div>
    @foreach ($monthCounts as $row)
        <div class="d-flex px-3 py-2 border-bottom small"><span class="flex-grow-1">{{ $row['name'] }}</span><b>{{ $row['count'] }} คาบ</b></div>
    @endforeach
</div>
@endif
@endsection
