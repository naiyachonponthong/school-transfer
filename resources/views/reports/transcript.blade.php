@extends('layouts.app')
@section('title', 'ปพ.1 '.$student->fullName())

@section('content')
<div class="d-flex gap-2 mb-3 no-print">
    <a href="{{ url()->previous() }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    <button onclick="print()" class="btn btn-primary ms-auto"><i class="bi bi-printer"></i> พิมพ์ / บันทึก PDF</button>
</div>
<div class="card" style="max-width:900px;margin:auto">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            @if (school('logo'))<img src="{{ asset('storage/'.school('logo')) }}" alt="" style="height:64px" class="mb-2">@endif
            <h2 class="h5 fw-bold mb-0">ระเบียนแสดงผลการเรียน (ปพ.1)</h2>
            <div>{{ school('school_name') }}</div>
        </div>
        <div class="row small mb-3 g-2">
            <div class="col-sm-6"><span class="text-muted">ชื่อ-สกุล:</span> <b>{{ $student->fullName() }}</b></div>
            <div class="col-sm-6"><span class="text-muted">เลขประจำตัวประชาชน:</span> {{ $student->citizen_id ?: '-' }}</div>
            <div class="col-sm-6"><span class="text-muted">รหัสนักเรียน:</span> {{ $student->student_code }}</div>
            <div class="col-sm-6"><span class="text-muted">วันเกิด:</span> {{ $student->birthdate ? thai_date($student->birthdate, true) : '-' }}</div>
        </div>

        @forelse ($terms as $t)
            <div class="fw-semibold mt-3 mb-1">{{ $t['term']->label() }}</div>
            <table class="table table-bordered table-sm small mb-1">
                <thead><tr class="text-center"><th style="width:110px">รหัสวิชา</th><th>รายวิชา</th><th style="width:80px">หน่วยกิต</th><th style="width:70px">ผลการเรียน</th></tr></thead>
                <tbody>
                @foreach ($t['rows'] as $r)
                    <tr><td class="text-center">{{ $r['course']->subject->code }}</td><td>{{ $r['course']->subject->name }}</td><td class="text-center">{{ $r['course']->subject->credit }}</td><td class="text-center fw-bold">{{ $r['grade'] ?? '-' }}</td></tr>
                @endforeach
                </tbody>
            </table>
            <div class="text-end small mb-2">หน่วยกิตภาคนี้ {{ $t['credits'] }} · GPA <b>{{ $t['gpa'] !== null ? number_format($t['gpa'], 2) : '-' }}</b></div>
        @empty
            <div class="text-center text-muted py-4">ยังไม่มีผลการเรียน</div>
        @endforelse

        <div class="border rounded-3 p-3 mt-3 d-flex justify-content-around text-center">
            <div><div class="small text-muted">หน่วยกิตสะสมที่ได้</div><div class="fs-4 fw-bold">{{ $credits }}</div></div>
            <div><div class="small text-muted">ผลการเรียนเฉลี่ยสะสม (GPAX)</div><div class="fs-4 fw-bold text-primary">{{ $gpax !== null ? number_format($gpax, 2) : '-' }}</div></div>
        </div>

        <div class="row text-center small mt-5 pt-3">
            <div class="col-6"><div>ลงชื่อ ...........................................</div><div class="text-muted mt-1">นายทะเบียน</div></div>
            <div class="col-6"><div>ลงชื่อ ...........................................</div><div class="mt-1">( {{ school('director_name') ?: '...........................................' }} )</div><div class="text-muted">ผู้อำนวยการโรงเรียน</div></div>
        </div>
        <div class="small text-muted mt-4">พิมพ์เมื่อ {{ thai_datetime(now()) }} · เอกสารนี้ใช้ประกอบการตรวจสอบภายใน ฉบับทางการให้ใช้แบบพิมพ์ ปพ.1 ของ สพฐ.</div>
    </div>
</div>
@endsection
