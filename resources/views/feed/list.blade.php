@forelse ($posts as $post)
    @include('feed.post', ['post' => $post])
@empty
    <div class="empty"><i class="bi bi-newspaper"></i>ยังไม่มีข่าวสาร</div>
@endforelse
@if ($posts instanceof \Illuminate\Pagination\LengthAwarePaginator && $posts->hasMorePages())
    <div class="p-3 text-center" data-feed-more>
        <button type="button" class="btn btn-soft w-100" data-url="{{ route('feed.index', ['page' => $posts->currentPage() + 1]) }}">ดูข่าวเพิ่มเติม</button>
    </div>
@endif
