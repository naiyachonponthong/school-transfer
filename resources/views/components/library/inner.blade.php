@props(['copy'])
{{-- บาร์โค้ดปกใน 7 × 4 ซม.: บาร์โค้ด + ชื่อเรื่อง + เลขทะเบียน + เลขเรียก + ราคา (ติดหน้าปกใน/หน้าลิขสิทธิ์) --}}
@php($b = $copy->book)
<div {{ $attributes->merge(['class' => 'lbl-inner']) }}>
    <div class="head"><span>ห้องสมุด{{ school('school_name') }}</span><span>ฉ.{{ $copy->copy_no }}</span></div>
    <div class="title">{{ $b->title }}{{ $b->volume ? ' ล.'.preg_replace('/^ล\.?\s*/u', '', $b->volume) : '' }}</div>
    {!! \App\Support\Barcode::svg($copy->barcode) !!}
    <div class="code">{{ $copy->barcode }}</div>
    <div class="meta">
        <span>เลขทะเบียน</span><b>{{ $copy->accession_no ?? '-' }}</b>
        <span>เลขเรียก</span><b>{{ $b->callNumberText($copy) ?: '-' }}</b>
        <span>ราคา</span><b>{{ $copy->price ? number_format($copy->price, 2) : '-' }}</b>
        <span>รับเข้า</span><b>{{ $copy->acquired_on ? thai_date($copy->acquired_on) : '-' }}</b>
    </div>
</div>
