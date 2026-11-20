@extends('layouts.site')
@php($label = $tag !== '' ? '#'.$tag : ($category ? \App\Models\WebPost::CATEGORIES[$category][0] : 'ข่าวสารทั้งหมด'))
@section('title', $label)

@section('content')
<div class="site-wrap">
    @include('website.partials.pagehead', ['title' => $label, 'crumbs' => ($category || $tag !== '') ? ['ข่าวสาร' => route('web.news'), $label => null] : ['ข่าวสาร' => null]])

    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between site-section mt-4">
        <nav class="settings-subnav border-0 p-0 m-0" aria-label="หมวดข่าว" style="display:flex;gap:.4rem;flex-wrap:wrap">
            <a href="{{ route('web.news', array_filter(['q' => $q])) }}" class="btn btn-sm {{ ! $category ? 'btn-primary' : 'btn-light border' }}">ทั้งหมด</a>
            @foreach (\App\Models\WebPost::CATEGORIES as $key => [$name, $icon])
                <a href="{{ route('web.news.category', array_filter(['category' => $key, 'q' => $q])) }}" class="btn btn-sm {{ $category === $key ? 'btn-primary' : 'btn-light border' }}"><i class="bi bi-{{ $icon }}"></i>{{ $name }}</a>
            @endforeach
        </nav>
        <form method="GET" class="d-flex gap-2" role="search">
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="ค้นหาข่าว" aria-label="ค้นหาข่าว" style="min-width:220px">
            <button class="btn btn-primary"><i class="bi bi-search"></i></button>
        </form>
    </div>

    @if ($posts->isEmpty())
        <div class="site-card text-center p-5 mt-3 text-muted"><i class="bi bi-newspaper fs-1 d-block mb-2"></i>{{ $q !== '' ? 'ไม่พบข่าวที่ค้นหา' : 'ยังไม่มีข่าวในหมวดนี้' }}</div>
    @else
        <div class="news-grid mt-3">@foreach ($posts as $p) @include('website.partials.news-card', ['post' => $p]) @endforeach</div>
        <div class="mt-4 d-flex justify-content-center">{{ $posts->links() }}</div>
    @endif
</div>
@endsection
