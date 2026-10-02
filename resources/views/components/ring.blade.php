@props(['value' => 0, 'size' => 96, 'stroke' => 9, 'label' => null, 'sub' => null, 'color' => null])
@php
    $r = ($size - $stroke) / 2;
    $c = 2 * M_PI * $r;
    $v = max(0, min(100, (float) $value));
@endphp
{{-- วงแหวนความคืบหน้า (Progress ring) --}}
<div class="ring" style="width:{{ $size }}px;height:{{ $size }}px;{{ $color ? '--ring-color:'.$color : '' }}">
    <svg width="{{ $size }}" height="{{ $size }}">
        <circle class="ring-bg" cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none" stroke-width="{{ $stroke }}"/>
        <circle class="ring-fg" cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" fill="none" stroke-width="{{ $stroke }}"
                stroke-dasharray="{{ $c }}" stroke-dashoffset="{{ $c * (1 - $v / 100) }}"/>
    </svg>
    <div class="ring-label"><b>{{ $label ?? round($v).'%' }}</b>@if($sub)<span>{{ $sub }}</span>@endif</div>
</div>
