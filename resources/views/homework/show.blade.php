@extends('layouts.app')
@section('title', $assignment->title)

@section('content')
@php
    $submitted = $subs->whereNotNull('submitted_at')->count();
@endphp
<div class="page-head">
    <div>
        <h1>{{ $assignment->title }}</h1>
        <div class="sub">{{ $assignment->course->subject->name }} · ห้อง {{ $assignment->course->classroom->name() }} · กำหนดส่ง {{ $assignment->due_at ? thai_datetime($assignment->due_at) : '-' }}</div>
    </div>
    <div class="actions">
        @if ($assignment->assessment)
            <form method="POST" action="{{ route('homework.sync', $assignment) }}" data-confirm="ส่งคะแนนงานนี้เข้าช่อง &quot;{{ $assignment->assessment->name }}&quot; ในสมุดคะแนน? (คะแนนเดิมในช่องนั้นจะถูกแทนที่)">@csrf
                <button class="btn btn-soft"><i class="bi bi-arrow-right-circle"></i> ส่งเข้าสมุดคะแนน</button></form>
        @endif
        <form method="POST" action="{{ route('homework.destroy', $assignment) }}" data-confirm="ลบงานนี้และงานที่ส่งมาทั้งหมด?">@csrf @method('DELETE')<button class="btn btn-light border text-danger"><i class="bi bi-trash"></i></button></form>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-8">
        <div class="card h-100"><div class="card-body">
            <div style="white-space:pre-line">{{ $assignment->description ?: 'ไม่มีรายละเอียด' }}</div>
            @if ($assignment->attachmentUrl())<a href="{{ $assignment->attachmentUrl() }}" target="_blank" class="btn btn-sm btn-light border mt-2"><i class="bi bi-paperclip"></i> ไฟล์ประกอบ</a>@endif
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card h-100"><div class="card-body d-flex align-items-center gap-3">
            <x-ring :value="$students->count() ? $submitted / $students->count() * 100 : 0" :size="90" :label="$submitted.'/'.$students->count()" sub="ส่งแล้ว" />
            <div class="small">คะแนนเต็ม <b>{{ $assignment->max_score ?? '-' }}</b><br>
                @if ($assignment->assessment)ผูกกับช่อง <b>{{ $assignment->assessment->name }}</b> ({{ $assignment->assessment->max_score }})@else ไม่ได้ผูกช่องคะแนน @endif</div>
        </div></div>
    </div>
</div>

<form method="POST" action="{{ route('homework.grade', $assignment) }}" class="card">
    @csrf
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>เลขที่ · ชื่อ</th><th>สถานะ</th><th>งานที่ส่ง</th><th class="text-center">ส่งกระดาษ</th><th style="width:100px">คะแนน</th><th>ความเห็นครู</th></tr></thead>
            <tbody>
            @foreach ($students as $s)
                @php($sub = $subs[$s->id] ?? null)
                @php($st = $sub ? $sub->setRelation('assignment', $assignment)->state() : ['ยังไม่ส่ง', 'secondary'])
                <tr>
                    <td class="text-nowrap"><span class="text-muted">{{ $s->number }}</span> {{ $s->fullName() }}</td>
                    <td><span class="badge bg-{{ $st[1] }}">{{ $st[0] }}</span>@if($sub?->submitted_at)<div class="small text-muted">{{ thai_datetime($sub->submitted_at) }}</div>@endif</td>
                    <td class="small" style="max-width:260px">
                        @if ($sub?->text)<div class="text-truncate" title="{{ $sub->text }}">{{ $sub->text }}</div>@endif
                        @if ($sub?->fileUrl())<a href="{{ $sub->fileUrl() }}" target="_blank"><i class="bi bi-paperclip"></i> เปิดไฟล์</a>@endif
                        @if ($sub?->channel === 'paper')<span class="text-muted">ส่งเป็นกระดาษ</span>@endif
                    </td>
                    <td class="text-center"><input type="checkbox" class="form-check-input" name="rows[{{ $s->id }}][paper]" value="1" @checked($sub?->submitted_at) @disabled($sub?->submitted_at)></td>
                    <td><input type="number" step="0.5" min="0" @if($assignment->max_score) max="{{ $assignment->max_score }}" @endif name="rows[{{ $s->id }}][score]" value="{{ $sub?->score !== null ? rtrim(rtrim(number_format($sub->score, 2, '.', ''), '0'), '.') : '' }}" class="form-control form-control-sm"></td>
                    <td><input name="rows[{{ $s->id }}][feedback]" value="{{ $sub?->feedback }}" class="form-control form-control-sm" placeholder="เช่น ดีมาก / แก้ข้อ 3"></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent"><button class="btn btn-primary"><i class="bi bi-save"></i> บันทึกการตรวจ</button></div>
</form>
@endsection
