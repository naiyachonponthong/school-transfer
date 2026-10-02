@extends('layouts.app')
@section('title', 'รายงานเวลาเรียน')

@section('content')
<div class="page-head">
    <div>
        <h1>รายงานเวลาเรียนประจำเดือน</h1>
        <div class="sub">{{ $classroom ? 'ห้อง '.$classroom->name().' · ' : '' }}{{ \App\Support\Thai::monthYear($month->month, $month->year) }}</div>
    </div>
    <div class="actions no-print">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn btn-light border"><i class="bi bi-file-earmark-spreadsheet"></i> ส่งออก Excel</a>
        <button class="btn btn-light border" onclick="print()"><i class="bi bi-printer"></i> พิมพ์</button>
    </div>
</div>

<form class="card mb-3 no-print" method="GET">
    <div class="card-body d-flex flex-wrap gap-2">
        <select name="classroom" class="form-select w-auto" data-autosubmit>
            @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected($classroom?->id === $c->id)>{{ $c->name() }}</option>@endforeach
        </select>
        <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control w-auto" data-autosubmit>
    </div>
</form>

<div class="print-only text-center mb-2">
    <h5 class="mb-0">{{ school('school_name') }}</h5>
    <div>รายงานเวลาเรียน ห้อง {{ $classroom?->name() }} เดือน{{ \App\Support\Thai::monthYear($month->month, $month->year) }}</div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-bordered att-grid mb-0">
            <thead>
                <tr>
                    <th class="name">เลขที่ / ชื่อ</th>
                    @foreach ($days as $d)<th class="{{ $d->isToday() ? 'text-primary' : '' }}">{{ $d->day }}<div class="fw-normal" style="font-size:.65rem">{{ \App\Support\Thai::DAYS_SHORT[$d->dayOfWeek] }}</div></th>@endforeach
                    @foreach (\App\Models\Attendance::STATUSES as $k => [$label])<th>{{ $label }}</th>@endforeach
                    <th>%</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($students as $s)
                @php($mine = $grid[$s->id] ?? collect())
                @php($cnt = $mine->countBy('status'))
                @php($den = $mine->count())
                <tr>
                    <td class="name"><span class="text-muted me-1">{{ $s->number }}</span> <a href="{{ route('students.show', $s) }}" class="text-body">{{ $s->fullName() }}</a></td>
                    @foreach ($days as $d)
                        @php($r = $mine[$d->toDateString()] ?? null)
                        <td>@if($r)<span class="att-cell s-{{ $r->status }}" title="{{ \App\Models\Attendance::label($r->status) }}{{ $r->note ? ': '.$r->note : '' }}">{{ \App\Models\Attendance::short($r->status) }}</span>@endif</td>
                    @endforeach
                    @foreach (array_keys(\App\Models\Attendance::STATUSES) as $k)<td class="{{ $k === 'absent' && ($cnt[$k] ?? 0) >= 3 ? 'text-danger fw-bold' : '' }}">{{ $cnt[$k] ?? 0 }}</td>@endforeach
                    <td class="fw-semibold">{{ $den ? round((($cnt['present'] ?? 0) + ($cnt['late'] ?? 0)) / $den * 100) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $days->count() + 7 }}" class="empty">ไม่มีนักเรียน</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="small text-muted mt-2">
    @foreach (\App\Models\Attendance::STATUSES as $k => [$label, $short])<span class="me-3"><span class="att-cell s-{{ $k }}">{{ $short }}</span> {{ $label }}</span>@endforeach
</div>
@endsection
