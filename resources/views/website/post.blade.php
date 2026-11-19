@extends('layouts.site')
@section('title', $post->title)
@section('description', $post->excerpt(160))
@if ($post->coverUrl())@section('og_image', $post->coverUrl())@endif

@section('content')
<div class="site-wrap">
    @include('website.partials.pagehead', ['title' => $post->title, 'crumbs' => ['ข่าวสาร' => route('web.news'), $post->categoryLabel() => route('web.news.category', $post->category)],
        'sub' => ($post->published_at ? thai_date($post->published_at, true) : 'ฉบับร่าง (ยังไม่เผยแพร่)').' · อ่าน '.number_format($post->views).' ครั้ง'])

    <div class="row g-3 mt-1">
        <div class="col-lg-8">
            <article class="site-card site-article">
                @unless ($post->isPublished())<div class="alert alert-warning small"><i class="bi bi-eye-slash"></i> ข่าวนี้ยังไม่เผยแพร่ เห็นได้เฉพาะผู้จัดการเว็บไซต์</div>@endunless
                @if ($post->cover)<img class="cover" src="{{ asset('storage/'.(string) $post->cover) }}" alt="{{ $post->title }}">@endif
                <div class="content">{!! \App\Support\Website::text($post->body) !!}</div>

                @if ($post->images)
                    <h2 class="fs-5 fw-bold mt-4 mb-3"><i class="bi bi-images text-primary"></i> ภาพกิจกรรม ({{ count($post->images) }} ภาพ)</h2>
                    <div class="gallery-grid">
                        @foreach ($post->images as $img)
                            <a href="{{ asset('storage/'.(string) $img) }}" target="_blank" rel="noopener"><img src="{{ asset('storage/'.(string) $img) }}" alt="ภาพกิจกรรม" loading="lazy"></a>
                        @endforeach
                    </div>
                @endif

                @if ($post->attachments)
                    <h2 class="fs-5 fw-bold mt-4 mb-3"><i class="bi bi-paperclip text-primary"></i> เอกสารแนบ</h2>
                    <div class="file-list">
                        @foreach ($post->attachments as $f)
                            <a href="{{ asset('storage/'.(string) $f['path']) }}" target="_blank" rel="noopener">
                                <i class="bi bi-file-earmark-{{ str_ends_with(strtolower($f['path']), '.pdf') ? 'pdf' : 'text' }}"></i>
                                <span class="flex-grow-1">{{ $f['name'] }}</span><i class="bi bi-download text-muted fs-6"></i>
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="d-flex gap-2 flex-wrap mt-4 pt-3 border-top">
                    <span class="text-muted small align-self-center">แชร์:</span>
                    <a class="btn btn-sm btn-light border" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank" rel="noopener"><i class="bi bi-facebook"></i> Facebook</a>
                    <a class="btn btn-sm btn-light border" href="https://social-plugins.line.me/lineit/share?url={{ urlencode(url()->current()) }}" target="_blank" rel="noopener"><i class="bi bi-line"></i> LINE</a>
                    <button type="button" class="btn btn-sm btn-light border" id="copyLink"><i class="bi bi-link-45deg"></i> คัดลอกลิงก์</button>
                    @if (\App\Support\Website::canManage(auth()->user()))
                        <a class="btn btn-sm btn-soft ms-auto" href="{{ route('website.admin', ['tab' => 'posts', 'edit' => $post->id]) }}"><i class="bi bi-pencil"></i> แก้ไขข่าวนี้</a>
                    @endif
                </div>
            </article>
        </div>
        <div class="col-lg-4">
            <div class="site-card">
                <div class="px-3 pt-3 pb-2"><h2 class="fs-6 fw-bold mb-0">{{ $post->categoryLabel() }}อื่น ๆ</h2></div>
                @if ($related->isEmpty())
                    <p class="text-muted small px-3 pb-3 mb-0">ยังไม่มีข่าวอื่นในหมวดนี้</p>
                @else
                    <ul class="list-plain">
                        @foreach ($related as $r)
                            <li><a href="{{ route('web.post', $r) }}"><span><span class="fw-semibold d-block">{{ $r->title }}</span><small class="text-muted">{{ thai_date($r->published_at) }}</small></span></a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('copyLink').addEventListener('click', async (e) => {
  try { await navigator.clipboard.writeText(location.href); e.currentTarget.innerHTML = '<i class="bi bi-check2"></i> คัดลอกแล้ว'; } catch (err) {}
});
</script>
@endpush
