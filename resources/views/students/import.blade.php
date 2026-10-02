@extends('layouts.app')
@section('title', 'นำเข้านักเรียน')

@section('content')
<div class="page-head">
    <div>
        <h1>นำเข้านักเรียนจาก Excel</h1>
        <div class="sub">ไม่ต้องกรอกทีละคน — คัดลอกจาก Excel/Google Sheets แล้ววางได้เลย</div>
    </div>
    <div class="actions"><a href="{{ route('students.import.template') }}" class="btn btn-light border"><i class="bi bi-download"></i> ดาวน์โหลดแบบฟอร์ม</a></div>
</div>

@if (session('import_errors'))
    <div class="alert alert-warning">
        <div class="fw-semibold">มีบางแถวที่ข้าม:</div>
        <ul class="mb-0">@foreach (session('import_errors') as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('students.import.store') }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-header"><i class="bi bi-clipboard"></i> วิธีที่ 1: วางข้อมูล (แนะนำ)</div>
            <div class="card-body">
                <p class="small text-muted mb-2">ใน Excel ให้เลือกตารางทั้งหมด <b>รวมแถวหัวตาราง</b> กด Ctrl+C แล้วมาวางในช่องด้านล่าง (Ctrl+V)</p>
                <textarea name="paste" rows="10" class="form-control font-monospace small" placeholder="รหัสนักเรียน&#9;คำนำหน้า&#9;ชื่อ&#9;นามสกุล&#9;ห้อง&#9;เลขที่&#10;10001&#9;เด็กชาย&#9;สมชาย&#9;ใจดี&#9;ป.4/1&#9;1"></textarea>
            </div>
            <div class="card-header border-top"><i class="bi bi-file-earmark-spreadsheet"></i> วิธีที่ 2: อัปโหลดไฟล์ CSV</div>
            <div class="card-body">
                <input type="file" name="file" accept=".csv,.txt" class="form-control">
                <div class="small text-muted mt-1">ใน Excel: ไฟล์ → บันทึกเป็น → CSV UTF-8</div>
            </div>
            <div class="card-body border-top">
                <button class="btn btn-primary btn-lg"><i class="bi bi-upload"></i> นำเข้า</button>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle"></i> หัวตารางที่รองรับ</div>
            <ul class="list-group list-group-flush small">
                @foreach ($columns as $key => $label)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $label }}</span>
                        @if (in_array($key, ['student_code', 'first_name']))<span class="badge bg-danger-subtle text-danger-emphasis">จำเป็น</span>@endif
                    </li>
                @endforeach
            </ul>
            <div class="card-body small text-muted">
                <ul class="ps-3 mb-0">
                    <li>เรียงคอลัมน์ลำดับไหนก็ได้ ขาดบางคอลัมน์ก็ได้</li>
                    <li>ห้อง เขียนแบบ <code>ม.1/2</code> ถ้ายังไม่มีห้องนี้ ระบบสร้างให้</li>
                    <li>วันเกิดใช้ พ.ศ. หรือ ค.ศ. ก็ได้ เช่น <code>15/05/2557</code></li>
                    <li>รหัสนักเรียนซ้ำ = อัปเดตข้อมูลเดิม (นำเข้าซ้ำได้ปลอดภัย)</li>
                    <li>มีเบอร์ผู้ปกครอง = สร้างบัญชีผู้ปกครองให้อัตโนมัติ</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
