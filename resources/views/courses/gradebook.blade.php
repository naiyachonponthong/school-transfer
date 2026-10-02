@extends('layouts.app')
@section('title', 'คะแนน '.$course->subject->name.' '.$course->classroom->name())

@section('content')
@php
    $readonly = $course->locked && ! auth()->user()->isAdmin();
    $max = $course->maxTotal();
@endphp
<div class="page-head">
    <div>
        <h1>{{ $course->subject->name }} <span class="badge bg-dark align-middle">{{ $course->classroom->name() }}</span></h1>
        <div class="sub">{{ $course->subject->code }} · {{ $course->term->label() }} · ครู {{ $course->teacher?->name ?? '-' }}</div>
    </div>
    <div class="actions">
        <span id="saveState" class="save-state align-self-center">@if($readonly)<i class="bi bi-lock-fill" aria-hidden="true"></i> ล็อกแล้ว ดูได้อย่างเดียว@else พิมพ์แล้วบันทึกเอง ไม่ต้องกดปุ่ม@endif</span>
        <a href="{{ route('gradebook.export', $course) }}" class="btn btn-light border"><i class="bi bi-file-earmark-spreadsheet"></i> ส่งออก</a>
        @unless ($readonly)
            <button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#columns"><i class="bi bi-layout-three-columns"></i> ช่องคะแนน</button>
        @endunless
    </div>
</div>

@if (abs($max - 100) > 0.001 && $course->assessments->isNotEmpty())
    <div class="alert alert-warning py-2 small"><i class="bi bi-info-circle"></i> คะแนนเต็มรวม {{ $max }} (ไม่ใช่ 100) ระบบจะคิดเกรดจากเปอร์เซ็นต์ให้อัตโนมัติ</div>
@endif

<div class="row g-3">
    <div class="col-xl-9">
        <div class="card">
            <div class="table-responsive">
                <table class="table gradebook align-middle mb-0" id="gradebook"
                       data-url="{{ route('gradebook.save', $course) }}" data-cols="{{ $course->assessments->count() }}" data-max="{{ $max }}" @if($readonly) data-readonly="1" @endif>
                    <thead>
                        <tr>
                            <th class="sticky-col">เลขที่ · ชื่อ</th>
                            @foreach ($course->assessments as $a)
                                <th class="text-center">{{ $a->name }}<div class="fw-normal text-primary">{{ rtrim(rtrim(number_format($a->max_score, 2), '0'), '.') }}</div></th>
                            @endforeach
                            <th class="text-center">รวม<div class="fw-normal text-primary">{{ $max }}</div></th>
                            <th class="text-center">เกรด</th>
                            <th class="text-center"><a href="{{ route('period-attendance.report', $course) }}" class="text-reset">เวลาเรียน</a></th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($students as $s)
                        <tr>
                            <td class="sticky-col text-nowrap"><span class="text-muted me-1">{{ $s->number }}</span> {{ $s->fullName() }}</td>
                            @foreach ($course->assessments as $a)
                                @php($v = $scores[$s->id][$a->id] ?? null)
                                <td class="text-center">
                                    <input class="score" name="scores[{{ $s->id }}][{{ $a->id }}]" value="{{ $v !== null ? rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.') : '' }}"
                                           data-max="{{ $a->max_score }}" inputmode="decimal" autocomplete="off" @disabled($readonly)>
                                </td>
                            @endforeach
                            <td class="text-center total">-</td>
                            <td class="text-center grade">-</td>
                            @php($at = $attendance[$s->id] ?? null)
                            <td class="text-center small text-nowrap">
                                @if ($at && $at['percent'] !== null)
                                    <span class="{{ $at['ms'] ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $at['percent'] }}%</span>
                                    @if ($at['ms'])<span class="badge bg-danger">มส.</span>@endif
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $course->assessments->count() + 4 }}"><div class="empty"><i class="bi bi-people"></i>ห้องนี้ยังไม่มีนักเรียน</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="small text-muted mt-2">
            <i class="bi bi-keyboard"></i> <kbd>Enter</kbd>/<kbd>↓</kbd> ลงคนถัดไป · <kbd>←</kbd><kbd>→</kbd> เปลี่ยนช่อง ·
            <i class="bi bi-clipboard"></i> คัดลอกคะแนนทั้งคอลัมน์จาก Excel แล้ววางที่ช่องแรกได้เลย
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card">
            <div class="card-header"><i class="bi bi-bar-chart"></i> การกระจายเกรด</div>
            <div class="card-body">
                @php($n = max(1, $distribution->sum()))
                @foreach (array_values(\App\Support\Grade::SCALE) as $g)
                    <div class="d-flex align-items-center gap-2 mb-1 small">
                        <span style="width:28px" class="fw-semibold">{{ $g }}</span>
                        <div class="flex-grow-1 behavior-meter"><span style="width:{{ ($distribution[$g] ?? 0) / $n * 100 }}%;background:var(--bs-{{ \App\Support\Grade::color($g) }})"></span></div>
                        <span style="width:24px" class="text-end">{{ $distribution[$g] ?? 0 }}</span>
                    </div>
                @endforeach
                <div class="small text-muted mt-2">คำนวณจากคนที่กรอกคะแนนครบทุกช่อง (รีเฟรชเพื่ออัปเดต)</div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><i class="bi bi-rulers"></i> เกณฑ์ตัดเกรด</div>
            <div class="card-body small">
                @php($prev = 100)
                @foreach (\App\Support\Grade::SCALE as $min => $g)
                    <div class="d-flex justify-content-between"><span>{{ $min }}–{{ $prev }}</span><b>{{ $g }}</b></div>
                    @php($prev = $min - 1)
                @endforeach
            </div>
        </div>
    </div>
</div>

@unless ($readonly)
<div class="modal fade" id="columns" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">ช่องคะแนน</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            @foreach ($course->assessments as $a)
                <form method="POST" action="{{ route('assessments.update', $a) }}" class="d-flex gap-2 mb-2">
                    @csrf @method('PUT')
                    <input name="name" value="{{ $a->name }}" class="form-control form-control-sm" required>
                    <input name="max_score" type="number" step="0.5" value="{{ $a->max_score }}" class="form-control form-control-sm" style="width:90px" required>
                    <input name="sort" type="hidden" value="{{ $a->sort }}">
                    <button class="btn btn-sm btn-light border" title="บันทึก"><i class="bi bi-check-lg"></i></button>
                    <button form="del{{ $a->id }}" class="btn btn-sm btn-light border text-danger" title="ลบ"><i class="bi bi-trash"></i></button>
                </form>
                <form id="del{{ $a->id }}" method="POST" action="{{ route('assessments.destroy', $a) }}" data-confirm="ลบช่อง {{ $a->name }} และคะแนนในช่องนี้ทั้งหมด?">@csrf @method('DELETE')</form>
            @endforeach
            <form method="POST" action="{{ route('assessments.store', $course) }}" class="d-flex gap-2 mt-3 pt-3 border-top">
                @csrf
                <input name="name" class="form-control form-control-sm" placeholder="ชื่อช่องใหม่ เช่น งานกลุ่ม" required>
                <input name="max_score" type="number" step="0.5" class="form-control form-control-sm" style="width:90px" placeholder="เต็ม" required>
                <button class="btn btn-sm btn-primary text-nowrap"><i class="bi bi-plus"></i> เพิ่ม</button>
            </form>
        </div>
    </div></div>
</div>
@endunless
@endsection
