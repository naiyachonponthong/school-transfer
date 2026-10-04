<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>บันทึกการเยี่ยมบ้าน {{ $student->fullName() }} · {{ school('school_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap">
    <style>
        /* แบบบันทึกการเยี่ยมบ้าน 4 หน้า A4 ตามแบบ สพฐ. — ช่องที่ระบบมีข้อมูลพิมพ์ให้ (สีน้ำเงินเข้ม) ช่องที่ไม่มีเว้นเส้นประให้กรอกด้วยมือ */
        @page { size: A4; margin: 12mm 13mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e9ebef; font: 12.5px/1.6 'Sarabun', sans-serif; color: #111; }
        .toolbar { position: sticky; top: 0; z-index: 5; background: #1f2937; color: #fff; padding: .6rem 1rem; display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; font-size: 14px; }
        .toolbar button, .toolbar a { background: #fff; color: #111; border: 0; border-radius: 8px; padding: .4rem .9rem; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .toolbar .hint { font-size: .82rem; opacity: .8; }
        .page { width: 210mm; height: 297mm; margin: 1rem auto; background: #fff; padding: 12mm 13mm; box-shadow: 0 4px 20px rgba(0,0,0,.12); overflow: hidden; }
        .head { position: relative; text-align: center; border-bottom: 1px solid #999; padding-bottom: 2.5mm; margin-bottom: 3mm; }
        .head h1 { font-size: 19px; margin: 0; line-height: 1.4; }
        .head span { position: absolute; right: 0; top: 0; font-size: 11px; }
        h2 { font-size: 15px; text-align: center; margin: 2mm 0 4mm; }
        .sec { margin-top: 2.2mm; }
        .in1 { padding-left: 6mm; } .in2 { padding-left: 12mm; }
        .line { display: flex; flex-wrap: wrap; align-items: baseline; gap: 0 1.5mm; }
        .fill { flex: 1; min-width: 12mm; border-bottom: 1px dotted #222; padding: 0 1.5mm; line-height: 1.35; color: #1e3a8a; font-weight: 600; min-height: 1.35em; }
        .fill.fix { flex: none; text-align: center; }
        .fill.signed { position: relative; text-align: center; }
        .fill.signed img { position: absolute; left: 50%; bottom: -1mm; transform: translateX(-50%); height: 11mm; max-width: 100%; mix-blend-mode: multiply; }
        .ck { display: inline-flex; align-items: baseline; gap: 1.2mm; }
        .ck::before { content: ''; flex: none; width: 3.2mm; height: 3.2mm; border: 1.1px solid #111; display: inline-block; transform: translateY(.5mm); font-size: 10px; line-height: 2.8mm; text-align: center; color: #1e3a8a; font-weight: 700; }
        .ck.on::before { content: '✓'; }
        .ck.rd::before { border-radius: 50%; }
        .ck.rd.on::before { content: ''; background: radial-gradient(#1e3a8a 45%, #fff 50%); -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .grid { display: grid; gap: .3mm 4mm; }
        .g2 { grid-template-columns: repeat(2, 1fr); } .g3 { grid-template-columns: repeat(3, 1fr); } .g4 { grid-template-columns: repeat(4, 1fr); } .g6 { grid-template-columns: repeat(6, 1fr); }
        .idboxes { display: inline-flex; align-items: center; gap: 1px; vertical-align: middle; }
        .idboxes span { width: 4.6mm; height: 5.2mm; border: 1px solid #111; display: inline-flex; align-items: center; justify-content: center; color: #1e3a8a; font-weight: 700; }
        .idboxes i { width: 1.6mm; border-top: 1px solid #111; }
        .photo { width: 26mm; height: 32mm; border: 1px solid #333; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 11px; overflow: hidden; float: right; margin-left: 4mm; }
        .photo img, .shot img { width: 100%; height: 100%; object-fit: cover; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: .2mm 1mm; }
        th { font-weight: 700; text-align: center; vertical-align: middle; line-height: 1.3; }
        .members { font-size: 11px; margin-top: 1mm; table-layout: fixed; }
        .members td { height: 6mm; text-align: right; color: #1e3a8a; font-weight: 600; }
        .members td.c { text-align: center; color: #111; font-weight: 400; } .members td.l { text-align: left; }
        .members tfoot td { text-align: left; color: #111; font-weight: 700; }
        .members tfoot td:last-child { text-align: right; color: #1e3a8a; }
        .close { width: 86%; margin: 1mm 0 1mm 8mm; } .close td { height: 5.6mm; } .close td.c { text-align: center; color: #1e3a8a; font-weight: 700; }
        .dots { border-bottom: 1px dotted #222; min-height: 6mm; color: #1e3a8a; font-weight: 600; }
        .shot { border: 1px solid #333; height: 72mm; margin: 0 6mm; display: flex; align-items: center; justify-content: center; overflow: hidden; }
        .sign { width: 95mm; margin-left: auto; margin-top: 3mm; }
        .tight .sec { margin-top: 1.1mm; }
        .certify { border: 1px solid #333; margin: 5mm 6mm 0; padding: 2mm 10mm 3mm; }
        .certify .inner { width: 110mm; margin: 0 auto; }
        b.t { font-weight: 700; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; box-shadow: none; width: auto; height: 272mm; padding: 0; break-after: page; }
            .page:last-child { break-after: auto; }
        }
        @media screen and (max-width: 820px) { body { overflow-x: auto; } }
    </style>
</head>
<body>
@php
    $F = \App\Support\HomeVisitForm::class;
    $form = $visit->form ?? [];
    $v = fn (string $key) => $form[$key] ?? '';
    $num = fn ($n) => $n === null || $n === '' ? '' : rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
    $ck = fn (string $key, string $opt) => '<span class="ck'.(in_array($opt, $form[$key] ?? [], true) ? ' on' : '').'">'.e($F::MULTI[$key][$opt]).'</span>';
    $rd = fn (string $key, string $opt) => '<span class="ck rd'.(($form[$key] ?? null) === $opt ? ' on' : '').'">'.e($F::SINGLE[$key][$opt]).'</span>';
    $flag = fn (string $key, string $label, string $shape = 'rd') => '<span class="ck '.$shape.(! empty($form[$key]) ? ' on' : '').'">'.e($label).'</span>';
    $idBoxes = function (?string $id) {
        $d = str_split(str_pad(preg_replace('/\D/', '', (string) $id), 13, ' '));
        $html = '<span class="idboxes">';
        foreach ($d as $i => $c) {
            $html .= (in_array($i, [1, 5, 10, 12], true) ? '<i></i>' : '').'<span>'.e(trim($c)).'</span>';
        }
        return $html.'</span>';
    };
    $members = array_pad($form['members'] ?? [], $F::MEMBER_ROWS, []);
    $months = ['มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน', 'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];
    $on = $visit->exists ? $visit->visited_on : null;
    $guardianName = trim($v('guardian_first').' '.$v('guardian_last'));
@endphp

<div class="toolbar">
    <b>บันทึกการเยี่ยมบ้าน</b> <span class="hint">{{ $student->fullName() }} · {{ $term->label() }}{{ $visit->exists ? '' : ' · ยังไม่ได้บันทึก (แบบเปล่าสำหรับกรอกด้วยมือ)' }}</span>
    <button onclick="print()" style="margin-left:auto">🖨 พิมพ์ / บันทึก PDF</button>
    <a href="{{ route('care.visits.form', $student) }}">กลับไปแก้ไข</a>
    <span class="hint">A4 · ขนาดจริง 100% · ปิดหัว/ท้ายกระดาษ</span>
</div>

{{-- ================= หน้า 1 ================= --}}
<div class="page">
    <div class="head"><h1>บันทึกการเยี่ยมบ้าน</h1><span>หน้า 1/4</span></div>
    <b class="t">คำชี้แจง :</b>
    <div class="in1">• แบบบันทึกการเยี่ยมบ้านฉบับนี้รวมการคัดกรองนักเรียนยากจนเข้าด้วยกัน เพื่อให้คุณครูสามารถลงพื้นที่ได้พร้อมกันในครั้งเดียว</div>
    <div class="in1">• การตอบแบบสอบถาม : หากเป็นตัวเลือก ○ หมายถึง ให้ตอบเพียงข้อเดียว และ หากเป็นตัวเลือก ☐ หมายถึง ให้ตอบได้มากกว่า 1 ข้อ</div>

    <div class="photo">@if ($student->photoUrl())<img src="{{ $student->photoUrl() }}" alt="">@else รูปถ่าย<br>นักเรียน @endif</div>
    <div class="line sec">โรงเรียน<span class="fill">{{ school('school_name') }}</span>สพป./สพม.<span class="fill">{{ school('school_affiliation') }}</span></div>
    <div class="line sec">1.&nbsp; ชื่อนักเรียน<span class="fill">{{ $student->prefix }}{{ $student->first_name }}</span>นามสกุล<span class="fill">{{ $student->last_name }}</span>ชั้น<span class="fill">{{ $student->classroom?->name() }}</span></div>
    <div class="in1">เลขที่บัตรประชาชน {!! $idBoxes($student->citizen_id) !!}</div>
    <div class="line sec" style="clear:both">2.&nbsp; ชื่อผู้ปกครองนักเรียน<span class="fill">{{ $v('guardian_first') }}</span>นามสกุล<span class="fill">{{ $v('guardian_last') }}</span>เบอร์โทรศัพท์<span class="fill">{{ $v('guardian_phone') }}</span>{!! $flag('no_guardian', 'ไม่มีผู้ปกครอง') !!}</div>
    <div class="line in1">ความสัมพันธ์ของผู้ปกครองกับนักเรียน<span class="fill">{{ $v('guardian_relation') }}</span>อาชีพ<span class="fill">{{ $v('guardian_occupation') }}</span>การศึกษาสูงสุด<span class="fill">{{ $v('guardian_education') }}</span></div>
    <div class="in1">เลขที่บัตรประชาชน {!! $idBoxes($v('guardian_citizen_id')) !!} &nbsp; {!! $flag('no_id_card', 'ไม่มีบัตรประจำตัวประชาชน') !!}</div>
    <div class="in2">{!! $flag('welfare_registered', 'เคยลงทะเบียนเพื่อสวัสดิการแห่งรัฐ (ลงทะเบียนคนจน)') !!}</div>
    <div class="line sec">3.&nbsp; จำนวนสมาชิกในครัวเรือน (รวมตัวนักเรียน)<span class="fill fix" style="width:22mm">{{ $num($v('member_count')) }}</span>คน มีรายละเอียดดังนี้ (กรอกเฉพาะนักเรียนยากจนเท่านั้น)</div>

    <table class="members">
        <thead>
            <tr>
                <th rowspan="2" style="width:8mm">คนที่</th><th rowspan="2" style="width:22mm">ความสัมพันธ์<br>กับนักเรียน</th><th rowspan="2" style="width:9mm">อายุ</th>
                <th rowspan="2" style="width:20mm">ความพิการทางร่างกาย/สติปัญญา<br>(ใส่เครื่องหมาย ✓ หรือ –)</th>
                <th colspan="5">รายได้เฉลี่ยต่อเดือนแยกตามประเภท (บาท/เดือน)</th>
                <th rowspan="2" style="width:19mm">รายได้รวมเฉลี่ยต่อเดือน</th>
            </tr>
            <tr>
                <th>ค่าจ้าง<br>เงินเดือน</th><th>ประกอบอาชีพทางการเกษตร<br>(หลังหักค่าใช้จ่าย)</th><th>ธุรกิจส่วนตัว<br>(หลังหักค่าใช้จ่าย)</th>
                <th style="width:32mm">สวัสดิการจากรัฐ/เอกชน (เงินบำนาญ, เบี้ยผู้สูงอายุ, อุดหนุนเด็กแรกเกิด, อุดหนุนคนพิการ, อื่นๆ)</th>
                <th>รายได้จากแหล่งอื่น (เงินโอน, ค่าเช่า, ดอกเบี้ย, อื่นๆ)</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($members as $i => $m)
            <tr>
                <td class="c">{{ $i + 1 }}</td><td class="l">{{ $m['relation'] ?? '' }}</td><td style="text-align:center">{{ $m['age'] ?? '' }}</td>
                <td style="text-align:center">{{ $m ? (($m['disabled'] ?? false) ? '✓' : '–') : '' }}</td>
                @foreach (array_keys($F::INCOME) as $type)<td>{{ $num($m[$type] ?? null) }}</td>@endforeach
                <td>{{ $m ? $num($m['total'] ?? null) : '' }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="9">รวมรายได้ครัวเรือน (รายการที่ 1 - 10)</td><td>{{ $num($form['household_income'] ?? null) }}</td></tr>
            <tr><td colspan="9">รายได้ครัวเรือนเฉลี่ยต่อคน (รวมรายได้ครัวเรือน หารด้วยจำนวนสมาชิกทั้งหมด จากข้อ 2)</td><td>{{ $num($form['household_income_per_head'] ?? null) }}</td></tr>
        </tfoot>
    </table>

    <div class="sec in1">4.&nbsp; สถานะของครัวเรือน กรอกเฉพาะบุคคลที่อาศัยในบ้านปัจจุบัน</div>
    <div class="in2">
        <div class="grid" style="grid-template-columns:44mm 1fr 1.6fr"><span>4.1. ครัวเรือนมีภาระพึ่งพิง ดังนี้</span>{!! $ck('dependents', 'disabled') !!}{!! $ck('dependents', 'elderly') !!}<span></span>{!! $ck('dependents', 'single_parent') !!}{!! $ck('dependents', 'unemployed') !!}</div>
        <div class="grid" style="grid-template-columns:44mm 1fr 1fr 1fr"><span>4.2. ประเภทที่อยู่อาศัย ดังนี้</span>{!! $rd('housing_type', 'own') !!}{!! $rd('housing_type', 'rent') !!}{!! $rd('housing_type', 'with_others') !!}</div>
        <div class="grid" style="grid-template-columns:44mm 1fr"><span>4.3 สภาพที่อยู่อาศัย ดังนี้</span>{!! $ck('house_condition', 'dilapidated') !!}<span></span>{!! $ck('house_condition', 'no_toilet') !!}</div>
        <div>4.4 ยานพาหนะของครอบครัว</div>
        @foreach (['vehicle_car' => 'รถยนต์ส่วนบุคคล', 'vehicle_pickup' => 'รถปิกอัพ/รถบรรทุกเล็ก/รถตู้', 'vehicle_tractor' => 'รถไถ/เกี่ยวข้าว/รถอีแต๋น/รถอื่นๆ ประเภทเดียวกัน'] as $k => $label)
            <div class="grid" style="grid-template-columns:80mm 1fr 1fr;padding-left:8mm"><span>- {{ $label }}</span>{!! $rd($k, 'yes') !!}{!! $rd($k, 'no') !!}</div>
        @endforeach
        <div class="grid" style="grid-template-columns:88mm 1fr 1fr"><span>4.5 เป็นเกษตรกร มีที่ดินทำกิน (รวมเช่า)</span>{!! $ck('farmland', 'under_one_rai') !!}{!! $ck('farmland', 'no_own_land') !!}</div>
    </div>
</div>

{{-- ================= หน้า 2 ================= --}}
<div class="page">
    <div class="head"><h1>บันทึกการเยี่ยมบ้าน</h1><span>หน้า 2/4</span></div>
    <div class="in1">5.&nbsp; ความสัมพันธ์ในครอบครัว</div>
    <div class="in2">
        <div class="line">5.1. สมาชิกในครอบครัวมีเวลาอยู่ร่วมกันกี่ชั่วโมงต่อวัน<span class="fill fix" style="width:35mm">{{ $num($v('hours_together')) }}</span>ชั่วโมง/วัน</div>
        <div>5.2. ความสัมพันธ์ระหว่างนักเรียนกับสมาชิกในครอบครัว</div>
        <table class="close">
            <thead><tr><th style="width:38%">สมาชิก</th>@foreach ($F::CLOSENESS as $label)<th>{{ str_replace(' ', '', $label) }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach ($F::RELATIVES as $rel => $label)
                <tr>
                    <td style="padding-left:6mm">{{ $rel === 'other' ? 'อื่นๆ '.($v('relation_other_name') ?: '.....................................') : $label }}</td>
                    @foreach (array_keys($F::CLOSENESS) as $c)<td class="c">{{ ($form['closeness'][$rel] ?? null) === $c ? '✓' : '' }}</td>@endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="sec">5.3. กรณีที่ผู้ปกครองไม่อยู่บ้านฝากเด็กนักเรียนอยู่บ้านกับใคร (ตอบเพียง 1 ข้อ)</div>
        <div class="line" style="padding-left:8mm;gap:0 10mm">{!! $rd('left_with', 'relative') !!}{!! $rd('left_with', 'neighbor') !!}{!! $rd('left_with', 'alone') !!}<span class="line" style="flex:1">{!! $rd('left_with', 'other') !!} ระบุ<span class="fill">{{ $v('left_with_other') }}</span></span></div>
        <div class="line sec">5.4. รายได้ครัวเรือนเฉลี่ยต่อคน (รวมรายได้ครัวเรือน หารด้วยจำนวนสมาชิกทั้งหมด)<span class="fill fix" style="width:22mm">{{ $num($v('income_per_head')) }}</span>บาท (กรอกเฉพาะกรณีนักเรียนไม่ยากจนเท่านั้น)</div>
        <div class="line">5.5. นักเรียนได้รับค่าใช้จ่ายจาก<span class="fill">{{ $v('expense_from') }}</span>&nbsp; {!! $flag('student_works', 'นักเรียนทำงานหารายได้', '') !!} อาชีพ<span class="fill">{{ $v('student_job') }}</span></div>
        <div class="line" style="padding-left:7mm">รายได้วันละ<span class="fill">{{ $num($v('student_income')) }}</span>บาท &nbsp; นักเรียนได้เงินมาโรงเรียนวันละ<span class="fill">{{ $num($v('allowance')) }}</span>บาท</div>
        <div>5.6. สิ่งที่ผู้ปกครองต้องการให้โรงเรียนช่วยเหลือนักเรียน</div>
        <div class="line" style="padding-left:8mm;gap:0 6mm">{!! $ck('help_wanted', 'learning') !!}{!! $ck('help_wanted', 'behavior') !!}{!! $ck('help_wanted', 'economic') !!}<span class="line" style="flex:1">{!! $ck('help_wanted', 'other') !!} ระบุ<span class="fill">{{ $v('help_wanted_other') }}</span></span></div>
        <div>5.7. ความช่วยเหลือที่ครอบครัวเคยได้รับจากหน่วยงานหรือต้องการได้รับการช่วยเหลือ</div>
        <div class="line" style="padding-left:8mm;gap:0 10mm">{!! $ck('assistance', 'elderly') !!}{!! $ck('assistance', 'disability') !!}<span class="line" style="flex:1">{!! $ck('assistance', 'other') !!} ระบุ<span class="fill">{{ $v('assistance_other') }}</span></span></div>
        <div>5.8. ข้อห่วงใยของผู้ปกครองที่มีต่อนักเรียน</div>
        @php $concernLines = array_pad(array_slice(preg_split('/\R/u', wordwrap($v('concerns'), 330, "\n", true)), 0, 3), 3, ''); @endphp
        @foreach ($concernLines as $l)<div class="dots">{{ $l }}</div>@endforeach
    </div>

    <div class="in1" style="margin-top:6mm">6.&nbsp; พฤติกรรมและความเสี่ยง</div>
    <div class="in2">
        <div>6.1. สุขภาพ</div>
        <div class="grid g3">@foreach (array_keys($F::MULTI['health']) as $o){!! $ck('health', $o) !!}@endforeach</div>
        <div style="margin-top:4mm">6.2. สวัสดิการหรือความปลอดภัย</div>
        <div class="grid g2">@foreach (array_keys($F::MULTI['safety']) as $o){!! $ck('safety', $o) !!}@endforeach</div>
        <div class="line" style="margin-top:4mm">6.3. ระยะทางระหว่างบ้านไปโรงเรียน (ไป/กลับ)<span class="fill fix" style="width:22mm">{{ $num($v('distance_km')) }}</span>กิโลเมตร ใช้เวลาเดินทาง<span class="fill fix" style="width:16mm">{{ $num($v('travel_hours')) }}</span>ชม.<span class="fill fix" style="width:16mm">{{ $num($v('travel_minutes')) }}</span>นาที</div>
        <div style="padding-left:7mm">การเดินทางของนักเรียนไปโรงเรียน (ตอบเพียง 1 ข้อ)</div>
        <div class="grid g4" style="padding-left:10mm">
            @foreach (['parent', 'bus', 'motorcycle', 'school_bus', 'car', 'bicycle', 'walk'] as $o){!! $rd('transport', $o) !!}@endforeach
            <span class="line">{!! $rd('transport', 'other') !!}<span class="fill">{{ $v('transport_other') }}</span></span>
        </div>
    </div>
</div>

{{-- ================= หน้า 3 ================= --}}
<div class="page tight">
    <div class="head"><h1>บันทึกการเยี่ยมบ้าน</h1><span>หน้า 3/4</span></div>
    <div class="in2">
        <div>6.4. ภาระงานความรับผิดชอบของนักเรียนที่มีต่อครอบครัว</div>
        <div class="grid g2" style="padding-left:6mm">
            @foreach (['housework', 'caregiving', 'trading', 'nearby_work', 'farm'] as $o){!! $ck('duties', $o) !!}@endforeach
            <span class="line">{!! $ck('duties', 'other') !!} ระบุ<span class="fill">{{ $v('duties_other') }}</span></span>
        </div>
        <div class="sec">6.5. กิจกรรมยามว่างหรืองานอดิเรก</div>
        <div class="grid g2" style="padding-left:6mm">
            @foreach (['tv_music', 'mall', 'reading', 'friends', 'racing', 'games', 'park', 'snooker'] as $o){!! $ck('hobbies', $o) !!}@endforeach
            <span class="line">{!! $ck('hobbies', 'other') !!} ระบุ<span class="fill">{{ $v('hobbies_other') }}</span></span>
        </div>
        @foreach (['substance' => ['6.6. พฤติกรรมการใช้สารเสพติด', 'g2'], 'violence' => ['6.7. พฤติกรรมการใช้ความรุนแรง', 'g3'], 'sexual' => ['6.8. พฤติกรรมทางเพศ', 'g2'], 'game' => ['6.9. การติดเกม', 'g3']] as $key => [$title, $cols])
            <div class="sec">{{ $title }}</div>
            <div class="grid {{ $cols }}">
                @foreach (array_keys($F::MULTI[$key]) as $o)
                    @if ($key === 'game' && $o === 'other')<span class="line">{!! $ck('game', 'other') !!}<span class="fill">{{ $v('game_other') }}</span></span>@else{!! $ck($key, $o) !!}@endif
                @endforeach
            </div>
        @endforeach
        <div class="sec">6.10. การเข้าถึงสื่อคอมพิวเตอร์และอินเตอร์เน็ตที่บ้าน</div>
        <div class="grid g2">{!! $rd('internet', 'yes') !!}{!! $rd('internet', 'no') !!}</div>
        <div class="sec">6.11. การใช้เครื่องมือสื่อสารอิเล็กทรอนิกส์</div>
        @foreach (array_keys($F::MULTI['devices']) as $o)<div>{!! $ck('devices', $o) !!}</div>@endforeach
    </div>

    <div style="margin-top:3mm"><b class="t">ผู้ให้ข้อมูลนักเรียน</b></div>
    <div class="grid g6" style="padding-left:6mm">@foreach (array_keys($F::SINGLE['informant']) as $o){!! $rd('informant', $o) !!}@endforeach</div>

    <div class="sign">
        <div style="text-align:center">ขอรับรองว่าข้อมูลดังกล่าวเป็นจริง</div>
        <div class="line" style="margin-top:3mm">ลงชื่อผู้ปกครอง/ผู้แทน<span class="fill signed">@if ($visit->sign_guardian)<img src="{{ route('files.show', ['home-visit-sign-guardian', $visit->id]) }}" alt="ลายเซ็นผู้ปกครอง">@endif</span></div>
        <div class="line">(<span class="fill" style="text-align:center">{{ $guardianName }}</span>)</div>
    </div>
</div>

{{-- ================= หน้า 4 ================= --}}
<div class="page">
    <div class="head"><h1>บันทึกการเยี่ยมบ้าน</h1><span>หน้า 4/4</span></div>
    <h2>ภาพถ่ายบ้านนักเรียนที่ได้รับการเยี่ยมบ้าน</h2>
    <div class="line"><b class="t">ชื่อ - นามสกุลนักเรียน</b><span class="fill" style="max-width:120mm">{{ $student->fullName() }}</span></div>
    <div class="grid" style="grid-template-columns:47mm 1fr;margin-top:1mm">
        <span>กรุณาระบุ ภาพถ่ายที่แนบมาคือ</span>
        <div>@foreach (array_keys($F::SINGLE['photo_kind']) as $o)<div><span class="ck{{ ($form['photo_kind'] ?? null) === $o ? ' on' : '' }}">{{ $F::SINGLE['photo_kind'][$o] }}</span></div>@endforeach</div>
    </div>

    @foreach (['photo' => ['รูปที่ 1 ภาพถ่ายสภาพบ้านนักเรียน', 'home-visit', 'มีหลังคาและฝาบ้านด้วย'], 'photo_inside' => ['รูปที่ 2 ภาพถ่ายภายในบ้านนักเรียน', 'home-visit-inside', '']] as $field => [$label, $type, $hint])
        <div style="text-align:center;font-weight:700;margin:4mm 0 2mm">{{ $label }}</div>
        <div class="shot">@if ($visit->{$field})<img src="{{ route('files.show', [$type, $visit->id]) }}" alt="{{ $label }}">@else{{ $hint }}@endif</div>
    @endforeach

    <div class="certify">
        <b class="t">ขอรับรองว่าข้อมูล และภาพถ่ายบ้านของนักเรียนเป็นความจริง</b>
        <div class="inner">
            <div class="line">(ลงชื่อ)<span class="fill signed">@if ($visit->sign_visitor)<img src="{{ route('files.show', ['home-visit-sign-visitor', $visit->id]) }}" alt="ลายเซ็นผู้เยี่ยมบ้าน">@endif</span></div>
            <div class="line">(<span class="fill" style="text-align:center">{{ $visit->visitor?->name }}</span>)</div>
            <div class="line">ตำแหน่ง<span class="fill" style="text-align:center">{{ $v('visitor_position') }}</span>(ครูหรือผู้อำนวยการโรงเรียน)</div>
            <div class="line">วันที่<span class="fill fix" style="width:16mm">{{ $on?->day }}</span>เดือน<span class="fill fix" style="width:30mm">{{ $on ? $months[$on->month - 1] : '' }}</span>พ.ศ.<span class="fill fix" style="width:18mm">{{ $on ? $on->year + 543 : '' }}</span></div>
        </div>
    </div>
</div>
</body>
</html>
