@extends('layouts.app')
@section('title', 'เลื่อนชั้น')

@section('content')
<div class="page-head">
    <div><h1>เลื่อนชั้น {{ $from }} → {{ $to }}</h1><div class="sub">ตรวจสอบก่อนยืนยัน · ข้อมูลเช็คชื่อ คะแนน และสมุดพกของปี {{ $from }} ยังเปิดดูได้ตามเดิม</div></div>
    <div class="actions"><a class="btn btn-light border" href="{{ route('classrooms.index', ['year' => $from]) }}"><i class="bi bi-arrow-left"></i> ห้องเรียน</a></div>
</div>

@if ($promoted)
    <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
        <div class="flex-grow-1">ปี {{ $from }} เลื่อนชั้นไปแล้ว {{ $promoted }} คน{{ $undoBlocker ? ' — '.$undoBlocker : '' }}</div>
        @unless ($undoBlocker)
            <form method="POST" action="{{ route('classrooms.promote.undo') }}" data-confirm="ยกเลิกการเลื่อนชั้น ให้นักเรียนทุกคนกลับห้องเดิมของปี {{ $from }}?">
                @csrf
                <input type="hidden" name="to_year" value="{{ $to }}">
                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-counterclockwise"></i> ยกเลิกการเลื่อนชั้น</button>
            </form>
        @endunless
    </div>
@endif

<form method="POST" action="{{ route('classrooms.promote') }}" data-confirm="ยืนยันเลื่อนชั้นนักเรียนจากปี {{ $from }} ไปปี {{ $to }}?">
    @csrf
    <input type="hidden" name="from_year" value="{{ $from }}">

    <div class="card mb-3"><div class="card-body">
        <label class="form-label">ชั้นที่จบการศึกษา (ไม่เลื่อนต่อ)</label>
        <div class="d-flex flex-wrap gap-3">
            @foreach (['อ.3', 'ป.6', 'ม.3', 'ม.6'] as $l)
                <label class="small"><input type="checkbox" class="form-check-input" name="graduate_levels[]" value="{{ $l }}" @checked(in_array($l, ['ป.6', 'ม.6']))> {{ $l }}</label>
            @endforeach
        </div>
        <div class="small text-muted mt-2">ห้องปีใหม่ใช้เลขห้องเดิม (ป.1/2 → ป.2/2) ย้ายห้องรายคนได้ภายหลังที่หน้าข้อมูลนักเรียน</div>
    </div></div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr><th>ห้องปี {{ $from }}</th><th class="text-center">นักเรียน</th><th>ปี {{ $to }}</th><th>ซ้ำชั้น (ติ๊กคนที่ไม่เลื่อน)</th></tr></thead>
                <tbody>
                @forelse ($classrooms as $c)
                    <tr>
                        <td class="text-nowrap fw-semibold">{{ $c->name() }}</td>
                        <td class="text-center">{{ $c->students->count() }}</td>
                        <td class="text-nowrap">{{ $next[$c->id] ? $next[$c->id].'/'.$c->room : 'ชั้นสูงสุด' }}</td>
                        <td>
                            @if ($c->students->isNotEmpty())
                                <details>
                                    <summary class="small text-primary" style="cursor:pointer">เลือกนักเรียน</summary>
                                    <div class="d-flex flex-wrap gap-3 mt-2">
                                        @foreach ($c->students as $s)
                                            <label class="small text-nowrap"><input type="checkbox" class="form-check-input" name="retain[]" value="{{ $s->id }}"> {{ $s->number }} {{ $s->fullName() }}</label>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="empty"><i class="bi bi-door-closed"></i>ไม่มีห้องเรียนในปี {{ $from }}</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($classrooms->isNotEmpty())
        <div class="text-end mt-3"><button class="btn btn-primary"><i class="bi bi-arrow-up-circle"></i> เลื่อนชั้น</button></div>
    @endif
</form>
@endsection
