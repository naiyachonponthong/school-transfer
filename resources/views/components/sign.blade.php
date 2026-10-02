@props(['role', 'name' => null])
{{-- ช่องลงนามในเอกสาร ปพ. · ไม่มีชื่อ = เว้นเส้นประให้เขียนเอง --}}
<div {{ $attributes->merge(['class' => 'text-center small']) }}>
    <div>ลงชื่อ ...........................................</div>
    <div class="mt-1">( {{ filled($name) ? $name : '...........................................' }} )</div>
    <div class="text-muted">{{ $role }}</div>
    {{ $slot }}
</div>
