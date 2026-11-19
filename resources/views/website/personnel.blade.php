@extends('layouts.site')
@section('title', 'ทำเนียบบุคลากร')

@section('content')
<div class="site-wrap">
    @include('website.partials.pagehead', ['title' => 'ทำเนียบบุคลากร', 'crumbs' => ['บุคลากร' => null]])

    @if ($groups->isEmpty())
        <div class="site-card text-center p-5 mt-3 text-muted"><i class="bi bi-people fs-1 d-block mb-2"></i>ยังไม่มีรายชื่อบุคลากร</div>
    @else
        @foreach ($departments->map(fn ($d) => [$d->name, $groups[$d->id]])->push(['บุคลากร', $groups[0] ?? collect()]) as [$name, $people])
            @continue($people->isEmpty())
            <section class="site-section">
                <div class="site-section-head"><h2>{{ $departments->isEmpty() ? 'ครูและบุคลากร' : $name }}</h2><span class="text-muted small">{{ $people->count() }} คน</span></div>
                <div class="person-grid">
                    @foreach ($people as $u)
                        <div class="site-card person">
                            @if ($u->avatarUrl())<img class="avatar-xl" src="{{ $u->avatarUrl() }}" alt="{{ $u->name }}" loading="lazy">@else<div class="avatar-xl">{{ mb_substr(preg_replace('/^(นาย|นางสาว|นาง|ว่าที่ร้อยตรี|ดร\.)\s*/u', '', $u->name), 0, 1) }}</div>@endif
                            <b>{{ $u->name }}</b>
                            <small>{{ $u->position ?: 'ครู' }}</small>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    @endif
</div>
@endsection
