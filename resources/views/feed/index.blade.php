@extends('layouts.app')
@section('title', 'ฟีดข่าว')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <x-page-banner title="ฟีดข่าวโรงเรียน" subtitle="ผลงาน กิจกรรม และประกาศล่าสุด" eyebrow="SCHOOL COMMUNITY" />
        <div class="card overflow-hidden" data-feed>
            @include('feed.composer')
            @include('feed.list', ['posts' => $posts])
        </div>
    </div>
</div>
@endsection
