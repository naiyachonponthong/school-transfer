@props(['anchor', 'title'])
<section class="manual-section" id="{{ $anchor }}">
    <h2><a href="#{{ $anchor }}" class="manual-anchor" aria-hidden="true">#</a>{{ $title }}</h2>
    {{ $slot }}
</section>
