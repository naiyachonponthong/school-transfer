@extends('layouts.app')
@section('title', 'ตรวจทาน · '.$exam->title)

@section('content')
@include('exams._nav')
@php
    $fmt = fn ($v) => $v === null ? '-' : rtrim(rtrim(number_format($v, 2), '0'), '.');
    $prev = $position !== false && $position > 0 ? $queue[$position - 1] : null;
    $next = $position !== false && $position < $queue->count() - 1 ? $queue[$position + 1] : null;
@endphp

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <a href="{{ route('exams.results', [$exam, 'tab' => $r->status === 'review' ? 'review' : 'all']) }}" class="btn btn-light border btn-sm"><i class="bi bi-arrow-left"></i> ผลตรวจ</a>
    @if ($position !== false)
        <span class="small text-muted">รอตรวจทาน แผ่นที่ {{ $position + 1 }}/{{ $queue->count() }}</span>
        @if ($prev)<a href="{{ route('exams.review', [$exam, $prev]) }}" class="btn btn-light border btn-sm"><i class="bi bi-chevron-left"></i></a>@endif
        @if ($next)<a href="{{ route('exams.review', [$exam, $next]) }}" class="btn btn-light border btn-sm"><i class="bi bi-chevron-right"></i></a>@endif
    @endif
    <span class="badge bg-{{ $r->statusColor() }} ms-auto">{{ $r->statusLabel() }}</span>
</div>

@if (session('replace_prompt'))
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> {{ session('replace_prompt') }}</div>
@endif

<div class="row g-3">
    <div class="col-lg-7">
        <div class="review-sheet" id="sheet"
             data-n="{{ $exam->n_items }}" data-groups='@json(\App\Models\Exam::SEAT_GROUPS)'
             data-key='@json($exam->key())' data-cancelled='@json($exam->cancelledItems())'
             data-cancel-mode="{{ $exam->cancel_mode }}" data-points="{{ $exam->points }}"
             data-flagged='@json(collect($r->flags ?? [])->filter(fn ($f) => preg_match('/^(multi|low_conf):\d+$/', $f))->map(fn ($f) => (int) explode(':', $f)[1])->values())'>
            @if ($r->image)
                <img src="{{ route('exams.image', [$exam, $r]) }}" alt="ภาพกระดาษคำตอบ">
            @else
                <div id="blankSheet"></div>
            @endif
        </div>
        <div class="small text-muted mt-2">
            <span class="me-3"><span class="legend-dot" style="background:#2563eb"></span> วงที่ระบบอ่าน/เลือก</span>
            <span class="me-3"><i class="bi bi-circle text-success"></i> เฉลย</span>
            <span><span class="legend-dot" style="background:#facc15"></span> ข้อที่ต้องดู</span>
            · แตะวงเพื่อแก้คำตอบ
            @unless ($r->image)<div class="mt-1"><i class="bi bi-image"></i> แผ่นนี้ไม่มีภาพ (ภาพเสียหรือเกินขนาด) — แสดงแผ่นเปล่าแทน</div>@endunless
        </div>
    </div>

    <div class="col-lg-5">
        <form method="POST" action="{{ route('exams.review.update', [$exam, $r]) }}" class="card mb-3" id="reviewForm">
            @csrf @method('PUT')
            <input type="hidden" name="answers" id="answers" value="{{ old('answers', $r->answers) }}">
            @if (session('replace_prompt'))<input type="hidden" name="replace" value="1">@endif
            <div class="card-body">
                <div class="d-flex align-items-start gap-2 mb-2">
                    <div class="flex-grow-1">
                        <div class="small text-muted">อ่านรหัสได้ <code>{{ $r->code_read ?: '-' }}</code> · เลขที่ <code>{{ $r->seat_read ?: '-' }}</code></div>
                        @if ($r->reasons())
                            <div class="mt-1">@foreach ($r->reasons() as $why)<span class="badge bg-warning-subtle text-warning-emphasis me-1">{{ $why }}</span>@endforeach</div>
                        @endif
                    </div>
                    <div class="text-end">
                        <div class="fs-2 fw-bold lh-1" id="liveScore">{{ $fmt($r->score) }}</div>
                        <div class="small text-muted">จาก <span id="liveMax">{{ $fmt($r->max_score) }}</span></div>
                    </div>
                </div>

                <label class="form-label mt-2">เจ้าของแผ่น</label>
                <input type="search" class="form-control form-control-sm mb-1" id="stuFilter" placeholder="พิมพ์เลขประจำตัว ชื่อ หรือเลขที่เพื่อกรอง">
                <select name="student_id" id="stuSelect" class="form-select @error('student_id') is-invalid @enderror">
                    @foreach ($students as $s)
                        <option value="{{ $s['id'] }}" @selected(old('student_id', $r->takerId()) == $s['id'])>{{ $s['label'] }}{{ $s['taken'] ? ' · มีแผ่นอื่นแล้ว' : '' }}</option>
                    @endforeach
                </select>
                @error('student_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                <label class="form-label mt-3">คำตอบรายข้อ <span class="small text-muted fw-normal">แตะเพื่อเปลี่ยน ก → ข → ค → ง → ว่าง</span></label>
                <div class="r-grid" id="ansGrid"></div>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                <button class="btn btn-primary flex-grow-1"><i class="bi bi-check2"></i> {{ $r->status === 'review' ? 'บันทึกแล้วไปแผ่นถัดไป' : 'บันทึก' }}</button>
            </div>
        </form>

        <div class="d-flex gap-2 mb-3">
            @if ($r->status === 'void')
                <form method="POST" action="{{ route('exams.review.update', [$exam, $r]) }}">@csrf @method('PUT')<input type="hidden" name="action" value="restore">
                    <button class="btn btn-light border btn-sm"><i class="bi bi-arrow-counterclockwise"></i> นำกลับมา</button></form>
            @else
                <form method="POST" action="{{ route('exams.review.update', [$exam, $r]) }}" data-confirm="ยกเลิกแผ่นนี้? (ไม่ลบ นำกลับมาได้ที่แท็บ ทุกแผ่น)">@csrf @method('PUT')<input type="hidden" name="action" value="void">
                    <button class="btn btn-link text-danger btn-sm px-0"><i class="bi bi-x-circle"></i> ยกเลิกแผ่นนี้ (แผ่นเสีย/ถ่ายซ้ำ)</button></form>
            @endif
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> ประวัติ</div>
            <div class="card-body small">
                <div>สแกนเมื่อ {{ $r->scanned_at ? thai_date($r->scanned_at).' '.$r->scanned_at->format('H:i') : '-' }} โดย {{ $r->scanner?->name ?? '-' }} ({{ $r->source === 'photo' ? 'จากรูป' : 'กล้อง' }}{{ $r->confidence !== null ? ' · ความมั่นใจ '.number_format($r->confidence * 100).'%' : '' }})</div>
                @foreach (array_reverse($r->edits ?? []) as $e)
                    <div class="border-top mt-2 pt-2">{{ $e['what'] }} <span class="text-muted">— {{ $e['by'] }} {{ $e['at'] }}</span></div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('exams._scripts', ['files' => ['common.js', 'sheet-layout.js', 'sheet.js', 'review.js']])
@endpush
