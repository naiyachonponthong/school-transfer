@props(['copy', 'band' => true])
{{-- ป้ายสันหนังสือ 2.5 × 4 ซม.: แถบสีตามหมวด + เลขเรียกหนังสือเรียงบรรทัด --}}
@php
    $b = $copy->book;
    [, $symbol, $place] = $b->collectionInfo();
    $main = $b->mainClass();
@endphp
<div {{ $attributes->merge(['class' => 'spine'.($band ? '' : ' no-band')]) }}>
    <div class="band" style="background:{{ \App\Support\Dewey::COLORS[$main] ?? '#9ca3af' }}">{{ $place === 'class' ? $symbol : $main }}</div>
    <div class="lines">@foreach ($b->callNumber($copy) as $line)<div>{{ $line }}</div>@endforeach</div>
</div>
