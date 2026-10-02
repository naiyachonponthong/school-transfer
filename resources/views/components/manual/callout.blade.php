@props(['type' => 'tip', 'title' => null])
@php([$icon, $label] = ['tip' => ['bi-lightbulb', 'เคล็ดลับ'], 'warn' => ['bi-exclamation-triangle', 'ข้อควรระวัง'], 'note' => ['bi-info-circle', 'หมายเหตุ']][$type])
<div class="manual-callout {{ $type }}">
    <div class="mc-title"><i class="bi {{ $icon }}"></i> {{ $title ?? $label }}</div>
    <div>{{ $slot }}</div>
</div>
