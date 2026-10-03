@extends('layouts.app')
@section('title', 'หนังสือขออนุญาต')

@section('content')
<div class="page-head">
    <div><h1>หนังสือขออนุญาตจากโรงเรียน</h1><div class="sub">อ่านรายละเอียดแล้วกดอนุญาตหรือไม่อนุญาต แก้คำตอบได้จนกว่าจะปิดรับ</div></div>
</div>

@forelse ($forms as $f)
    <div class="card mb-3">
        <div class="card-header">{{ $f->title }}
            <span class="ms-auto small text-muted fw-normal">{{ thai_date($f->created_at) }}{{ $f->due_date ? ' · ตอบภายใน '.thai_date($f->due_date) : '' }}</span>
        </div>
        <div class="card-body" style="white-space:pre-line">{{ $f->body }}</div>
        @foreach ($children->filter(fn ($c) => $f->includes($c)) as $child)
            @php($r = $responses[$f->id.'-'.$child->id] ?? null)
            <div class="border-top px-3 py-2">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="flex-grow-1"><b>{{ $child->fullName() }}</b> <span class="text-muted small">{{ $child->classroom?->name() }}</span>
                        @if ($r)<span class="badge bg-{{ $r->agreed ? 'success' : 'danger' }} ms-1">{{ $r->agreed ? 'อนุญาตแล้ว' : 'ไม่อนุญาต' }}</span>@endif
                    </span>
                    @if ($f->acceptsResponses())
                        @foreach ([1 => ['อนุญาต', 'success'], 0 => ['ไม่อนุญาต', 'outline-danger']] as $val => [$label, $color])
                            <form method="POST" action="{{ route('parent.consents.respond', $f) }}">
                                @csrf<input type="hidden" name="student_id" value="{{ $child->id }}"><input type="hidden" name="agreed" value="{{ $val }}">
                                <button class="btn btn-{{ $color }}">{{ $label }}</button>
                            </form>
                        @endforeach
                    @else
                        <span class="small text-muted">ปิดรับคำตอบแล้ว</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@empty
    <div class="card"><div class="empty"><i class="bi bi-envelope-paper"></i>ยังไม่มีหนังสือขออนุญาต</div></div>
@endforelse
@endsection
