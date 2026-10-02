@extends('layouts.public')
@section('title', \App\Support\AdmissionForm::STEPS[$step][0].' · สมัครเรียน')
@section('heading', 'ใบสมัครเลขที่ '.$a->app_no)
@section('subheading', 'ชั้น '.$a->level.' · ปีการศึกษา '.$year.' · บันทึกทุกขั้น กลับมากรอกต่อได้')

@section('content')
@php
    $stepInfo = \App\Support\AdmissionForm::STEPS[$step];
    $opt = $config['optional'];
    $star = fn ($f) => in_array($f, ['level', 'prefix', 'first_name', 'last_name', 'gender', 'parent_name'], true) || ($opt[$f] ?? '') === 'required' ? ' *' : '';
    $val = fn ($f) => old($f, $a->{$f});
    $saved = collect($a->answers ?? [])->keyBy('id');
    $idx = array_search($step, $steps, true);
@endphp
@include('admissions.apply._progress', ['current' => $step])

<form method="POST" action="{{ route('apply.save', $step) }}" enctype="multipart/form-data" class="card">
    @csrf
    <div class="card-header"><i class="bi {{ $stepInfo[1] }}"></i> ขั้นที่ {{ $idx + 1 }} · {{ $stepInfo[0] }}</div>
    <div class="card-body">
        @if ($errors->any())
            <div class="alert alert-danger small"><i class="bi bi-exclamation-circle"></i> กรุณาตรวจสอบช่องที่มีเครื่องหมายสีแดง</div>
        @endif
        <div class="row g-3">
            @foreach ($fields as $f)
                @switch($f)
                    @case('level')
                        <div class="col-12">
                            <label class="form-label">ระดับชั้นที่สมัคร *</label>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($levels as $l)
                                    @php($isFull = in_array($l, $full, true) && $l !== $a->level)
                                    <input type="radio" class="btn-check" name="level" value="{{ $l }}" id="lv{{ $loop->index }}" @checked($val('level') === $l) @disabled($isFull)>
                                    <label class="btn btn-outline-primary px-4" for="lv{{ $loop->index }}">{{ $l }}@if ($isFull) <small>(เต็มแล้ว)</small>@endif</label>
                                @endforeach
                            </div>
                            <div class="form-text">เปลี่ยนชั้นแล้ว คำถามบางข้ออาจเปลี่ยนตามชั้น</div>
                            @error('level')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>
                        @break
                    @case('prefix')
                        <div class="col-md-3"><label class="form-label">คำนำหน้า *</label>
                            <select name="prefix" class="form-select @error('prefix') is-invalid @enderror">@foreach (['เด็กชาย', 'เด็กหญิง', 'นาย', 'นางสาว'] as $p)<option @selected($val('prefix') === $p)>{{ $p }}</option>@endforeach</select></div>
                        @break
                    @case('gender')
                        <div class="col-md-3"><label class="form-label">เพศ *</label>
                            <select name="gender" class="form-select @error('gender') is-invalid @enderror"><option value="">- เลือก -</option><option value="M" @selected($val('gender') === 'M')>ชาย</option><option value="F" @selected($val('gender') === 'F')>หญิง</option></select>
                            @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        @break
                    @case('gpa')
                        <div class="col-md-4"><label class="form-label">เกรดเฉลี่ยเดิม{{ $star('gpa') }}</label>
                            <input name="gpa" type="number" step="0.01" min="0" max="4" value="{{ $val('gpa') }}" class="form-control @error('gpa') is-invalid @enderror">
                            @error('gpa')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        @break
                    @case('address')
                    @case('note')
                        <div class="col-12"><label class="form-label">{{ $f === 'address' ? 'ที่อยู่ปัจจุบัน' : 'ข้อมูลเพิ่มเติม' }}{{ $star($f) }}</label>
                            <textarea name="{{ $f }}" rows="3" class="form-control @error($f) is-invalid @enderror" placeholder="{{ $f === 'address' ? 'บ้านเลขที่ หมู่ ถนน ตำบล อำเภอ จังหวัด รหัสไปรษณีย์' : 'เช่น ความสามารถพิเศษ' }}">{{ $val($f) }}</textarea>
                            @error($f)<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        @break
                    @default
                        @php($labels = ['first_name' => 'ชื่อ', 'last_name' => 'นามสกุล', 'nickname' => 'ชื่อเล่น', 'previous_school' => 'โรงเรียนเดิม', 'parent_name' => 'ชื่อ-สกุล ผู้ปกครอง', 'relation' => 'ความสัมพันธ์กับผู้สมัคร'])
                        <div class="{{ in_array($f, ['previous_school', 'parent_name'], true) ? 'col-md-8' : ($f === 'nickname' ? 'col-md-3' : 'col-md-' . ($f === 'relation' ? 4 : 5)) }}">
                            <label class="form-label">{{ $labels[$f] }}{{ $star($f) }}</label>
                            <input name="{{ $f }}" value="{{ $val($f) }}" class="form-control @error($f) is-invalid @enderror" @if($f === 'relation') list="rel" @endif>
                            @error($f)<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        @if ($f === 'relation')<datalist id="rel"><option>บิดา</option><option>มารดา</option><option>ผู้ปกครอง</option></datalist>@endif
                @endswitch
            @endforeach

            @if ($step === 'family')
                <div class="col-md-4"><label class="form-label">เบอร์โทรผู้ปกครอง</label><input class="form-control" value="{{ $a->parent_phone }}" disabled>
                    <div class="form-text">ใช้เปิดใบสมัคร แก้ไม่ได้</div></div>
            @endif
            @if ($step === 'student')
                <div class="col-md-5"><label class="form-label">เลขประจำตัวประชาชน</label><input class="form-control" value="{{ $a->citizen_id }}" disabled></div>
                <div class="col-md-4"><label class="form-label">วันเกิด</label><input class="form-control" value="{{ thai_date($a->birthdate, true) }}" disabled></div>
            @endif

            @foreach ($questions as $q)
                @include('admissions._question', ['q' => $q, 'saved' => $saved->get($q['id'])])
            @endforeach
        </div>
    </div>
    <div class="card-footer bg-transparent">
        {{-- ปุ่ม "ถัดไป" อยู่ก่อนใน DOM เพื่อให้กด Enter แล้วไปขั้นถัดไป (แสดงผลกลับด้านด้วย flex-row-reverse) --}}
        <div class="d-flex flex-row-reverse flex-wrap gap-2">
            <button name="nav" value="next" class="btn btn-primary px-4">{{ $idx === count($steps) - 1 ? 'ตรวจสอบและยืนยัน' : 'ถัดไป' }} <i class="bi bi-arrow-right"></i></button>
            <button name="nav" value="save" class="btn btn-light border"><i class="bi bi-save"></i> บันทึกไว้ก่อน</button>
            @if ($idx > 0)<button name="nav" value="back" class="btn btn-light border me-auto"><i class="bi bi-arrow-left"></i> ย้อนกลับ</button>@endif
        </div>
    </div>
</form>
<div class="d-flex justify-content-between mt-3 small">
    <span class="text-muted"><i class="bi bi-info-circle"></i> กลับมากรอกต่อ: หน้าสมัครเรียน → กรอกเลขบัตร วันเกิด เบอร์โทรเดิม</span>
    <form method="POST" action="{{ route('apply.leave') }}">@csrf<button class="btn btn-link btn-sm p-0">ออก (กรอกต่อภายหลัง)</button></form>
</div>
@endsection
