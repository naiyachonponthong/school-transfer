@extends('layouts.app')
@section('title', 'หนังสือขออนุญาตผู้ปกครอง')

@section('content')
<div class="page-head">
    <div><h1>หนังสือขออนุญาตผู้ปกครอง</h1><div class="sub">ส่งถึงห้องที่เลือก ผู้ปกครองกดอนุญาตหรือไม่อนุญาตในระบบ ครูเห็นว่าใครยังไม่ตอบ</div></div>
    <div class="actions"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newConsent" @disabled($classrooms->isEmpty())><i class="bi bi-plus-lg"></i> ส่งหนังสือใหม่</button></div>
</div>

@if ($errors->any())<div class="alert alert-danger py-2 small">{{ $errors->first() }}</div>@endif

<div class="card">
    @forelse ($forms as $f)
        <a href="{{ route('consents.show', $f) }}" class="d-flex align-items-center gap-3 px-3 py-2 border-bottom text-decoration-none text-body">
            <div class="flex-grow-1">
                <div class="fw-semibold">{{ $f->title }} @unless ($f->is_open)<span class="badge bg-secondary">ปิดรับแล้ว</span>@endunless</div>
                <div class="small text-muted">ส่งเมื่อ {{ thai_date($f->created_at) }}{{ $f->due_date ? ' · ตอบภายใน '.thai_date($f->due_date) : '' }}</div>
            </div>
            <div class="text-end small">
                <div><b>{{ $f->responses_count }}</b>/{{ $f->target_count }} ตอบแล้ว</div>
                <div class="text-muted">อนุญาต {{ $f->agreed_count }} · ไม่อนุญาต {{ $f->responses_count - $f->agreed_count }}</div>
            </div>
        </a>
    @empty
        <div class="empty"><i class="bi bi-envelope-paper"></i>ยังไม่มีหนังสือขออนุญาต</div>
    @endforelse
</div>

<div class="modal fade" id="newConsent" tabindex="-1">
    <div class="modal-dialog modal-lg"><form method="POST" action="{{ route('consents.store') }}" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">ส่งหนังสือขออนุญาต</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-md-8"><label class="form-label">เรื่อง</label><input name="title" value="{{ old('title') }}" class="form-control" placeholder="เช่น ขออนุญาตพานักเรียนไปทัศนศึกษา" required></div>
            <div class="col-md-4"><label class="form-label">ตอบภายใน</label><input type="date" name="due_date" value="{{ old('due_date') }}" min="{{ today()->toDateString() }}" class="form-control"></div>
            <div class="col-12"><label class="form-label">รายละเอียด</label><textarea name="body" rows="6" class="form-control" placeholder="วัน เวลา สถานที่ ค่าใช้จ่าย ครูผู้ควบคุม การเดินทาง" required>{{ old('body') }}</textarea></div>
            <div class="col-12">
                <label class="form-label">ส่งถึงห้อง</label>
                <div class="d-flex flex-wrap gap-3">@foreach ($classrooms as $c)<label class="small"><input type="checkbox" class="form-check-input" name="classroom_ids[]" value="{{ $c->id }}" @checked(in_array($c->id, old('classroom_ids', [])))> {{ $c->name() }}</label>@endforeach</div>
            </div>
        </div>
        <div class="modal-footer"><span class="small text-muted me-auto">ผู้ปกครองที่เชื่อม LINE จะได้รับแจ้งทันที</span><button class="btn btn-primary"><i class="bi bi-send"></i> ส่ง</button></div>
    </form></div>
</div>
@endsection
