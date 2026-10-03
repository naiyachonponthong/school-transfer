@extends('layouts.app')
@section('title', 'เยี่ยมบ้าน')

@section('content')
<div class="page-head">
    <div><h1>เยี่ยมบ้านนักเรียน</h1><div class="sub">{{ $term?->label() ?? 'ยังไม่ได้ตั้งภาคเรียน' }} · เยี่ยมแล้ว {{ $visits->count() }} จาก {{ $students->count() }} คน</div></div>
    <div class="actions">
        <form method="GET"><select name="classroom" class="form-select" data-autosubmit aria-label="ห้องเรียน">
            @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected($classroom?->id === $c->id)>{{ $c->name() }}</option>@endforeach
        </select></form>
        <a href="{{ route('care.index', ['classroom' => $classroom?->id]) }}" class="btn btn-light border"><i class="bi bi-clipboard-heart"></i> ดูแลช่วยเหลือ</a>
        <button onclick="print()" class="btn btn-light border"><i class="bi bi-printer"></i> พิมพ์สรุป</button>
    </div>
</div>

@if ($riskCounts->isNotEmpty())
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach ($riskCounts as $key => $n)<span class="badge bg-danger-subtle text-danger fw-normal fs-6">{{ \App\Models\HomeVisit::RISKS[$key] ?? $key }} {{ $n }} คน</span>@endforeach
    </div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead><tr><th>นักเรียน</th><th>วันที่เยี่ยม</th><th>ผู้ปกครองที่พบ</th><th>ที่อยู่อาศัย</th><th>ความเสี่ยงที่พบ</th><th class="no-print"></th></tr></thead>
            <tbody>
            @forelse ($students as $s)
                @php($v = $visits[$s->id] ?? null)
                <tr>
                    <td class="text-nowrap"><span class="text-muted me-1">{{ $s->number }}</span> {{ $s->fullName() }}</td>
                    <td class="small">{{ $v ? thai_date($v->visited_on) : '-' }}</td>
                    <td class="small">{{ $v?->guardian_met ?: '-' }}</td>
                    <td class="small">{{ $v ? (\App\Models\HomeVisit::HOUSING[$v->housing] ?? '-') : '-' }}</td>
                    <td class="small {{ $v && $v->riskLabels() ? 'text-danger' : 'text-muted' }}">{{ $v ? (implode(', ', $v->riskLabels()) ?: 'ไม่พบ') : '' }}</td>
                    <td class="text-end no-print"><a href="{{ route('care.visits.form', $s) }}" class="btn btn-sm {{ $v ? 'btn-light border' : 'btn-primary' }}">{{ $v ? 'แก้ไข' : 'บันทึก' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><i class="bi bi-people"></i>{{ $classroom ? 'ห้องนี้ยังไม่มีนักเรียน' : 'คุณยังไม่ได้เป็นครูประจำชั้นของห้องใด' }}</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
