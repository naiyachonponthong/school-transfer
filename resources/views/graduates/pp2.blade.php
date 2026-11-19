<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ปพ.2 ประกาศนียบัตร · {{ school('school_name') }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style id="theme-vars">:root{ {!! \App\Support\Theme::css() !!} }</style>
<style>
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body { font-family: 'Sarabun', sans-serif; background: #e5e7eb; color: #111; }
.bar { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; padding: .7rem 1rem; background: #fff; box-shadow: 0 2px 8px rgba(16,24,40,.08); font-size: 14px; }
.bar a, .bar button { display: inline-flex; align-items: center; gap: .35rem; padding: .45rem .9rem; border-radius: 10px; border: 1px solid #d1d5db; background: #fff; color: #1f2937; text-decoration: none; font: inherit; cursor: pointer; }
.bar .on, .bar .print { background: var(--sb-primary, #F26522); border-color: var(--sb-primary, #F26522); color: #fff; }
.bar .print { margin-left: auto; font-weight: 600; }
.bar .muted { color: #6b7280; font-size: 13px; }
.note { max-width: 297mm; margin: .8rem auto 0; padding: .7rem 1rem; border-radius: 12px; font-size: 14px; background: #eff6ff; color: #1e3a8a; }
.diploma-wrap { padding: 1rem; overflow-x: auto; }
/* วางข้อความตามแบบพิมพ์ ปพ.2 ของกระทรวง (A4 แนวนอน) · ทุกตำแหน่งเป็น มม. เลื่อนทั้งแผ่นได้ด้วย --dx / --dy */
.diploma { width: 297mm; height: 210mm; margin: 0 auto 1rem; background: #fff; position: relative; box-shadow: 0 2px 12px rgba(16,24,40,.08); overflow: hidden; font-size: 16pt; color: #111; }
.diploma-inner { position: absolute; inset: 0; transform: translate(var(--dx, 0mm), var(--dy, 0mm)); }
.diploma.full .diploma-inner::before { content: ""; position: absolute; inset: 6mm; border: 1.4mm double #6b7280; }
.dp { position: absolute; white-space: nowrap; line-height: 1; }
/* ตำแหน่งวัดจากแบบพิมพ์ ปพ.2 ตัวอย่าง: top = เส้นฐานโดยประมาณของบรรทัด (มม. จากขอบกระดาษ) */
.dp-code { top: 12mm; right: 15mm; text-align: left; font-size: 13pt; line-height: 1.45; }
.dp-code .num { display: inline-block; min-width: 12mm; text-align: center; }
.dp-emblem { top: 11mm; left: 16mm; width: 36mm; height: 44mm; border: 1px dashed #9ca3af; display: grid; place-items: center; font-size: 10pt; color: #9ca3af; text-align: center; white-space: normal; line-height: 1.4; }
.dp-head { top: 20.5mm; left: 0; right: 0; text-align: center; font-size: 24pt; }
.dp-sub { top: 34.5mm; left: 0; right: 0; text-align: center; font-size: 17pt; }
.dp-name { top: 49.5mm; left: 0; right: 0; text-align: center; font-size: 18pt; font-weight: 700; }
.dp-l, .dp-v { font-size: 16pt; }
.dp-v { text-align: center; }
.dp-v.left { text-align: left; padding-left: 2mm; }
.diploma.full .dp-v { border-bottom: 1px dotted #6b7280; padding-bottom: .6mm; }
.dp-bless { top: 131mm; left: 0; right: 0; text-align: center; font-size: 16pt; }
.dp-sign { top: 158mm; left: 0; right: 0; text-align: center; font-size: 15pt; line-height: 1.55; }
/* พิมพ์ลงแบบฟอร์มจริง: ซ่อนทุกอย่างที่พิมพ์มาแล้วบนแบบ เหลือเฉพาะข้อมูล */
.diploma.overlay .static { visibility: hidden; }
.diploma.overlay .dp-emblem { display: none; }
.diploma.overlay { box-shadow: 0 0 0 1px #d1d5db inset; }
@media print {
    @page { size: A4 landscape; margin: 0; }
    .diploma { margin: 0; box-shadow: none !important; break-after: page; }
    .diploma:last-of-type { break-after: auto; }
    .no-print-box { display: none; }
}
.dp-emblem img { width: 100%; height: 100%; object-fit: contain; }
.dp-emblem.has-img { border: 0; }
@media print {
    body { background: #fff; }
    .bar, .note { display: none !important; }
    .diploma-wrap { padding: 0; overflow: visible; }
    /* สูงน้อยกว่า A4 เล็กน้อย กันปัดเศษแล้วล้นเป็นหน้าว่างเพิ่ม */
    .diploma { margin: 0; height: 209.5mm; box-shadow: none !important; break-after: page; page-break-after: always; }
    .diploma:last-child { break-after: auto; page-break-after: auto; }
}
</style>
</head>
<body>
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
<div class="bar">
    <a href="{{ url()->previous() }}"><i class="bi bi-arrow-left"></i> กลับ</a>
    <a href="{{ request()->fullUrlWithQuery(['mode' => null]) }}" class="{{ $overlay ? '' : 'on' }}">ฉบับตรวจสอบ (เต็มแบบ)</a>
    <a href="{{ request()->fullUrlWithQuery(['mode' => 'overlay']) }}" class="{{ $overlay ? 'on' : '' }}">พิมพ์ลงแบบฟอร์มจริง</a>
    <span class="muted">{{ $issues->count() }} ฉบับ · ตั้งเครื่องพิมพ์: A4 แนวนอน · ขนาด 100% (ไม่ย่อ) · ขอบกระดาษ: ไม่มี · ปิด "ส่วนหัวและส่วนท้าย"</span>
    <button type="button" class="print" onclick="print()"><i class="bi bi-printer"></i> พิมพ์</button>
</div>
@if ($overlay)
    <div class="note">พิมพ์เฉพาะข้อมูลของนักเรียนลงบนแบบพิมพ์ ปพ.2 ครั้งแรกให้พิมพ์ลงกระดาษเปล่าหนึ่งแผ่น ซ้อนกับแบบพิมพ์แล้วส่องกับไฟ ถ้าไม่ตรงเส้นประทั้งแผ่น เลื่อนได้ที่ ตั้งค่า &gt; ข้อมูลโรงเรียน (ตอนนี้ ซ้าย/ขวา {{ $dx }} มม. บน/ล่าง {{ $dy }} มม.)</div>
@else
    <div class="note">ฉบับตรวจสอบ ใช้ดูความถูกต้องของข้อมูล หรือพิมพ์ลงกระดาษเปล่า ตราครุฑอัปโหลดได้ที่ ตั้งค่า &gt; ข้อมูลโรงเรียน &gt; ตำแหน่งพิมพ์ ปพ.2</div>
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
            <div class="dp dp-code"><span class="static">ปพ.๒ : {{ $letter[$stage] ?? 'บ' }}</span><br><span class="static">เลขที่</span> <span class="num">{{ $th($i->number) }}</span></div>
            @if ($emblem = school('pp2_emblem'))<div class="dp dp-emblem has-img static"><img src="{{ asset('storage/'.$emblem) }}" alt="ตราครุฑ"></div>@else<div class="dp dp-emblem no-print-box">ตำแหน่งตราครุฑ<br>(อัปโหลดได้ที่ตั้งค่า)</div>@endif
            <div class="dp dp-head static">กระทรวงศึกษาธิการ</div>
            <div class="dp dp-sub static">ประกาศนียบัตรฉบับนี้ให้ไว้เพื่อแสดงว่า</div>
            <div class="dp dp-name">{{ $s['name'] }}</div>

            @php
                // [ชื่อ, ค่า, ซ้าย(มม.), กว้าง(มม.), ชิดซ้าย] ของแต่ละช่อง แถวละบรรทัด (top มม.)
                $line = fn ($top, $items) => [$top, $items];
                $lines = [
                    $line(65.5, [['เกิดวันที่', 42], [$bd, 64, 28], ['เดือน', 94], [$bm, 106, 44], ['พ.ศ.', 152], [$by, 162, 30]]),
                    $line(91, [['จาก', 42], [school('school_name'), 52, 180, true]]),
                    $line(104.5, [['จังหวัด', 42], [school('school_province'), 57, 56, true], ['สังกัด', 115], [school('school_affiliation'), 129, 103, true]]),
                    $line(118, [['เมื่อวันที่', 42], [$gd, 64, 28], ['เดือน', 94], [$gm, 106, 44], ['พ.ศ.', 152], [$gy, 162, 30]]),
                ];
            @endphp
            @foreach ($lines as [$top, $items])
                @foreach ($items as $it)
                    @if (count($it) === 2)
                        <div class="dp dp-l static" style="top: {{ $top }}mm; left: {{ $it[1] }}mm">{{ $it[0] }}</div>
                    @else
                        <div class="dp dp-v {{ ! empty($it[3]) ? 'left' : '' }}" style="top: {{ $top }}mm; left: {{ $it[1] }}mm; width: {{ $it[2] }}mm">{{ $it[0] }}&nbsp;</div>
                    @endif
                @endforeach
            @endforeach
            <div class="dp dp-l static" style="top: 78mm; left: 42mm">{{ $finish[$stage] ?? $finish['b'] }}</div>
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
</body>
</html>
