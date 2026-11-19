@extends('layouts.site')
@section('title', $page->title)
@section('description', \Illuminate\Support\Str::limit(strip_tags((string) $page->body), 160))

@section('content')
<div class="site-wrap">
    @include('website.partials.pagehead', ['title' => $page->title, 'crumbs' => ['เกี่ยวกับโรงเรียน' => null, $page->title => null]])
    <article class="site-card site-article mt-3">
        @unless ($page->is_published)<div class="alert alert-warning small"><i class="bi bi-eye-slash"></i> หน้านี้ยังไม่เผยแพร่ เห็นได้เฉพาะผู้จัดการเว็บไซต์</div>@endunless
        @if ($page->coverUrl())<img class="cover" src="{{ $page->coverUrl() }}" alt="{{ $page->title }}">@endif
        @if (trim((string) $page->body) === '')
            <p class="text-muted mb-0">ยังไม่มีเนื้อหา</p>
        @else
            <div class="content">{!! \App\Support\Website::text($page->body) !!}</div>
        @endif
        @if (\App\Support\Website::canManage(auth()->user()))
            <div class="mt-4 pt-3 border-top"><a class="btn btn-sm btn-soft" href="{{ route('website.admin', ['tab' => 'pages', 'edit' => $page->id]) }}"><i class="bi bi-pencil"></i> แก้ไขหน้านี้</a></div>
        @endif
    </article>
</div>
@endsection
