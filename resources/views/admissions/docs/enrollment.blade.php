@extends('admissions.docs._layout')

@section('doc')
@php
    $logo = school('logo') ? asset('storage/'.school('logo')) : null;
    $st = $a->student;
    $answers = collect($a->answers ?? [])->where('type', '!=', 'file');
    $used = [];
    // ดึงคำตอบจากคำถามที่โรงเรียนตั้งเอง ถ้าข้อความคำถามตรงกับช่องในใบมอบตัว (ไม่ตรง = เว้นเส้นประให้กรอกมือ)
    $pick = function (string ...$words) use ($answers, &$used) {
        foreach ($words as $w) { // คำแรกสำคัญที่สุด
            foreach ($answers as $ans) {
                if (! in_array($ans['id'], $used, true) && str_contains($ans['label'], $w)) {
                    $used[] = $ans['id'];

                    return \App\Support\AdmissionForm::display($ans);
                }
            }
        }

        return '';
    };
    $gender = $a->gender;
    $father = $a->relation === 'บิดา' ? $a->parent_name : $pick('ชื่อบิดา', 'ชื่อ-สกุลบิดา', 'ชื่อ-สกุล บิดา');
    $mother = $a->relation === 'มารดา' ? $a->parent_name : $pick('ชื่อมารดา', 'ชื่อ-สกุลมารดา', 'ชื่อ-สกุล มารดา');
@endphp
<div class="page">
    <div style="display:grid;grid-template-columns:1fr auto 1fr;align-items:start;gap:1rem">
        <div></div>
        <div class="center">@if ($logo)<img src="{{ $logo }}" class="logo" alt="">@endif</div>
        <div style="justify-self:end;display:flex;gap:.6rem;align-items:flex-start">
            <div class="box small center" style="min-width:36mm;line-height:1.6">เลขประจำตัวนักเรียน<br><b style="font-size:1.1rem;color:#1e3a8a">{{ $st?->student_code ?: ' ' }}</b><br><span class="muted">สำหรับเจ้าหน้าที่</span></div>
            <div class="photo">@if ($photoUrl)<img src="{{ $photoUrl }}" alt="">@else รูปถ่าย<br>ขนาด 3×4 ซม.@endif</div>
        </div>
    </div>
    <div class="center">
        <h1>ใบมอบตัวนักเรียน ระดับชั้น {{ $a->level }} ห้อง <span class="fill s">{{ $st?->classroom ? $st->classroom->room : '' }}</span></h1>
        <div><b>{{ school('school_name') }} ปีการศึกษา {{ $a->year }}</b></div>
        <div>มอบตัววันที่ <span class="fill s"></span> เดือน <span class="fill"></span> พ.ศ. <span class="fill s"></span></div>
    </div>

    <h2>ข้อมูลนักเรียน</h2>
    <div class="line">1. ชื่อ-สกุลนักเรียน <span class="fill grow left">{{ $a->fullName() }}</span>
        เพศ <span class="check radio {{ $gender === 'M' ? 'on' : '' }}">ชาย</span><span class="check radio {{ $gender === 'F' ? 'on' : '' }}">หญิง</span></div>
    <div class="line">เลขประจำตัวประชาชน @include('admissions.docs._idboxes', ['id' => $a->citizen_id])</div>
    <div class="line">ชื่อเล่น <span class="fill s">{{ $a->nickname }}</span> วันเดือนปีเกิด <span class="fill">{{ thai_date($a->birthdate, true) }}</span>
        ศาสนา <span class="fill s">{{ $pick('ศาสนา') }}</span> เชื้อชาติ <span class="fill s">{{ $pick('เชื้อชาติ') }}</span> สัญชาติ <span class="fill s">{{ $pick('สัญชาติ') }}</span></div>
    <div class="line">หมู่เลือด <span class="fill s">{{ $pick('หมู่เลือด', 'กรุ๊ปเลือด') }}</span> น้ำหนัก <span class="fill s">{{ $pick('น้ำหนัก') }}</span> กิโลกรัม
        ส่วนสูง <span class="fill s">{{ $pick('ส่วนสูง') }}</span> เซนติเมตร เบอร์โทรนักเรียน <span class="fill">{{ $pick('เบอร์นักเรียน', 'เบอร์โทรนักเรียน', 'มือถือนักเรียน') }}</span></div>
    <div class="line">2. จบการศึกษาจากโรงเรียน <span class="fill xl left">{{ $a->previous_school }}</span></div>
    <div class="line">ตำบล/แขวง <span class="fill"></span> อำเภอ/เขต <span class="fill"></span> จังหวัด <span class="fill"></span></div>
    <div class="line">3. ที่อยู่ปัจจุบันที่สามารถติดต่อได้ <span class="fill grow left">{{ $a->address }}</span></div>
    @unless ($a->address)<div class="line"><span class="fill grow"></span></div>@endunless

    <h2>ข้อมูล บิดา มารดา (โดยกำเนิด)</h2>
    <div class="line">1. ชื่อ-สกุล บิดา <span class="fill xl left">{{ $father }}</span> อายุ <span class="fill s"></span> ปี</div>
    <div class="line">อาชีพ <span class="fill w">{{ $a->relation === 'บิดา' ? $pick('อาชีพผู้ปกครอง', 'อาชีพบิดา') : $pick('อาชีพบิดา') }}</span> รายได้/เดือน <span class="fill"></span> บาท
        โทรศัพท์ <span class="fill">{{ $a->relation === 'บิดา' ? $a->parent_phone : '' }}</span></div>
    <div class="line">2. ชื่อ-สกุล มารดา <span class="fill xl left">{{ $mother }}</span> อายุ <span class="fill s"></span> ปี</div>
    <div class="line">อาชีพ <span class="fill w">{{ $a->relation === 'มารดา' ? $pick('อาชีพผู้ปกครอง', 'อาชีพมารดา') : $pick('อาชีพมารดา') }}</span> รายได้/เดือน <span class="fill"></span> บาท
        โทรศัพท์ <span class="fill">{{ $a->relation === 'มารดา' ? $a->parent_phone : '' }}</span></div>
    <div class="line"><b>สถานภาพของบิดา มารดา</b>
        @foreach (['อยู่ด้วยกัน', 'หย่าร้าง', 'แยกกันอยู่', 'บิดาถึงแก่กรรม', 'มารดาถึงแก่กรรม'] as $s)<span class="check radio">{{ $s }}</span>@endforeach</div>
    @php($isParent = in_array($a->relation, ['บิดา', 'มารดา'], true))
    <div class="line">3. ชื่อ-สกุล ผู้ปกครอง <span class="fill w left">{{ $isParent ? '' : $a->parent_name }}</span> <b>(กรณีที่ไม่ได้อยู่กับบิดา-มารดา)</b></div>
    <div class="line">เกี่ยวข้องกับนักเรียนเป็น <span class="fill">{{ $isParent ? '' : $a->relation }}</span> อายุ <span class="fill s"></span> ปี อาชีพ <span class="fill w"></span></div>
    <div class="line">รายได้/เดือน <span class="fill"></span> บาท โทรศัพท์ <span class="fill">{{ $isParent ? '' : $a->parent_phone }}</span></div>

    @php($rest = $answers->reject(fn ($x) => in_array($x['id'], $used, true)))
    @if ($rest->isNotEmpty())
        <h2>ข้อมูลเพิ่มเติมจากใบสมัคร</h2>
        @foreach ($rest as $ans)
            <div class="line small">{{ $ans['label'] }} <span class="fill grow left">{{ \App\Support\AdmissionForm::display($ans) }}</span></div>
        @endforeach
    @endif

    <h2 class="center" style="text-decoration:none">รายละเอียดเพิ่มเติม</h2>
    <p style="text-indent:2.5rem;margin:.2rem 0">{{ $config['pledge'] }}</p>
    <div class="right">
        <div class="sign">ลงชื่อ <span class="fill w"></span> นักเรียน<br>( <span class="fill w">{{ $a->fullName() }}</span> )</div><br>
        <div class="sign">ลงชื่อ <span class="fill w"></span> ผู้ปกครอง<br>( <span class="fill w">{{ $a->parent_name }}</span> )</div>
    </div>
    <p class="center small" style="margin-top:1rem"><b>***หมายเหตุ</b> ให้ผู้ปกครองที่นำนักเรียนมามอบตัว เป็นผู้ลงชื่อต่อหน้าเจ้าหน้าที่ผู้ตรวจเอกสาร***</p>
</div>
@endsection
