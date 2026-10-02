@extends('layouts.public')
@section('title', 'ตรวจสอบและยืนยัน · สมัครเรียน')
@section('heading', 'ใบสมัครเลขที่ '.$a->app_no)
@section('subheading', 'ตรวจสอบข้อมูลก่อนส่ง · ส่งแล้วแก้ไขไม่ได้')

@section('content')
@php
    $S = \App\Support\AdmissionForm::STEPS;
    $answers = collect($a->answers ?? [])->keyBy('id');
    $rows = [
        'student' => [['ระดับชั้น', $a->level], ['ชื่อ-สกุล', $a->fullName()], ['ชื่อเล่น', $a->nickname], ['เพศ', ['M' => 'ชาย', 'F' => 'หญิง'][$a->gender] ?? null],
            ['วันเกิด', thai_date($a->birthdate, true)], ['เลขประจำตัวประชาชน', $a->citizen_id]],
        'education' => [['โรงเรียนเดิม', $a->previous_school], ['เกรดเฉลี่ย', $a->gpa ? number_format($a->gpa, 2) : null]],
        'family' => [['ผู้ปกครอง', $a->parent_name], ['ความสัมพันธ์', $a->relation], ['เบอร์โทร', $a->parent_phone], ['ที่อยู่', $a->address]],
        'extra' => [['ข้อมูลเพิ่มเติม', $a->note]],
        'documents' => [],
    ];
@endphp
@include('admissions.apply._progress', ['current' => 'review'])

@if ($missing)
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> ขั้น "<b>{{ $S[$missing['step']][0] }}</b>" ยังกรอกไม่ครบ:
        {{ collect($missing['errors'])->flatten()->take(3)->implode(' · ') }}
        <a href="{{ route('apply.step', $missing['step']) }}" class="alert-link">ไปกรอก →</a>
    </div>
@endif

@foreach ($steps as $s)
    <div class="card mb-3">
        <div class="card-header"><i class="bi {{ $S[$s][1] }}"></i> {{ $S[$s][0] }}
            <a href="{{ route('apply.step', $s) }}" class="ms-auto small"><i class="bi bi-pencil"></i> แก้ไข</a>
        </div>
        <div class="card-body">
            <dl class="row mb-0 small">
                @foreach ($rows[$s] as [$label, $value])
                    @if ($value !== null && $value !== '')
                        <dt class="col-sm-4 text-muted fw-normal">{{ $label }}</dt><dd class="col-sm-8" style="white-space:pre-line">{{ $value }}</dd>
                    @endif
                @endforeach
                @foreach (\App\Support\AdmissionForm::questionsFor($a->level, $config, $s) as $q)
                    @php($ans = $answers->get($q['id']))
                    <dt class="col-sm-4 text-muted fw-normal">{{ $q['label'] }}</dt>
                    <dd class="col-sm-8" style="white-space:pre-line">@if (! $ans)<span class="{{ $q['required'] ? 'text-danger' : 'text-muted' }}">{{ $q['required'] ? 'ยังไม่ได้ตอบ' : '-' }}</span>@elseif ($q['type'] === 'file')<a href="{{ route('apply.file', $q['id']) }}" target="_blank"><i class="bi bi-paperclip"></i> {{ \App\Support\AdmissionForm::display($ans) }}</a>@else{{ \App\Support\AdmissionForm::display($ans) }}@endif</dd>
                @endforeach
            </dl>
        </div>
    </div>
@endforeach

@if ($fee > 0)
    <div class="alert alert-info"><i class="bi bi-cash-coin"></i> ค่าสมัครชั้น {{ $a->level }} <b>{{ baht($fee) }} บาท</b> — ชำระหลังส่งใบสมัคร (สแกนพร้อมเพย์หรือโอน แล้วแนบสลิป)</div>
@endif

@if ($closedReason)
    <div class="alert alert-danger">{{ $closedReason }} — ส่งใบสมัครไม่ได้แล้ว</div>
@else
    <form method="POST" action="{{ route('apply.submit') }}" class="card">
        @csrf
        <div class="card-body">
            <label class="form-check">
                <input type="checkbox" name="confirm" value="1" class="form-check-input @error('confirm') is-invalid @enderror" required>
                ข้าพเจ้าขอรับรองว่าข้อมูลข้างต้นเป็นความจริงทุกประการ
            </label>
            @error('confirm')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>
        <div class="card-footer bg-transparent d-flex flex-wrap gap-2">
            <a href="{{ route('apply.step', end($steps)) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> ย้อนกลับ</a>
            <button class="btn btn-primary btn-lg ms-auto" @disabled($missing)><i class="bi bi-send"></i> ส่งใบสมัคร</button>
        </div>
    </form>
@endif
@endsection
