@extends('layouts.app')
@section('title', 'แจ้งเตือน')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
        <div class="page-head"><div><h1>แจ้งเตือน</h1><div class="sub">{{ $items->where('unread', true)->count() }} รายการใหม่</div></div></div>
        <div class="card overflow-hidden">
            @forelse ($items as $n)
                <a href="{{ $n['url'] }}" class="noti {{ $n['unread'] ? 'unread' : '' }}">
                    <span class="ni tint-{{ $n['color'] === 'primary' ? 'primary' : $n['color'] }}"><i class="bi {{ $n['icon'] }}"></i></span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold">{{ $n['title'] }}</div>
                        <div class="small text-muted">{{ $n['sub'] }}</div>
                    </div>
                    <span class="when">{{ \App\Support\Thai::ago($n['at']) }}</span>
                </a>
            @empty
                <div class="empty"><i class="bi bi-bell-slash"></i>ยังไม่มีแจ้งเตือน</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
