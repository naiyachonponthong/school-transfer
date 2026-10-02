@props(['title', 'subtitle' => '', 'eyebrow' => 'SCHOOL HUB'])

<section class="section-hero">
    <div class="section-hero-copy">
        <span class="section-hero-eyebrow">{{ $eyebrow }}</span>
        <h1>{{ $title }}</h1>
        @if ($subtitle)<p>{{ $subtitle }}</p>@endif
        @if (trim((string) $slot) !== '')
            <div class="section-hero-actions">{{ $slot }}</div>
        @endif
    </div>
    <img class="section-hero-art" src="{{ asset('assets/img/school-pages.png') }}" alt="" aria-hidden="true" width="1536" height="1024">
</section>
