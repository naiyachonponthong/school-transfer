@props(['copy'])
{{-- บาร์โค้ดปกนอก 5 × 2.5 ซม. (ติดปกหลัง/ปกหน้า ใช้ยิงยืม-คืน) --}}
<div {{ $attributes->merge(['class' => 'lbl-outer']) }}>
    <div class="t">ห้องสมุด{{ school('school_name') }}</div>
    {!! \App\Support\Barcode::svg($copy->barcode) !!}
    <div class="code">{{ $copy->barcode }}</div>
</div>
