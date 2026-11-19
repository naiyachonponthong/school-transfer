@extends('layouts.app')
@section('title', 'ปพ.2 ประกาศนียบัตร')

@push('head')
<style>
.diploma { width: 297mm; height: 210mm; margin: 0 auto 1.5rem; background: #fff; position: relative; box-shadow: 0 2px 12px rgba(16,24,40,.08); overflow: hidden; }
.diploma-inner { position: absolute; inset: 0; padding: 18mm 22mm; transform: translate(var(--dx, 0mm), var(--dy, 0mm)); text-align: center; font-size: 16pt; line-height: 1.9; }
.diploma.full .diploma-inner::before { content: ""; position: absolute; inset: 8mm; border: 2.5mm double #8a6d1f; border-radius: 4mm; }
.diploma .logo { height: 30mm; }
.diploma h1 { font-size: 30pt; font-weight: 700; margin: 2mm 0 0; letter-spacing: .02em; }
.diploma .no { position: absolute; top: 14mm; right: 26mm; font-size: 12pt; }
.diploma .photo { position: absolute; top: 40mm; left: 26mm; width: 25mm; height: 32mm; object-fit: cover; border: 1px solid #999; }
.diploma .name { font-size: 24pt; font-weight: 700; }
.diploma .sign { position: absolute; bottom: 18mm; right: 30mm; width: 95mm; font-size: 14pt; line-height: 1.6; }
/* overlay: พิมพ์เฉพาะข้อมูลลงแบบพิมพ์จริง ซ่อนข้อความคงที่ของแบบ */
.diploma.overlay .static { visibility: hidden; }
.diploma.overlay { box-shadow: 0 0 0 1px #d1d5db inset; }
@media print {
    @page { size: A4 landscape; margin: 0; }
    .diploma { margin: 0; box-shadow: none !important; break-after: page; }
    .diploma:last-of-type { break-after: auto; }
}
@media (max-width: 1200px) { .diploma-wrap { overflow-x: auto; } }
</style>
@endpush

@section('content')
@php($blank = $overlay ? '' : '..............................')
@php($d = fn ($v) => $v ? thai_date($v, true) : $blank)
<div class="d-flex gap-2 mb-3 no-print flex-wrap align-items-center">
    <a href="{{ url()->previous() }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
    <div class="btn-group">
        <a href="{{ request()->fullUrlWithQuery(['mode' => null]) }}" class="btn btn-sm {{ $overlay ? 'btn-light border' : 'btn-primary' }}">ฉบับตรวจสอบ (เต็มแบบ)</a>
        <a href="{{ request()->fullUrlWithQuery(['mode' => 'overlay']) }}" class="btn btn-sm {{ $overlay ? 'btn-primary' : 'btn-light border' }}">พิมพ์ลงแบบฟอร์มจริง</a>
    </div>
    <span class="small text-muted">{{ $issues->count() }} ฉบับ · กระดาษ A4 แนวนอน ไม่ย่อขนาด (Scale 100%)</span>
    <button onclick="print()" class="btn btn-primary ms-auto"><i class="bi bi-printer"></i> พิมพ์</button>
</div>
@if ($overlay)
    <div class="alert alert-info small no-print d-flex gap-2"><i class="bi bi-info-circle-fill"></i><div>
        โหมดนี้พิมพ์เฉพาะข้อมูลของนักเรียน ใส่แบบพิมพ์ ปพ.2 ของกระทรวงในถาดแล้วพิมพ์ ถ้าข้อความไม่ตรงช่อง ลองพิมพ์ลงกระดาษเปล่าหนึ่งแผ่นซ้อนเทียบ
        แล้วเลื่อนตำแหน่งที่ ตั้งค่า &gt; ข้อมูลโรงเรียน (ตอนนี้ ซ้าย/ขวา {{ $dx }} มม. บน/ล่าง {{ $dy }} มม.)
    </div></div>
@endif

<div class="diploma-wrap">
@foreach ($issues as $i)
    @php($s = $i->snapshot)
    <div class="diploma {{ $overlay ? 'overlay' : 'full' }}" style="--dx: {{ $dx }}mm; --dy: {{ $dy }}mm">
        <div class="diploma-inner">
            <div class="no">เลขที่ {{ $i->code() }}</div>
            @if (! $overlay && school('logo'))<img class="logo static" src="{{ asset('storage/'.school('logo')) }}" alt="">@endif
            @if (! $overlay && ! empty($s['photo']))<img class="photo" src="{{ asset('storage/'.$s['photo']) }}" alt="">@endif
            <h1 class="static">ประกาศนียบัตร</h1>
            <div class="static">{{ school('school_name') }}</div>
            <div class="mt-2"><span class="static">ให้ไว้เพื่อแสดงว่า</span></div>
            <div class="name">{{ $s['name'] }}</div>
            <div><span class="static">เลขประจำตัวนักเรียน</span> {{ $s['student_code'] }} <span class="static">เลขประจำตัวประชาชน</span> {{ $s['citizen_id'] ?: $blank }}</div>
            <div><span class="static">เกิดวันที่</span> {{ $d($s['birthdate']) }}</div>
            <div><span class="static">ได้สำเร็จการศึกษาระดับ</span><b>{{ $s['stage_name'] }}</b> <span class="static">ตามหลักสูตรแกนกลางการศึกษาขั้นพื้นฐาน</span></div>
            <div><span class="static">เมื่อวันที่</span> {{ $d($s['graduated_on']) }}</div>
            <div class="sign">
                <span class="static">ลงชื่อ ...........................................................</span><br>
                @if ($s['director'] || ! $overlay)( {{ $s['director'] ?: '...........................................................' }} )@endif<br>
                <span class="static">ผู้อำนวยการ{{ school('school_name') }}</span>
            </div>
        </div>
    </div>
@endforeach
</div>
@endsection
