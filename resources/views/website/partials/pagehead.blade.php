<section class="site-pagehead">
    <div class="crumb"><a href="{{ route('web.home') }}">หน้าแรก</a>@foreach ($crumbs ?? [] as $label => $url) / @if ($url)<a href="{{ $url }}">{{ $label }}</a>@else{{ $label }}@endif @endforeach</div>
    <h1>{{ $title }}</h1>
    @isset($sub)<div class="opacity-75 mt-1">{{ $sub }}</div>@endisset
</section>
