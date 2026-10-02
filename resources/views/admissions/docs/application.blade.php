@extends('admissions.docs._layout')

@section('doc')
@php
    $levels = \App\Support\AdmissionForm::levels();
    if (! in_array($a->level, $levels, true)) { $levels[] = $a->level; }
    $logo = school('logo') ? asset('storage/'.school('logo')) : null;
    $gender = ['M' => 'ชาย', 'F' => 'หญิง'][$a->gender] ?? '';
    $answers = collect($a->answers ?? []);
    $files = $answers->where('type', 'file');
@endphp
<div class="page">
    {{-- ส่วนที่ 1 สำหรับโรงเรียน --}}
    <div style="display:grid;grid-template-columns:1fr auto 1fr;align-items:start;gap:1rem">
        <div class="small">
            <b>(ส่วนที่ 1 สำหรับโรงเรียน)</b><br>
            เลขที่ใบสมัคร <span class="fill">{{ $a->app_no }}</span><br>
            @if ($a->fee_amount)ค่าสมัคร <span class="fill">{{ $a->fee_status === 'paid' ? 'ชำระแล้ว '.$a->fee_receipt_no : 'ยังไม่ชำระ' }}</span>@endif
        </div>
        <div class="center">@if ($logo)<img src="{{ $logo }}" class="logo" alt="">@endif</div>
        <div style="justify-self:end"><div class="photo">@if ($photoUrl)<img src="{{ $photoUrl }}" alt="">@else รูปถ่าย<br>ขนาด 3×4 ซม.@endif</div></div>
    </div>
    <div class="center" style="margin-top:-.6rem">
        <h1>ใบสมัครเข้าเรียน</h1>
        <div>{{ school('school_name') }} ปีการศึกษา {{ $a->year }}</div>
        <div style="margin:.3rem 0">@foreach ($levels as $l)<span class="check {{ $l === $a->level ? 'on' : '' }}">ชั้น {{ $l }}</span>@endforeach</div>
    </div>

    <div class="line">ข้าพเจ้า <span class="fill w">{{ $a->prefix }}{{ $a->first_name }}</span> นามสกุล <span class="fill w">{{ $a->last_name }}</span>
        @if ($a->nickname)ชื่อเล่น <span class="fill">{{ $a->nickname }}</span>@endif</div>
    <div class="line">เลขประจำตัวประชาชน @include('admissions.docs._idboxes', ['id' => $a->citizen_id])
        เพศ <span class="fill s">{{ $gender }}</span></div>
    <div class="line">เกิดวันที่ <span class="fill w">{{ thai_date($a->birthdate, true) }}</span> อายุ <span class="fill s">{{ $a->birthdate?->age }}</span> ปี</div>
    <div class="line">สำเร็จการศึกษา/กำลังศึกษาจากโรงเรียน <span class="fill grow left">{{ $a->previous_school }}</span>
        เกรดเฉลี่ย <span class="fill s">{{ $a->gpa ? number_format($a->gpa, 2) : '' }}</span></div>
    <div class="line">ชื่อ-สกุลผู้ปกครอง <span class="fill w">{{ $a->parent_name }}</span> เกี่ยวข้องเป็น <span class="fill">{{ $a->relation }}</span>
        โทรศัพท์ <span class="fill">{{ $a->parent_phone }}</span></div>
    <div class="line">ที่อยู่ปัจจุบัน <span class="fill grow left">{{ $a->address }}</span></div>

    @foreach ($answers->where('type', '!=', 'file') as $ans)
        <div class="line">{{ $ans['label'] }} <span class="fill grow left">{{ \App\Support\AdmissionForm::display($ans) }}</span></div>
    @endforeach
    @if ($a->note)<div class="line">ข้อมูลเพิ่มเติม <span class="fill grow left">{{ $a->note }}</span></div>@endif
    @if ($files->isNotEmpty())
        <div class="line">หลักฐานการสมัคร (แนบออนไลน์)
            @foreach ($files as $f)<span class="check on">{{ $f['label'] }}</span>@endforeach
        </div>
    @endif

    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;margin-top:.4rem">
        <div class="small muted" style="max-width:80mm;padding-top:1.2rem">
            สมัครออนไลน์เมื่อ {{ $a->submitted_at ? thai_datetime($a->submitted_at) : '-' }}<br>
            ข้าพเจ้าขอรับรองว่าข้อความข้างต้นเป็นความจริงทุกประการ
        </div>
        <div>
            <div class="sign">ลงชื่อ <span class="fill w"></span> ผู้สมัคร<br>( <span class="fill w">{{ $a->fullName() }}</span> )</div><br>
            <div class="sign">ลงชื่อ <span class="fill w"></span> ผู้ปกครอง<br>( <span class="fill w">{{ $a->parent_name }}</span> )</div><br>
            <div class="sign">ลงชื่อ <span class="fill w"></span> ผู้รับสมัคร<br>( <span class="fill w"></span> )<br>วันที่ <span class="fill w"></span></div>
        </div>
    </div>

    @if ($config['exam_slip'])
        <div class="cut"></div>
        {{-- ส่วนที่ 2 สำหรับผู้สมัคร (บัตรประจำตัวผู้สอบ) --}}
        <div style="display:grid;grid-template-columns:1fr auto 1fr;align-items:start;gap:1rem">
            <div><b class="small">(ส่วนที่ 2 สำหรับผู้สมัคร)</b>
                <div class="box" style="padding:.4rem .7rem;margin-top:.3rem;line-height:2">
                    เลขประจำตัวสอบ <span class="fill">{{ $a->exam_no }}</span><br>
                    ห้องสอบที่ <span class="fill">{{ $a->exam_room }}</span><br>
                    เลขที่นั่งสอบ <span class="fill">{{ $a->exam_seat }}</span><br>
                    เลขที่ใบสมัคร <span class="fill">{{ $a->app_no }}</span>
                </div>
            </div>
            <div class="center">@if ($logo)<img src="{{ $logo }}" class="logo" alt="">@endif<div class="small">{{ school('school_name') }}</div></div>
            <div style="justify-self:end"><div class="photo">@if ($photoUrl)<img src="{{ $photoUrl }}" alt="">@else รูปถ่าย<br>ขนาด 3×4 ซม.@endif</div></div>
        </div>
        <div class="line" style="margin-top:.4rem">ข้าพเจ้า <span class="fill w">{{ $a->fullName() }}</span>
            สมัครเข้าศึกษาต่อระดับ @foreach ($levels as $l)<span class="check {{ $l === $a->level ? 'on' : '' }}">ชั้น {{ $l }}</span>@endforeach</div>
        <div class="line">จากโรงเรียน <span class="fill grow left">{{ $a->previous_school }}</span></div>
        <div class="right"><div class="sign">ลงชื่อ <span class="fill w"></span> ผู้รับสมัคร<br>( <span class="fill w"></span> )<br>วันที่ <span class="fill w"></span></div></div>
        <div class="small muted">นำส่วนนี้มาแสดงในวันสอบพร้อมบัตรประจำตัวประชาชน/สูติบัตร</div>
    @endif
</div>
@endsection
