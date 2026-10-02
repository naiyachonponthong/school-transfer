@extends('layouts.app')
@section('title', $survey->exists ? 'แก้ไขแบบประเมิน' : 'สร้างแบบประเมิน')

@section('content')
<div class="page-head"><div><h1>{{ $survey->exists ? 'แก้ไข: '.$survey->title : 'สร้างแบบประเมิน' }}</h1><div class="sub">กำหนดตัวเลือก ด้าน เกณฑ์ และข้อคำถามในรูปแบบข้อความ</div></div></div>
<form method="POST" action="{{ $survey->exists ? route('surveys.update', $survey) : route('surveys.store') }}" class="row g-3">
    @csrf @if ($survey->exists) @method('PUT') @endif
    <div class="col-lg-7">
        <div class="card">
            <div class="card-body">
                <div class="mb-2"><label class="form-label">ชื่อแบบประเมิน</label><input name="title" value="{{ old('title', $survey->title) }}" class="form-control" required></div>
                <div class="mb-2"><label class="form-label">คำอธิบาย</label><textarea name="description" rows="2" class="form-control">{{ old('description', $survey->description) }}</textarea></div>
                <div class="row g-2 mb-2">
                    <div class="col-7"><label class="form-label">ผู้ตอบ</label><select name="respondent" class="form-select">@foreach (\App\Models\Survey::RESPONDENTS as $k => $v)<option value="{{ $k }}" @selected(old('respondent', $survey->respondent) === $k)>{{ $v }}</option>@endforeach</select></div>
                    <div class="col-5 d-flex align-items-end"><label class="form-check form-switch mb-2"><input type="checkbox" class="form-check-input" name="is_active" value="1" @checked(old('is_active', $survey->is_active))> เปิดใช้งาน</label></div>
                </div>
                <label class="form-label">โครงสร้างแบบประเมิน</label>
                <textarea name="definition" rows="26" class="form-control font-monospace small" required>{{ old('definition', $text) }}</textarea>
                @if ($survey->exists && $survey->responses()->exists())
                    <div class="small text-warning mt-1"><i class="bi bi-lock"></i> มีผู้ตอบแล้ว แก้ได้เฉพาะชื่อ คำอธิบาย และเกณฑ์แปลผล (ข้อคำถามล็อกไว้เพื่อไม่ให้ผลเดิมผิดเพี้ยน)</div>
                @endif
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-primary"><i class="bi bi-save"></i> บันทึก</button></div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card"><div class="card-body small">
            <div class="fw-bold mb-2"><i class="bi bi-lightbulb text-warning"></i> วิธีเขียน</div>
            <p><code>[ตัวเลือก]</code> บรรทัดละตัวเลือก <code>ข้อความ=คะแนน</code></p>
            <p><code>[ด้าน]</code> <code>รหัส | ชื่อด้าน | นับรวม 1/0 | เกณฑ์</code><br>เกณฑ์: <code>ปกติ&lt;=4 success; เสี่ยง&lt;=5 warning; มีปัญหา&lt;=10 danger</code> (ชื่อ &lt;= คะแนนสูงสุดของช่วง ตามด้วยสี)</p>
            <p><code>[รวม]</code> เกณฑ์ของคะแนนรวม (เฉพาะด้านที่นับรวม = 1)</p>
            <p><code>[ข้อคำถาม]</code> <code>ข้อความ | รหัสด้าน | R</code> ใส่ R ถ้าเป็นข้อกลับคะแนน (ข้อเชิงบวกในด้านที่คะแนนสูง = มีปัญหา)</p>
            <p class="mb-0">บรรทัดที่ขึ้นต้นด้วย <code>#</code> คือหมายเหตุ ระบบไม่อ่าน</p>
        </div></div>
    </div>
</form>
@endsection
