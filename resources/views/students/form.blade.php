@extends('layouts.app')
@section('title', $student->exists ? 'แก้ไขข้อมูลนักเรียน' : 'เพิ่มนักเรียน')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $student->exists ? 'แก้ไข: '.$student->fullName() : 'เพิ่มนักเรียนใหม่' }}</h1>
        <div class="sub">ช่องที่มี <span class="text-danger">*</span> จำเป็นต้องกรอก ที่เหลือกรอกทีหลังได้</div>
    </div>
</div>

<form method="POST" enctype="multipart/form-data" action="{{ $student->exists ? route('students.update', $student) : route('students.store') }}">
    @csrf
    @if ($student->exists) @method('PUT') @endif

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-person-vcard"></i> ข้อมูลหลัก</div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">รหัสนักเรียน <span class="text-danger">*</span></label>
                        <input name="student_code" value="{{ old('student_code', $student->student_code) }}" class="form-control" required autofocus>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">ห้องเรียน</label>
                        <select name="classroom_id" class="form-select">
                            <option value="">- ยังไม่ระบุ -</option>
                            @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected(old('classroom_id', $student->classroom_id) == $c->id)>{{ $c->name() }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">เลขที่</label>
                        <input type="number" name="number" value="{{ old('number', $student->number) }}" class="form-control" min="1">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">คำนำหน้า</label>
                        <input name="prefix" value="{{ old('prefix', $student->prefix) }}" class="form-control" list="prefixes">
                        <datalist id="prefixes"><option>เด็กชาย</option><option>เด็กหญิง</option><option>นาย</option><option>นางสาว</option></datalist>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
                        <input name="first_name" value="{{ old('first_name', $student->first_name) }}" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                        <input name="last_name" value="{{ old('last_name', $student->last_name) }}" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ชื่อเล่น</label>
                        <input name="nickname" value="{{ old('nickname', $student->nickname) }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">เพศ</label>
                        <select name="gender" class="form-select">
                            <option value="">-</option>
                            <option value="M" @selected(old('gender', $student->gender) === 'M')>ชาย</option>
                            <option value="F" @selected(old('gender', $student->gender) === 'F')>หญิง</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">วันเกิด</label>
                        <input type="date" name="birthdate" value="{{ old('birthdate', $student->birthdate?->toDateString()) }}" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">สถานะ</label>
                        <select name="status" class="form-select">
                            @foreach (\App\Models\Student::STATUSES as $k => $v)<option value="{{ $k }}" @selected(old('status', $student->status) === $k)>{{ $v }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">เลขประจำตัวประชาชน</label>
                        <input name="citizen_id" value="{{ old('citizen_id', $student->citizen_id) }}" class="form-control" inputmode="numeric" maxlength="13">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">เบอร์โทรนักเรียน</label>
                        <input name="phone" value="{{ old('phone', $student->phone) }}" class="form-control" inputmode="tel">
                    </div>
                    <div class="col-12">
                        <label class="form-label">ที่อยู่</label>
                        <textarea name="address" rows="2" class="form-control">{{ old('address', $student->address) }}</textarea>
                    </div>
                </div>
            </div>

            @unless ($student->exists)
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-person-hearts"></i> ผู้ปกครอง <span class="small text-muted fw-normal">(ระบบจะสร้างบัญชีให้ผู้ปกครองเข้าดูข้อมูลได้ทันที)</span></div>
                    <div class="card-body row g-3">
                        <div class="col-md-5"><label class="form-label">ชื่อ-สกุลผู้ปกครอง</label><input name="guardian_name" value="{{ old('guardian_name') }}" class="form-control"></div>
                        <div class="col-md-4"><label class="form-label">เบอร์โทร</label><input name="guardian_phone" value="{{ old('guardian_phone') }}" class="form-control" inputmode="tel"></div>
                        <div class="col-md-3"><label class="form-label">ความสัมพันธ์</label><input name="relation" value="{{ old('relation') }}" class="form-control" list="relations"></div>
                        <datalist id="relations"><option>บิดา</option><option>มารดา</option><option>ปู่</option><option>ย่า</option><option>ตา</option><option>ยาย</option><option>ผู้ปกครอง</option></datalist>
                    </div>
                </div>
            @endunless
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-image"></i> รูปถ่าย</div>
                <div class="card-body text-center">
                    <span class="sb-avatar lg mb-3">@if($student->photoUrl())<img src="{{ $student->photoUrl() }}" alt="">@else<i class="bi bi-person"></i>@endif</span>
                    <input type="file" name="photo" accept="image/*" class="form-control">
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-heart-pulse"></i> สุขภาพ</div>
                <div class="card-body">
                    <label class="form-label">กรุ๊ปเลือด</label>
                    <select name="blood_type" class="form-select mb-3">
                        <option value="">-</option>
                        @foreach (['A', 'B', 'AB', 'O'] as $b)<option @selected(old('blood_type', $student->blood_type) === $b)>{{ $b }}</option>@endforeach
                    </select>
                    <label class="form-label">โรคประจำตัว / แพ้ยา / แพ้อาหาร</label>
                    <textarea name="medical_note" rows="3" class="form-control">{{ old('medical_note', $student->medical_note) }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึก</button>
        @unless ($student->exists)
            <button name="another" value="1" class="btn btn-outline-primary btn-lg"><i class="bi bi-plus-lg"></i> บันทึกแล้วเพิ่มคนต่อไป</button>
        @endunless
        <a href="{{ $student->exists ? route('students.show', $student) : route('students.index') }}" class="btn btn-light btn-lg border">ยกเลิก</a>
    </div>
</form>
@endsection
