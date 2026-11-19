<a href="{{ route('web.post', $post) }}" class="site-card news-card">
    <div class="news-thumb">
        @if ($post->coverUrl())<img src="{{ $post->coverUrl() }}" alt="" loading="lazy">@else<i class="bi bi-{{ \App\Models\WebPost::CATEGORIES[$post->category][1] ?? 'newspaper' }}"></i>@endif
    </div>
    <div class="news-body">
        <span class="news-chip">@if ($post->pinned)<i class="bi bi-pin-angle-fill"></i>@endif{{ $post->categoryLabel() }}</span>
        <h3>{{ $post->title }}</h3>
        @if ($post->body)<p>{{ $post->excerpt() }}</p>@endif
        <div class="news-meta"><span><i class="bi bi-calendar3"></i> {{ thai_date($post->published_at) }}</span><span><i class="bi bi-eye"></i> {{ number_format($post->views) }}</span></div>
    </div>
</a>
