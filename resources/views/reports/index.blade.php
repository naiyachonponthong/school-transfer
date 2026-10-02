@extends('layouts.app')
@section('title', 'รายงานและเอกสาร')

@section('content')
<div class="page-head"><div><h1>รายงานและเอกสาร</h1><div class="sub">เอกสาร ปพ. และไฟล์ส่งหน่วยงาน</div></div></div>
<div class="row g-3">
    @php
        $cards = [
            ['bi-file-earmark-text', 'ปพ.1 ระเบียนแสดงผลการเรียน', 'ผลการเรียนทุกภาคเรียน + GPAX เปิดจากหน้าข้อมูลนักเรียน → ปุ่ม "ปพ.1"', route('students.index'), 'เลือกนักเรียน'],
            ['bi-journal-text', 'ปพ.6 สมุดรายงานประจำตัว', 'ผลการเรียนภาคเรียนปัจจุบัน เวลาเรียน ความประพฤติ เปิดจากหน้าข้อมูลนักเรียน → "สมุดพก"', route('students.index'), 'เลือกนักเรียน'],
            ['bi-journal-check', 'ปพ.5 แบบบันทึกผลการเรียนรายวิชา', 'ส่งออกคะแนนทุกช่อง + เกรด ของแต่ละรายวิชาเป็น Excel', route('courses.index'), 'ไปที่รายวิชา'],
            ['bi-table', 'รายงานเวลาเรียนรายเดือน', 'ตาราง มา/ขาด/ลา/สาย รายวัน ส่งออก Excel ได้', route('attendance.report'), 'เปิดรายงาน'],
            ['bi-person-check', 'การมาปฏิบัติงานของครู', 'ลงเวลาเข้า-ออก พร้อมระยะห่างจากโรงเรียน (GPS)', auth()->user()->isAdmin() ? route('staff-attendance.report') : route('checkin'), 'เปิดรายงาน'],
        ];
    @endphp
    @foreach ($cards as [$icon, $title, $desc, $url, $btn])
        <div class="col-md-6 col-xl-4">
            <div class="card h-100"><div class="card-body d-flex flex-column">
                <div class="d-flex gap-3 mb-2"><span class="app-ico" style="width:46px;height:46px;font-size:1.2rem"><i class="bi {{ $icon }}"></i></span><div class="fw-bold">{{ $title }}</div></div>
                <div class="small text-muted flex-grow-1">{{ $desc }}</div>
                <a href="{{ $url }}" class="btn btn-soft mt-3">{{ $btn }}</a>
            </div></div>
        </div>
    @endforeach
    <div class="col-md-6 col-xl-4">
        <form method="GET" action="{{ route('reports.dmc') }}" class="card h-100"><div class="card-body d-flex flex-column">
            <div class="d-flex gap-3 mb-2"><span class="app-ico ico-teal" style="width:46px;height:46px;font-size:1.2rem"><i class="bi bi-cloud-arrow-up"></i></span><div class="fw-bold">ข้อมูลนักเรียนสำหรับ DMC</div></div>
            <div class="small text-muted flex-grow-1">ไฟล์ CSV เลขบัตร ชื่อ ชั้น ห้อง เลขที่ วันเกิด ผู้ปกครอง · ตรวจรูปแบบคอลัมน์กับ DMC ปีปัจจุบันก่อนนำเข้า</div>
            <select name="classroom" class="form-select form-select-sm mt-2"><option value="">ทั้งโรงเรียน</option>@foreach ($classrooms as $c)<option value="{{ $c->id }}">{{ $c->name() }}</option>@endforeach</select>
            <button class="btn btn-soft mt-2"><i class="bi bi-download"></i> ดาวน์โหลด CSV</button>
        </div></form>
    </div>
</div>
@endsection
