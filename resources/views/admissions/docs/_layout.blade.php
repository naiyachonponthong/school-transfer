<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} {{ $a->app_no }} · {{ school('school_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* เอกสาร A4 — ช่องที่ระบบมีข้อมูลพิมพ์ให้ (สีน้ำเงินเข้ม) ช่องที่ไม่มีเว้นเส้นประให้กรอกด้วยมือ */
        @page { size: A4; margin: 12mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e9ebef; font: 15px/1.9 'Sarabun', sans-serif; color: #111; }
        .toolbar { position: sticky; top: 0; z-index: 5; background: #1f2937; color: #fff; padding: .6rem 1rem; display: flex; gap: .6rem; align-items: center; flex-wrap: wrap; }
        .toolbar button, .toolbar a { background: #fff; color: #111; border: 0; border-radius: 8px; padding: .4rem .9rem; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .toolbar .hint { font-size: .82rem; opacity: .8; }
        .page { width: 210mm; min-height: 297mm; margin: 1rem auto; background: #fff; padding: 12mm 14mm; box-shadow: 0 4px 20px rgba(0,0,0,.12); position: relative; }
        .page + .page { page-break-before: always; }
        .center { text-align: center; }
        .right { text-align: right; }
        h1 { font-size: 1.35rem; margin: 0; line-height: 1.5; }
        h2 { font-size: 1rem; margin: .6rem 0 .1rem; text-decoration: underline; }
        .muted { color: #555; }
        .small { font-size: .85rem; }
        .logo { height: 72px; }
        .photo { width: 30mm; height: 38mm; border: 1px solid #333; display: flex; align-items: center; justify-content: center; text-align: center; font-size: .8rem; color: #555; overflow: hidden; }
        .photo img { width: 100%; height: 100%; object-fit: cover; }
        .fill { display: inline-block; min-width: 20mm; border-bottom: 1px dotted #222; padding: 0 .35rem; line-height: 1.5; text-align: center; color: #1e3a8a; font-weight: 600; vertical-align: baseline; }
        .fill.w { min-width: 60mm; } .fill.xl { min-width: 110mm; } .fill.s { min-width: 12mm; } .fill.left { text-align: left; }
        .line { display: flex; flex-wrap: wrap; align-items: baseline; gap: .2rem .45rem; }
        .line .grow { flex: 1; min-width: 30mm; }
        .box { display: inline-block; border: 1.2px solid #111; border-radius: 3px; padding: 0 .3rem; }
        .idboxes { display: inline-flex; align-items: center; gap: 2px; vertical-align: middle; }
        .idboxes span { width: 6.2mm; height: 7.2mm; border: 1.2px solid #111; display: inline-flex; align-items: center; justify-content: center; color: #1e3a8a; font-weight: 700; }
        .idboxes i { width: 2mm; border-top: 1.2px solid #111; }
        .check { display: inline-flex; align-items: center; gap: .25rem; margin-right: .8rem; }
        .check::before { content: ''; width: 4mm; height: 4mm; border: 1.2px solid #111; display: inline-block; }
        .check.on::before { content: '✓'; color: #1e3a8a; font-weight: 700; line-height: 3.6mm; text-align: center; }
        .radio::before { border-radius: 50%; }
        .sign { display: inline-block; text-align: center; min-width: 75mm; margin-top: .8rem; }
        .cut { border-top: 1.5px dashed #444; margin: 1rem -14mm .8rem; position: relative; }
        .cut::after { content: '✂ ตัดตามรอยประ'; position: absolute; left: 14mm; top: -.8rem; background: #fff; padding: 0 .4rem; font-size: .75rem; color: #444; }
        table.items { width: 100%; border-collapse: collapse; margin: .6rem 0; }
        table.items th, table.items td { border: 1px solid #333; padding: .3rem .6rem; }
        table.items th { background: #f1f1f1; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { margin: 0; box-shadow: none; width: auto; min-height: auto; padding: 0; }
        }
        @media screen and (max-width: 820px) { .page { width: auto; min-height: 0; margin: .5rem; padding: 6mm; } }
    </style>
</head>
<body>
<div class="toolbar">
    <b>{{ $title }}</b> <span class="hint">เลขที่ใบสมัคร {{ $a->app_no }}</span>
    <button onclick="print()" style="margin-left:auto">🖨 พิมพ์ / บันทึก PDF</button>
    <span class="hint">ตั้งค่าเครื่องพิมพ์: A4 · ขนาดจริง 100% · ปิดหัว/ท้ายกระดาษ</span>
</div>
@yield('doc')
</body>
</html>
