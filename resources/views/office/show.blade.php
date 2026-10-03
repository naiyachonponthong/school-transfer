@extends('layouts.app')
@section('title', $doc->subject)

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $doc->subject }}</h1>
        <div class="sub">{{ $doc->typeLabel() }} เลขทะเบียน {{ $doc->number() }} · ลงวันที่ {{ thai_date($doc->doc_date) }}{{ $doc->ref_no ? ' · ที่ '.$doc->ref_no : '' }}{{ $doc->party ? ' · '.$doc->party : '' }}</div>
    </div>
    <div class="actions">
        <a href="{{ route('office.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
        @if ($doc->file)<a href="{{ route('files.show', ['office-doc', $doc->id]) }}" target="_blank" class="btn btn-primary"><i class="bi bi-file-earmark-text"></i> เปิดไฟล์หนังสือ</a>@endif
    </div>
</div>

@if ($mine && ! $mine->pivot->acknowledged_at)
    <form method="POST" action="{{ route('office.acknowledge', $doc) }}" class="alert alert-info d-flex align-items-center gap-3">
        @csrf
        <span class="flex-grow-1"><i class="bi bi-bell"></i> หนังสือนี้เวียนถึงคุณ อ่านแล้วกรุณากดรับทราบ</span>
        <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> รับทราบ</button>
    </form>
@elseif ($mine)
    <div class="alert alert-success py-2 small"><i class="bi bi-check-circle"></i> คุณรับทราบแล้วเมื่อ {{ thai_datetime($mine->pivot->acknowledged_at) }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-info-circle"></i> รายละเอียด</div>
            <div class="card-body small">
                <div><span class="text-muted">ชั้นความเร็ว:</span> {{ \App\Models\OfficeDocument::URGENCY[$doc->urgency][0] }}</div>
                <div><span class="text-muted">ลงทะเบียนโดย:</span> {{ $doc->creator?->name ?? '-' }} เมื่อ {{ thai_datetime($doc->created_at) }}</div>
                @if ($doc->note)<div class="mt-2" style="white-space:pre-line">{{ $doc->note }}</div>@endif
            </div>
        </div>
    </div>
    @if ($canManage)
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-people"></i> การรับทราบ {{ $doc->recipients->whereNotNull('pivot.acknowledged_at')->count() }}/{{ $doc->recipients->count() }}</div>
            @forelse ($doc->recipients as $r)
                <div class="d-flex px-3 py-2 border-bottom small">
                    <span class="flex-grow-1">{{ $r->name }}</span>
                    @if ($r->pivot->acknowledged_at)<span class="text-success"><i class="bi bi-check-circle"></i> {{ thai_datetime($r->pivot->acknowledged_at) }}</span>
                    @else<span class="text-muted">ยังไม่รับทราบ</span>@endif
                </div>
            @empty
                <div class="empty py-3">ยังไม่ได้เวียน</div>
            @endforelse
            <form method="POST" action="{{ route('office.recipients', $doc) }}" class="card-body">
                @csrf
                <label class="form-label small">เวียนเพิ่ม</label>
                <div class="d-flex flex-wrap gap-3 mb-2" style="max-height:140px;overflow:auto">
                    @foreach ($staff->whereNotIn('id', $doc->recipients->pluck('id')) as $u)<label class="small text-nowrap"><input type="checkbox" class="form-check-input" name="recipient_ids[]" value="{{ $u->id }}"> {{ $u->name }}</label>@endforeach
                </div>
                <button class="btn btn-sm btn-light border"><i class="bi bi-send"></i> เวียน</button>
            </form>
        </div>
    </div>
    @endif
</div>
@endsection
