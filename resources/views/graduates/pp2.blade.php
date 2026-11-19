@extends('layouts.app')
@section('title', 'ปพ.2 ประกาศนียบัตร')

@push('head')
<style>
/* วางข้อความตามแบบพิมพ์ ปพ.2 ของกระทรวง (A4 แนวนอน) · ทุกตำแหน่งเป็น มม. เลื่อนทั้งแผ่นได้ด้วย --dx / --dy */
.diploma { width: 297mm; height: 210mm; margin: 0 auto 1.5rem; background: #fff; position: relative; box-shadow: 0 2px 12px rgba(16,24,40,.08); overflow: hidden; font-size: 16pt; color: #111; }
.diploma-inner { position: absolute; inset: 0; transform: translate(var(--dx, 0mm), var(--dy, 0mm)); }
.diploma.full .diploma-inner::before { content: ""; position: absolute; inset: 9mm; border: 1.6mm double #6b7280; }
.dp { position: absolute; white-space: nowrap; line-height: 1.2; }
.dp-code { top: 16mm; right: 22mm; text-align: right; font-size: 13pt; line-height: 1.5; }
.dp-emblem { top: 17mm; left: 24mm; width: 34mm; height: 40mm; border: 1px dashed #9ca3af; display: grid; place-items: center; font-size: 10pt; color: #9ca3af; text-align: center; white-space: normal; }
.dp-head { top: 26mm; left: 0; right: 0; text-align: center; font-size: 25pt; }
.dp-sub { top: 40mm; left: 0; right: 0; text-align: center; font-size: 18pt; }
.dp-name { top: 53mm; left: 0; right: 0; text-align: center; font-size: 19pt; font-weight: 700; }
.dp-row { left: 86mm; width: 190mm; display: flex; align-items: baseline; gap: 2mm; font-size: 16pt; }
.dp-row .v { border-bottom: 1px dotted #6b7280; text-align: center; padding: 0 2mm; }
.dp-row .v.grow { flex: 1; text-align: left; }
.row-birth { top: 68mm; } .row-grad { top: 80mm; } .row-from { top: 92mm; } .row-prov { top: 104mm; } .row-date { top: 116mm; }
.dp-bless { top: 128mm; left: 0; right: 0; text-align: center; }
.dp-sign { top: 152mm; left: 0; right: 0; text-align: center; line-height: 1.6; font-size: 15pt; }
.w-day { width: 18mm; } .w-month { width: 46mm; } .w-year { width: 30mm; } .w-prov { width: 62mm; }
/* พิมพ์ลงแบบฟอร์มจริง: ซ่อนทุกอย่างที่พิมพ์มาแล้วบนแบบ เหลือเฉพาะข้อมูล */
.diploma.overlay .static { visibility: hidden; }
.diploma.overlay .dp-row .v { border-color: transparent; }
.diploma.overlay .dp-emblem { display: none; }
.diploma.overlay { box-shadow: 0 0 0 1px #d1d5db inset; }
@media print {
    @page { size: A4 landscape; margin: 0; }
    .diploma { margin: 0; box-shadow: none !important; break-after: page; }
    .diploma:last-of-type { break-after: auto; }
    .dp-emblem { display: none; }
}
.diploma-wrap { overflow-x: auto; }
</style>
@endpush

@section('content')
@php
    $th = fn ($v) => strtr((string) $v, ['0' => '๐', '1' => '๑', '2' => '๒', '3' => '๓', '4' => '๔', '5' => '๕', '6' => '๖', '7' => '๗', '8' => '๘', '9' => '๙']);
    $parts = function (?string $date) use ($th) {
        if (! $date) { return ['', '', '']; }
        $c = \Illuminate\Support\Carbon::parse($date);

        return [$th($c->day), \App\Support\Thai::MONTHS[$c->month], $th($c->year + 543)];
    };
    $letter = ['p' => 'ป', 'b' => 'บ', 'w' => 'พ'];
    // ข้อความตามแบบ ปพ.2 ของแต่ละระดับ
    $finish = ['p' => 'เป็นผู้สำเร็จการศึกษาระดับประถมศึกษาตามหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน',
        'b' => 'เป็นผู้สำเร็จการศึกษาภาคบังคับตามหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน',
        'w' => 'เป็นผู้สำเร็จการศึกษาขั้นพื้นฐานตามหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน'];
@endphp
<div class="d-flex gap-2 mb-3 no-print flex-wrap align-items-center">
    <a href="{{ url()->previous() }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    <div class="btn-group">
        <a href="{{ request()->fullUrlWithQuery(['mode' => null]) }}" class="btn btn-sm {{ $overlay ? 'btn-light border' : 'btn-primary' }}">ฉบับตรวจสอบ (เต็มแบบ)</a>
        <a href="{{ request()->fullUrlWithQuery(['mode' => 'overlay']) }}" class="btn btn-sm {{ $overlay ? 'btn-primary' : 'btn-light border' }}">พิมพ์ลงแบบฟอร์มจริง</a>
    </div>
    <span class="small text-muted">{{ $issues->count() }} ฉบับ · กระดาษ A4 แนวนอน ไม่ย่อขนาด (Scale 100%) ไม่มีขอบกระดาษ (Margins: None)</span>
    <button onclick="print()" class="btn btn-primary ms-auto"><i class="bi bi-printer"></i> พิมพ์</button>
</div>
@if ($overlay)
    <div class="alert alert-info small no-print d-flex gap-2"><i class="bi bi-info-circle-fill"></i><div>
        โหมดนี้พิมพ์เฉพาะข้อมูลของนักเรียนลงบนแบบพิมพ์ ปพ.2 ของกระทรวง ครั้งแรกให้พิมพ์ลงกระดาษเปล่าหนึ่งแผ่น ซ้อนกับแบบพิมพ์แล้วส่องกับไฟ
        ถ้าข้อความไม่ตรงเส้นประ เลื่อนตำแหน่งที่ ตั้งค่า &gt; ข้อมูลโรงเรียน (ตอนนี้ ซ้าย/ขวา {{ $dx }} มม. บน/ล่าง {{ $dy }} มม.)
    </div></div>
@else
    <div class="alert alert-light small no-print d-flex gap-2"><i class="bi bi-info-circle"></i><div>ฉบับตรวจสอบสำหรับดูความถูกต้องของข้อมูล ฉบับจริงต้องพิมพ์ลงแบบพิมพ์ ปพ.2 ของกระทรวงศึกษาธิการ (ตราครุฑพิมพ์มาบนแบบแล้ว ระบบไม่พิมพ์ตราครุฑ)</div></div>
@endif

<div class="diploma-wrap">
@foreach ($issues as $i)
    @php
        $s = $i->snapshot;
        $stage = $s['stage'] ?? 'b';
        [$bd, $bm, $by] = $parts($s['birthdate'] ?? null);
        [$gd, $gm, $gy] = $parts($s['graduated_on'] ?? null);
    @endphp
    <div class="diploma {{ $overlay ? 'overlay' : 'full' }}" style="--dx: {{ $dx }}mm; --dy: {{ $dy }}mm">
        <div class="diploma-inner">
            <div class="dp dp-code"><span class="static">ปพ.๒ : {{ $letter[$stage] ?? 'บ' }}</span><br><span class="static">เลขที่</span> {{ $th($i->number) }}</div>
            <div class="dp dp-emblem">ตำแหน่งตราครุฑ<br>(พิมพ์มาบนแบบ)</div>
            <div class="dp dp-head static">กระทรวงศึกษาธิการ</div>
            <div class="dp dp-sub static">ประกาศนียบัตรฉบับนี้ให้ไว้เพื่อแสดงว่า</div>
            <div class="dp dp-name">{{ $s['name'] }}</div>

            <div class="dp dp-row row-birth">
                <span class="static">เกิดวันที่</span><span class="v w-day">{{ $bd }}</span>
                <span class="static">เดือน</span><span class="v w-month">{{ $bm }}</span>
                <span class="static">พ.ศ.</span><span class="v w-year">{{ $by }}</span>
            </div>
            <div class="dp dp-row row-grad static">{{ $finish[$stage] ?? $finish['b'] }}</div>
            <div class="dp dp-row row-from"><span class="static">จาก</span><span class="v grow">{{ school('school_name') }}</span></div>
            <div class="dp dp-row row-prov">
                <span class="static">จังหวัด</span><span class="v w-prov">{{ school('school_province') }}</span>
                <span class="static">สังกัด</span><span class="v grow">{{ school('school_affiliation') }}</span>
            </div>
            <div class="dp dp-row row-date">
                <span class="static">เมื่อวันที่</span><span class="v w-day">{{ $gd }}</span>
                <span class="static">เดือน</span><span class="v w-month">{{ $gm }}</span>
                <span class="static">พ.ศ.</span><span class="v w-year">{{ $gy }}</span>
            </div>
            <div class="dp dp-bless static">ขอให้มีความสุขสวัสดิ์เจริญเทอญ</div>
            <div class="dp dp-sign">
                <span class="static">.................................................</span><br>
                @if (! empty($s['director']))( {{ $s['director'] }} )@elseif (! $overlay)( ............................................. )@endif<br>
                <span class="static">ผู้อำนวยการ</span>
            </div>
        </div>
    </div>
@endforeach
</div>
@endsection
