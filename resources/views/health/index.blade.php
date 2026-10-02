@extends('layouts.app')
@section('title', 'ห้องพยาบาล')

@section('content')
<div class="page-head">
    <div><h1>ห้องพยาบาล</h1><div class="sub">บันทึกแล้วแจ้งผู้ปกครองทาง LINE ทันที</div></div>
    <div class="actions"><a href="{{ route('health.measure') }}" class="btn btn-light border"><i class="bi bi-rulers"></i> ชั่งน้ำหนัก / วัดส่วนสูง</a></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-heart-pulse"></i></div><div><div class="stat-value">{{ $todayCount }}</div><div class="stat-label">มาวันนี้</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-house"></i></div><div><div class="stat-value">{{ $sentHome }}</div><div class="stat-label">กลับบ้านวันนี้</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-info"><i class="bi bi-calendar-month"></i></div><div><div class="stat-value">{{ $monthCount }}</div><div class="stat-label">เดือนนี้</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="card-body py-2 small">
        <div class="fw-semibold mb-1">อาการที่พบบ่อย (30 วัน)</div>
        @forelse ($topSymptoms as $sym => $c)<div class="d-flex justify-content-between"><span>{{ $sym }}</span><b>{{ $c }}</b></div>@empty<span class="text-muted">-</span>@endforelse
    </div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <form method="POST" action="{{ route('health.store') }}" class="card">
            @csrf
            <div class="card-header"><i class="bi bi-plus-circle text-primary"></i> บันทึกการมารับบริการ</div>
            <div class="card-body">
                <label class="form-label">นักเรียน (พิมพ์ชื่อ/รหัส หรือสแกนบัตร)</label>
                <input name="student_code" value="{{ old('student_code') }}" class="form-control mb-3" list="hStudents" required autofocus>
                <datalist id="hStudents">
                    @foreach ($students as $s)<option value="{{ $s->student_code }} {{ $s->fullName() }}{{ $s->nickname ? ' ('.$s->nickname.')' : '' }} {{ $s->classroom?->name() }}"></option>@endforeach
                </datalist>
                <label class="form-label">อาการ</label>
                <input name="symptom" value="{{ old('symptom') }}" class="form-control mb-2" required>
                <div class="d-flex flex-wrap gap-1 mb-3">
                    @foreach (\App\Models\HealthVisit::SYMPTOMS as $sym)
                        <button type="button" class="btn btn-sm btn-light border" onclick="this.form.symptom.value='{{ $sym }}'">{{ $sym }}</button>
                    @endforeach
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-4"><label class="form-label">อุณหภูมิ °C</label><input name="temperature" type="number" step="0.1" class="form-control" placeholder="36.5"></div>
                    <div class="col-8"><label class="form-label">ยาที่ให้</label><input name="medicine" class="form-control" placeholder="เช่น พาราเซตามอล 1 เม็ด"></div>
                </div>
                <label class="form-label">การดูแล</label>
                <input name="treatment" class="form-control mb-3" placeholder="เช่น ทำแผล นอนพัก 30 นาที">
                <label class="form-label">ผล</label>
                <div class="d-grid gap-2" style="grid-template-columns:1fr 1fr">
                    @foreach (\App\Models\HealthVisit::ACTIONS as $k => [$label, $color])
                        <input type="radio" class="btn-check" name="action" value="{{ $k }}" id="ha{{ $k }}" @checked($loop->first)>
                        <label class="btn btn-outline-{{ $color }}" for="ha{{ $k }}">{{ $label }}</label>
                    @endforeach
                </div>
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-primary w-100 btn-lg"><i class="bi bi-save"></i> บันทึกและแจ้งผู้ปกครอง</button></div>
        </form>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> ประวัติ
                <form class="ms-auto" method="GET"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="ค้นหานักเรียน"></form></div>
            @forelse ($visits as $v)
                <div class="d-flex gap-2 px-3 py-2 border-bottom align-items-start">
                    <span class="sb-avatar sm">{{ $v->student->initials() }}</span>
                    <div class="flex-grow-1 small">
                        <div><a href="{{ route('students.show', $v->student) }}" class="fw-semibold text-body">{{ $v->student->fullName() }}</a> <span class="text-muted">{{ $v->student->classroom?->name() }}</span></div>
                        <div><b>{{ $v->symptom }}</b>@if($v->temperature) · {{ $v->temperature }}°C @endif @if($v->medicine) · ยา: {{ $v->medicine }}@endif @if($v->treatment) · {{ $v->treatment }}@endif</div>
                        <div class="text-muted">{{ thai_datetime($v->visited_at) }} · {{ $v->recorder?->name }}</div>
                        @if ($v->student->medical_note)<div class="text-danger"><i class="bi bi-exclamation-triangle"></i> {{ $v->student->medical_note }}</div>@endif
                    </div>
                    <form method="POST" action="{{ route('health.update', $v) }}">@csrf @method('PUT')
                        <select name="action" class="form-select form-select-sm text-{{ $v->actionColor() }}" data-autosubmit style="width:auto">
                            @foreach (\App\Models\HealthVisit::ACTIONS as $k => [$label])<option value="{{ $k }}" @selected($v->action === $k)>{{ $label }}</option>@endforeach
                        </select>
                    </form>
                </div>
            @empty
                <div class="empty"><i class="bi bi-heart-pulse"></i>ยังไม่มีบันทึก</div>
            @endforelse
        </div>
        <div class="mt-3">{{ $visits->links() }}</div>
    </div>
</div>
@endsection
