@extends('layouts.app')
@section('title', 'ประกาศ')

@section('content')
<x-page-banner title="ประกาศ" subtitle="ข่าวสารจากโรงเรียน" eyebrow="SCHOOL NEWS">
    @if (auth()->user()->isStaff())
        <a href="{{ route('announcements.create') }}" class="btn btn-light"><i class="bi bi-plus-lg"></i> สร้างประกาศ</a>
    @endif
</x-page-banner>

<div class="d-flex flex-column gap-2">
    @forelse ($announcements as $a)
        @php($unread = ! in_array($a->id, $readIds))
        <a href="{{ route('announcements.show', $a) }}" class="card announcement-card text-decoration-none text-body {{ $a->pinned ? 'border-danger-subtle' : '' }}">
            <div class="card-body d-flex gap-3">
                <div class="stat-icon {{ $a->pinned ? 'tint-danger' : 'tint-primary' }}"><i class="bi {{ $a->pinned ? 'bi-pin-angle-fill' : 'bi-megaphone' }}"></i></div>
                <div class="flex-grow-1 min-w-0">
                    <div class="d-flex gap-2 align-items-center">
                        <span class="fw-bold {{ $unread ? '' : 'text-body-secondary' }}">{{ $a->title }}</span>
                        @if ($unread)<span class="badge bg-danger">ใหม่</span>@endif
                    </div>
                    <div class="text-muted small text-truncate">{{ \Illuminate\Support\Str::limit(strip_tags($a->body), 140) }}</div>
                    <div class="small text-muted mt-1">
                        {{ $a->author?->name }} · {{ thai_datetime($a->created_at) }} ·
                        <span class="badge bg-light text-dark border">{{ $a->audienceLabel() }}</span>
                        @if (auth()->user()->isStaff()) · <i class="bi bi-eye"></i> อ่านแล้ว {{ $a->readers_count }} คน @endif
                    </div>
                </div>
            </div>
        </a>
    @empty
        <div class="card"><div class="empty"><i class="bi bi-megaphone"></i>ยังไม่มีประกาศ</div></div>
    @endforelse
</div>
<div class="mt-3">{{ $announcements->links() }}</div>
@endsection
