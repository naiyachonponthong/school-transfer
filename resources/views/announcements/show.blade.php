@extends('layouts.app')
@section('title', $announcement->title)

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <a href="{{ route('announcements.index') }}" class="btn btn-link px-0 mb-2"><i class="bi bi-arrow-left"></i> ประกาศทั้งหมด</a>
        <div class="card">
            <div class="card-body p-4">
                <div class="d-flex gap-2 mb-2">
                    @if ($announcement->pinned)<span class="badge bg-danger"><i class="bi bi-pin-angle-fill"></i> ปักหมุด</span>@endif
                    <span class="badge bg-light text-dark border">{{ $announcement->audienceLabel() }}</span>
                </div>
                <h1 class="h3 fw-bold">{{ $announcement->title }}</h1>
                <div class="text-muted small mb-4">{{ $announcement->author?->name }} · {{ thai_datetime($announcement->created_at) }}
                    @if (auth()->user()->isStaff()) · <i class="bi bi-eye"></i> อ่านแล้ว {{ $readCount }} คน @endif
                </div>
                <div style="white-space:pre-line;font-size:1.05rem;line-height:1.8">{{ $announcement->body }}</div>
            </div>
            @if (auth()->user()->isAdmin() || $announcement->author_id === auth()->id())
                <div class="card-footer bg-transparent d-flex gap-2">
                    <a href="{{ route('announcements.edit', $announcement) }}" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i> แก้ไข</a>
                    <form method="POST" action="{{ route('announcements.destroy', $announcement) }}" data-confirm="ลบประกาศนี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i> ลบ</button></form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
