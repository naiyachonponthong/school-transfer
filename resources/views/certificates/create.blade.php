@extends('layouts.app')
@section('title', 'ออก ปพ.7 '.$student->fullName())

@section('content')
@php($missing = collect(['citizen_id' => 'เลขประจำตัวประชาชน', 'birthdate' => 'วันเกิด', 'father_name' => 'ชื่อบิดา', 'mother_name' => 'ชื่อมารดา'])->filter(fn ($l, $k) => blank($preview[$k])))
<div class="page-head">
    <div><h1>ออกใบรับรองผลการศึกษา (ปพ.7)</h1><div class="sub">{{ $student->fullName() }} · {{ $student->classroom?->name() ?? '-' }} · {{ \App\Models\Student::STATUSES[$student->status] ?? $student->status }}</div></div>
</div>
<div class="row g-3">
    <div class="col-lg-6">
        <form method="POST" action="{{ route('certificates.store', $student) }}" class="card">
            @csrf
            <div class="card-body">
                <label class="form-label">ออกให้เพื่อ <span class="text-danger">*</span></label>
                <input name="purpose" value="{{ old('purpose') }}" class="form-control mb-2" list="purposes" required autofocus>
                <datalist id="purposes">@foreach (\App\Http\Controllers\CertificateController::PURPOSES as $p)<option>{{ $p }}</option>@endforeach</datalist>
                <div class="small text-muted mb-3">เมื่อกดออก ระบบจะให้เลขที่ถัดไปของปี พ.ศ. {{ today()->year + 543 }} และบันทึกลงทะเบียนคุม (ข้อมูลในใบจะคงตามวันที่ออก)</div>
                <button class="btn btn-primary"><i class="bi bi-file-earmark-check"></i> ออกใบรับรองและพิมพ์</button>
                <a href="{{ route('students.show', $student) }}" class="btn btn-light border">ยกเลิก</a>
            </div>
        </form>
    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-eye"></i> ข้อมูลที่จะพิมพ์</div>
            <div class="card-body small">
                @if ($missing->isNotEmpty())
                    <div class="alert alert-warning py-2 d-block">ยังไม่มี {{ $missing->implode(', ') }} — <a href="{{ route('students.edit', $student) }}" class="alert-link">กรอกข้อมูลนักเรียนก่อน</a> หรือออกไปก่อนแล้วเขียนด้วยมือ</div>
                @endif
                <dl class="row mb-0">
                    <dt class="col-5 fw-normal text-muted">ผลการเรียนเฉลี่ยสะสม</dt><dd class="col-7">{{ $preview['gpax'] !== null ? number_format($preview['gpax'], 2) : '-' }}</dd>
                    <dt class="col-5 fw-normal text-muted">หน่วยกิตสะสมที่ได้</dt><dd class="col-7">{{ $preview['credits'] }}</dd>
                    <dt class="col-5 fw-normal text-muted">ถึงภาคเรียน</dt><dd class="col-7 mb-0">{{ $preview['last_term'] ?? '-' }}</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
