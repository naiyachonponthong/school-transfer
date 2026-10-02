@extends('layouts.app')
@section('title', 'การบ้าน')

@section('content')
<div class="page-head"><div><h1>การบ้าน</h1><div class="sub">{{ auth()->user()->isStudent() ? "ส่งงานได้เลย ถ่ายรูปงานหรือแนบไฟล์" : "ส่งงานแทนบุตรหลานได้ (ถ่ายรูปงานหรือแนบไฟล์)" }}</div></div></div>

@forelse ($items as $item)
    @php($child = $item['child'])
    <div class="m-section-title">{{ auth()->user()->isStudent() ? "งานของฉัน" : "น้อง".($child->nickname ?: $child->first_name) }} <span class="small text-muted fw-normal">ห้อง {{ $child->classroom?->name() }}</span></div>
    <div class="d-flex flex-column gap-2 mb-4">
        @forelse ($item['assignments'] as $a)
            @php($sub = $item['subs'][$a->id] ?? null)
            @php($st = $sub ? $sub->setRelation('assignment', $a)->state() : ($a->isClosed() ? ['เลยกำหนด ยังไม่ส่ง', 'danger'] : ['ยังไม่ส่ง', 'secondary']))
            <div class="card">
                <div class="card-body">
                    <div class="d-flex gap-2 align-items-start">
                        <span class="app-ico" style="width:42px;height:42px;font-size:1.1rem;flex-shrink:0"><i class="bi bi-journal-text"></i></span>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold">{{ $a->title }}</div>
                            <div class="small text-muted">{{ $a->course->subject->name }} · ส่งภายใน {{ $a->due_at ? thai_datetime($a->due_at) : '-' }}</div>
                        </div>
                        <span class="badge bg-{{ $st[1] }}">{{ $st[0] }}</span>
                    </div>
                    @if ($a->description)<div class="small mt-2" style="white-space:pre-line">{{ $a->description }}</div>@endif
                    @if ($a->attachmentUrl())<a href="{{ $a->attachmentUrl() }}" target="_blank" class="btn btn-sm btn-light border mt-2"><i class="bi bi-paperclip"></i> ใบงาน</a>@endif

                    @if ($sub?->score !== null)
                        <div class="alert alert-success py-2 mt-2 mb-0 small">คะแนน <b>{{ rtrim(rtrim(number_format($sub->score, 2), '0'), '.') }}{{ $a->max_score ? ' / '.rtrim(rtrim(number_format($a->max_score, 2), '0'), '.') : '' }}</b>@if($sub->feedback) · ครู: {{ $sub->feedback }}@endif</div>
                    @else
                        <details class="mt-2" @if(! $sub) open @endif>
                            <summary class="small text-primary fw-semibold" style="cursor:pointer">{{ $sub ? 'ส่งใหม่ / แก้ไขงาน' : 'ส่งงาน' }}</summary>
                            <form method="POST" action="{{ route(auth()->user()->isStudent() ? 'student.homework.submit' : 'parent.homework.submit', $a) }}" enctype="multipart/form-data" class="mt-2">
                                @csrf
                                <input type="hidden" name="student_id" value="{{ $child->id }}">
                                <textarea name="text" rows="2" class="form-control mb-2" placeholder="พิมพ์คำตอบหรือข้อความถึงครู">{{ $sub?->text }}</textarea>
                                <div class="d-flex gap-2">
                                    <input type="file" name="file" class="form-control" accept="image/*,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,video/*">
                                    <button class="btn btn-primary text-nowrap"><i class="bi bi-send"></i> ส่ง</button>
                                </div>
                            </form>
                        </details>
                    @endif
                </div>
            </div>
        @empty
            <div class="card"><div class="empty py-4"><i class="bi bi-emoji-smile"></i>ยังไม่มีการบ้าน</div></div>
        @endforelse
    </div>
@empty
    <div class="card"><div class="empty"><i class="bi bi-person-exclamation"></i>ยังไม่ได้ผูกบัญชีกับนักเรียน</div></div>
@endforelse
@endsection
