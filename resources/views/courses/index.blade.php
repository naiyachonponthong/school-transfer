@extends('layouts.app')
@section('title', 'รายวิชาและคะแนน')

@section('content')
@php($u = auth()->user())
<div class="page-head">
    <div>
        <h1>{{ $showAll ? 'รายวิชาที่เปิดสอน' : 'รายวิชาที่ฉันสอน' }}</h1>
        <div class="sub">{{ $term?->label() }} · {{ $courses->count() }} รายวิชา</div>
    </div>
    <div class="actions">
        @if ($u->isAdmin())
            <a href="{{ request()->fullUrlWithQuery(['view' => $showAll ? 'mine' : null]) }}" class="btn btn-light border">{{ $showAll ? 'เฉพาะที่ฉันสอน' : 'ดูทั้งหมด' }}</a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkCourse"><i class="bi bi-plus-lg"></i> เปิดรายวิชา</button>
        @endif
    </div>
</div>

<form class="card mb-3" method="GET">
    <div class="card-body d-flex flex-wrap gap-2">
        <input type="hidden" name="view" value="{{ request('view') }}">
        <select name="term" class="form-select w-auto" data-autosubmit>
            @foreach ($terms as $t)<option value="{{ $t->id }}" @selected($term?->id === $t->id)>{{ $t->label() }}</option>@endforeach
        </select>
        <select name="classroom" class="form-select w-auto" data-autosubmit>
            <option value="">ทุกห้อง</option>
            @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected(request('classroom') == $c->id)>{{ $c->name() }}</option>@endforeach
        </select>
    </div>
</form>

@if ($courses->isEmpty())
    <div class="card"><div class="empty"><i class="bi bi-journal-x"></i>
        {{ $showAll ? 'ยังไม่ได้เปิดรายวิชาในภาคเรียนนี้' : 'ยังไม่มีรายวิชาที่คุณสอนในภาคเรียนนี้ ติดต่อฝ่ายวิชาการเพื่อกำหนดครูผู้สอน' }}
    </div></div>
@else
<div class="row g-3">
    @foreach ($courses as $c)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <div class="stat-icon tint-{{ ['basic' => 'primary', 'extra' => 'info', 'activity' => 'warning'][$c->subject->type] ?? 'primary' }}"><i class="bi bi-book"></i></div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold text-truncate">{{ $c->subject->name }}</div>
                            <div class="small text-muted">{{ $c->subject->code }} · {{ $c->subject->credit }} หน่วยกิต</div>
                        </div>
                        <span class="badge bg-dark fs-6">{{ $c->classroom->name() }}</span>
                    </div>
                    <div class="small text-muted mb-3">
                        <i class="bi bi-person"></i> {{ $c->teacher?->name ?? 'ยังไม่กำหนดครู' }} · {{ $c->assessments_count }} ช่องคะแนน
                        @if ($c->locked)<span class="badge bg-secondary ms-1"><i class="bi bi-lock-fill"></i> ล็อกแล้ว</span>@endif
                    </div>
                    <div class="mt-auto d-flex gap-2">
                        @if ($c->canEdit($u))
                            <a href="{{ route('gradebook.show', $c) }}" class="btn btn-primary flex-grow-1"><i class="bi bi-pencil-square"></i> กรอกคะแนน</a>
                            <a href="{{ route('period-attendance.report', $c) }}" class="btn btn-light border" title="เวลาเรียนรายวิชา / มส."><i class="bi bi-clock-history"></i></a>
                        @endif
                        @if ($u->isAdmin())
                            <button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#edit{{ $c->id }}"><i class="bi bi-gear"></i></button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        @if ($u->isAdmin())
            <div class="modal fade" id="edit{{ $c->id }}" tabindex="-1">
                <div class="modal-dialog"><div class="modal-content">
                    <form method="POST" action="{{ route('courses.update', $c) }}">
                        @csrf @method('PUT')
                        <div class="modal-header"><h5 class="modal-title">{{ $c->subject->name }} · {{ $c->classroom->name() }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">
                            <label class="form-label">ครูผู้สอน</label>
                            <select name="teacher_id" class="form-select mb-3">
                                <option value="">- ยังไม่กำหนด -</option>
                                @foreach ($teachers as $t)<option value="{{ $t->id }}" @selected($c->teacher_id === $t->id)>{{ $t->name }}</option>@endforeach
                            </select>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="locked" value="1" id="lock{{ $c->id }}" @checked($c->locked)>
                                <label class="form-check-label" for="lock{{ $c->id }}">ล็อกคะแนน (ครูแก้ไขไม่ได้ หลังส่งผลการเรียน)</label>
                            </div>
                        </div>
                        <div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div>
                    </form>
                    <form method="POST" action="{{ route('courses.destroy', $c) }}" class="px-3 pb-3" data-confirm="ลบรายวิชานี้และคะแนนทั้งหมด?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger px-0"><i class="bi bi-trash"></i> ลบรายวิชานี้</button></form>
                </div></div>
            </div>
        @endif
    @endforeach
</div>
@endif

@if ($u->isAdmin() && $term)
<div class="modal fade" id="bulkCourse" tabindex="-1">
    <div class="modal-dialog modal-lg"><form method="POST" action="{{ route('courses.bulk') }}" class="modal-content">
        @csrf
        <input type="hidden" name="term_id" value="{{ $term->id }}">
        <div class="modal-header"><h5 class="modal-title">เปิดรายวิชา · {{ $term->label() }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="small text-muted">เลือกหลายห้อง × หลายวิชาได้ในครั้งเดียว ระบบจะสร้างช่องคะแนนเริ่มต้น (เก็บ 70 : สอบ 30) ให้ แก้ไขได้ภายหลัง</p>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">ห้องเรียน</label>
                    <div class="border rounded-3 p-2" style="max-height:300px;overflow:auto">
                        @foreach ($classrooms->groupBy('level') as $level => $rooms)
                            <div class="small fw-semibold text-muted mt-1">{{ $level }}</div>
                            <div class="d-flex flex-wrap gap-2 mb-1">
                                @foreach ($rooms as $r)
                                    <label class="form-check-label small"><input type="checkbox" class="form-check-input" name="classroom_ids[]" value="{{ $r->id }}"> {{ $r->name() }}</label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-7">
                    <label class="form-label">รายวิชา</label>
                    <div class="border rounded-3 p-2" style="max-height:300px;overflow:auto">
                        @forelse ($subjects as $s)
                            <label class="d-block small"><input type="checkbox" class="form-check-input" name="subject_ids[]" value="{{ $s->id }}"> <span class="text-muted">{{ $s->code }}</span> {{ $s->name }}</label>
                        @empty
                            <div class="text-muted small">ยังไม่มีรายวิชา <a href="{{ route('subjects.index') }}">เพิ่มรายวิชา</a></div>
                        @endforelse
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">ครูผู้สอน (ไม่บังคับ)</label>
                    <select name="teacher_id" class="form-select">
                        <option value="">- กำหนดภายหลัง -</option>
                        @foreach ($teachers as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                    </select>
                </div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">เปิดรายวิชา</button></div>
    </form></div>
</div>
@endif
@endsection
