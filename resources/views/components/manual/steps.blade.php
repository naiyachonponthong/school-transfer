{{-- ขั้นตอนเรียงเลข (ใส่ <li> ข้างใน) --}}
<ol {{ $attributes->merge(['class' => 'manual-steps']) }}>{{ $slot }}</ol>
