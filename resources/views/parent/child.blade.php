@extends('layouts.app')
@section('title', $student->nickname ?: $student->first_name)

@section('content')
@php
    $score = $student->behaviorScore();
    $scoreColor = $score >= 80 ? '#10b981' : ($score >= 60 ? '#f59e0b' : '#ef4444');
    $termTotal = $termAtt->sum();
    $start = $month->copy()->startOfMonth();
    $prevMonth = $month->copy()->subMonth()->format('Y-m');
    $nextMonth = $month->copy()->addMonth()->format('Y-m');
    $tabs = ['overview' => 'การมาเรียน', 'grades' => 'ผลการเรียน', 'behavior' => 'ความประพฤติ', 'timetable' => 'ตารางเรียน', 'fees' => 'ค่าธรรมเนียม', 'health' => 'สุขภาพ', 'survey' => 'แบบประเมิน'];
    // นักเรียนดูของตัวเอง: ค่าธรรมเนียมดูได้อย่างเดียว (ผู้ปกครองเป็นผู้ชำระ) · แบบประเมิน = ฉบับที่นักเรียนประเมินตนเอง
    if (($isStudent ?? false) && $surveys->isEmpty()) {
        unset($tabs['survey']);
    }
    $tabs['leaves'] = 'ใบลา';
    $msCount = collect($periodSummary ?? [])->filter(fn ($s) => $s['ms'] ?? false)->count();
@endphp

@php
    $siblings = auth()->user()->children;
@endphp
@if ($siblings->count() > 1)
    <div class="child-switch mb-3">
        @foreach ($siblings as $sib)
            <a href="{{ route('parent.child', ['student' => $sib, 'tab' => $tab]) }}" class="{{ $sib->id === $student->id ? 'active' : '' }}">
                <span class="sb-avatar sm">{{ $sib->initials() }}</span> น้อง{{ $sib->nickname ?: $sib->first_name }}
            </a>
        @endforeach
    </div>
@endif
<div class="card child-card mb-3">
    <div class="head">
        <span class="sb-avatar">@if($student->photoUrl())<img src="{{ $student->photoUrl() }}" alt="">@else{{ $student->initials() }}@endif</span>
        <div class="flex-grow-1">
            <div class="fw-bold fs-5">{{ $student->fullName() }}</div>
            <div class="small opacity-75">ห้อง {{ $student->classroom?->name() }} · เลขที่ {{ $student->number }} · รหัส {{ $student->student_code }}</div>
        </div>
    </div>
    <div class="card-body row text-center g-2">
        <div class="col-4"><div class="fs-4 fw-bold text-success">{{ $termTotal ? round((($termAtt['present'] ?? 0) + ($termAtt['late'] ?? 0)) / $termTotal * 100) : '-' }}%</div><div class="small text-muted">มาเรียน</div></div>
        <div class="col-4"><div class="fs-4 fw-bold text-primary">{{ $gpa !== null ? number_format($gpa, 2) : '-' }}</div><div class="small text-muted">เกรดเฉลี่ย</div></div>
        <div class="col-4"><div class="fs-4 fw-bold" style="color:{{ $scoreColor }}">{{ $score }}</div><div class="small text-muted">ความประพฤติ</div></div>
    </div>
</div>

<div class="chip-grid mb-3" style="grid-template-columns:repeat(3,1fr)">
    <a href="{{ route(($isStudent ?? false) ? 'student.homework' : 'parent.homework') }}" class="chip"><i class="bi bi-journal-text tint-primary"></i>การบ้าน</a>
    <a href="{{ route('portfolio.show', $student) }}" class="chip"><i class="bi bi-folder2-open tint-warning"></i>แฟ้มผลงาน</a>
    @if ($isStudent ?? false)
        <a href="{{ route('transcript', $student) }}" class="chip"><i class="bi bi-file-earmark-text tint-teal"></i>ปพ.1</a>
    @else
        <a href="{{ route('chat.index') }}" class="chip"><i class="bi bi-chat-dots tint-blue"></i>คุยกับครู</a>
    @endif
</div>

@if ($msCount)
    <div class="alert alert-danger d-flex gap-2 align-items-center">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>เวลาเรียนต่ำกว่า {{ \App\Models\PeriodAttendance::MIN_PERCENT }}% อยู่ <b>{{ $msCount }} วิชา</b> — เสี่ยงไม่มีสิทธิ์สอบ (มส.) ดูรายละเอียดที่แท็บ "ผลการเรียน"</div>
    </div>
@endif

<ul class="nav nav-pills mb-3 gap-1 flex-nowrap overflow-auto">
    @foreach ($tabs as $k => $v)
        <li class="nav-item"><button class="nav-link text-nowrap {{ $tab === $k ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#p-{{ $k }}">{{ $v }}</button></li>
    @endforeach
</ul>

<div class="tab-content">
    <div class="tab-pane fade {{ $tab === 'overview' ? 'show active' : '' }}" id="p-overview">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <a href="?month={{ $prevMonth }}" class="btn btn-sm btn-light"><i class="bi bi-chevron-left"></i></a>
                        <span class="mx-auto">{{ \App\Support\Thai::monthYear($month->month, $month->year) }}</span>
                        <a href="?month={{ $nextMonth }}" class="btn btn-sm btn-light"><i class="bi bi-chevron-right"></i></a>
                    </div>
                    <div class="card-body">
                        <div class="cal">
                            @foreach (['อา', 'จ', 'อ', 'พ', 'พฤ', 'ศ', 'ส'] as $d)<div class="dow">{{ $d }}</div>@endforeach
                            @for ($i = 0; $i < $start->dayOfWeek; $i++)<div></div>@endfor
                            @for ($day = 1; $day <= $month->daysInMonth; $day++)
                                @php
                                    $date = $start->copy()->day($day);
                                    $rec = $monthAtt[$date->toDateString()] ?? null;
                                @endphp
                                <div class="d {{ $date->isWeekend() ? 'weekend' : '' }} {{ $date->isToday() ? 'today' : '' }} {{ $rec ? 's-'.$rec->status : '' }}" title="{{ $rec ? \App\Models\Attendance::label($rec->status) : '' }}">{{ $day }}</div>
                            @endfor
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-3 small">
                            @foreach (\App\Models\Attendance::STATUSES as $k => [$label])
                                <span class="d-flex align-items-center gap-1"><span class="att-cell s-{{ $k }}" style="width:14px;height:14px"></span>{{ $label }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><i class="bi bi-pie-chart"></i> สรุป{{ $term?->label() }}</div>
                    <ul class="list-group list-group-flush">
                        @foreach (\App\Models\Attendance::STATUSES as $k => [$label, , $color])
                            <li class="list-group-item d-flex justify-content-between"><span><span class="badge bg-{{ $color }} me-2">&nbsp;</span>{{ $label }}</span><b>{{ $termAtt[$k] ?? 0 }} วัน</b></li>
                        @endforeach
                    </ul>
                </div>
                @php($notPresent = $monthAtt->where('status', '!=', 'present'))
                @if ($notPresent->isNotEmpty())
                    <div class="card mt-3">
                        <div class="card-header"><i class="bi bi-list-ul"></i> รายละเอียดเดือนนี้</div>
                        @foreach ($notPresent as $a)
                            <div class="px-3 py-2 border-bottom small d-flex gap-2">
                                <span class="badge bg-{{ \App\Models\Attendance::color($a->status) }}">{{ \App\Models\Attendance::label($a->status) }}</span>
                                {{ \App\Support\Thai::fullDate($a->date) }} @if($a->note)<span class="text-muted">· {{ $a->note }}</span>@endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="tab-pane fade {{ $tab === 'grades' ? 'show active' : '' }}" id="p-grades">
        <div class="card">
            <div class="card-header"><i class="bi bi-mortarboard"></i> {{ $term?->label() }}
                <a href="{{ route('report-card', $student) }}" class="ms-auto btn btn-sm btn-light border"><i class="bi bi-printer"></i> สมุดพก</a>
            </div>
            @if ($resultsHidden ?? false)<div class="alert alert-info m-3 mb-0 small"><i class="bi bi-calendar-event"></i> โรงเรียนจะประกาศผลการเรียนวันที่ {{ thai_date($term->results_announce_on) }}</div>@endif
            @forelse ($grades as $g)
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $g['course']->subject->name }}</div>
                        <div class="small text-muted">{{ $g['course']->subject->code }} · {{ $g['course']->subject->credit }} หน่วยกิต · {{ $g['course']->teacher?->name }}</div>
                        @if ($ps = ($periodSummary[$g['course']->id] ?? null))
                            <div class="small mt-1">
                                <span class="text-{{ $ps['ms'] ? 'danger' : 'success' }}"><i class="bi bi-clock-history"></i> เข้าเรียน {{ $ps['percent'] }}%</span>
                                <span class="text-muted">({{ $ps['came'] }}/{{ $ps['total'] }} คาบ{{ $ps['absent'] ? ' · ขาด '.$ps['absent'] : '' }})</span>
                                @if ($ps['ms'])<span class="badge bg-danger ms-1">มส.</span>@endif
                            </div>
                        @endif
                    </div>
                    <div class="text-end small text-muted" style="width:70px">{{ $g['total'] !== null ? $g['total'].'/'.$g['max'] : '' }}</div>
                    <span class="text-nowrap"><x-grade :grade="$g['grade']" :original="$g['original']" /></span>
                </div>
            @empty
                <div class="empty"><i class="bi bi-journal"></i>ยังไม่มีผลการเรียน</div>
            @endforelse
        </div>

        @if (($examResults ?? collect())->isNotEmpty())
            <div class="card mt-3">
                <div class="card-header"><i class="bi bi-ui-checks-grid"></i> ผลสอบ</div>
                @foreach ($examResults as $er)
                    <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $er->exam->title }} · {{ $er->exam->subject->name }}</div>
                            <div class="small text-muted">{{ $er->exam->exam_date ? thai_date($er->exam->exam_date) : '' }}</div>
                        </div>
                        <div class="fw-bold">{{ rtrim(rtrim(number_format($er->score, 2), '0'), '.') }} <small class="text-muted fw-normal">/ {{ rtrim(rtrim(number_format($er->max_score, 2), '0'), '.') }}</small></div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="tab-pane fade {{ $tab === 'behavior' ? 'show active' : '' }}" id="p-behavior">
        <div class="card">
            <div class="card-header"><i class="bi bi-award"></i> บันทึกความประพฤติ <span class="ms-auto">คะแนนคงเหลือ <b style="color:{{ $scoreColor }}">{{ $score }}</b></span></div>
            @forelse ($student->behaviorRecords as $b)
                <div class="d-flex gap-2 align-items-center px-3 py-2 border-bottom">
                    <span class="badge {{ $b->points > 0 ? 'bg-success' : 'bg-danger' }}" style="width:44px">{{ $b->points > 0 ? '+' : '' }}{{ $b->points }}</span>
                    <div><div class="fw-semibold">{{ $b->title }}</div><div class="small text-muted">{{ thai_date($b->date) }} @if($b->note)· {{ $b->note }}@endif</div></div>
                </div>
            @empty
                <div class="empty"><i class="bi bi-emoji-smile"></i>ยังไม่มีบันทึก</div>
            @endforelse
        </div>
    </div>

    <div class="tab-pane fade {{ $tab === 'timetable' ? 'show active' : '' }}" id="p-timetable">
        <div class="card">
            <div class="table-responsive">
                <table class="table tt mb-0">
                    <thead><tr><th class="day">วัน</th>@foreach ($periods as $i => $t)<th>คาบ {{ $i + 1 }}<div class="small text-muted fw-normal">{{ $t }}</div></th>@endforeach</tr></thead>
                    <tbody>
                    @foreach (\App\Models\TimetableSlot::days() as $d => $dn)
                        <tr><th class="day">{{ $dn }}</th>
                            @foreach ($periods as $i => $t)
                                @php($s = $slots[$d.'-'.($i + 1)] ?? null)
                                <td class="p-1">@if($s)<div class="slot" style="background:#eef2ff"><b>{{ $s->course?->subject->name ?? $s->label }}</b><small>{{ $s->course?->teacher?->name }}</small></div>@endif</td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade {{ $tab === 'fees' ? 'show active' : '' }}" id="p-fees">
        @include('scholarships._student', ['all' => false])
        <div class="card">
            @if ($isStudent ?? false)
                @php($owed = $student->invoices->where('status', '!=', 'void')->sum(fn ($i) => max(0, $i->balance())))
                <div class="card-header"><i class="bi bi-wallet2"></i> ค่าธรรมเนียมของฉัน
                    <span class="ms-auto small fw-normal {{ $owed > 0 ? 'text-danger' : 'text-success' }}">{{ $owed > 0 ? 'ค้างชำระรวม '.baht($owed) : 'ไม่มียอดค้างชำระ' }}</span>
                </div>
                <div class="px-3 py-2 small text-muted border-bottom">ดูได้อย่างเดียว · การชำระเงินและแนบสลิปทำโดยผู้ปกครอง</div>
            @endif
            @forelse ($student->invoices->where('status', '!=', 'void') as $inv)
                @if ($isStudent ?? false)<div class="d-flex align-items-center gap-2 px-3 py-3 border-bottom">@else<a href="{{ route('invoices.show', $inv) }}" class="d-flex align-items-center gap-2 px-3 py-3 border-bottom text-decoration-none text-body">@endif
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $inv->title }}</div>
                        <div class="small text-muted">{{ $inv->invoice_no }} · กำหนดชำระ {{ $inv->due_date ? thai_date($inv->due_date) : '-' }}</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold">{{ baht($inv->balance() > 0 ? $inv->balance() : $inv->netTotal()) }}</div>
                        <span class="badge bg-{{ $inv->statusColor() }}">{{ $inv->statusLabel() }}</span>
                    </div>
                @if ($isStudent ?? false)</div>@else</a>@endif
            @empty
                <div class="empty"><i class="bi bi-receipt"></i>ไม่มีรายการ</div>
            @endforelse
        </div>
    </div>

    <div class="tab-pane fade {{ $tab === 'leaves' ? 'show active' : '' }}" id="p-leaves">
        <div class="card">
            <div class="card-header"><i class="bi bi-envelope-paper"></i> ใบลา
                @unless ($isStudent ?? false)<a href="{{ route('parent.leave', ['student' => $student->id]) }}" class="ms-auto small fw-normal">+ ส่งใบลา</a>@endunless
            </div>
            @if ($isStudent ?? false)<div class="px-3 py-2 small text-muted border-bottom">ผู้ปกครองเป็นผู้ส่งใบลา · ที่นี่ดูได้ว่าครูอนุมัติแล้วหรือยัง</div>@endif
            @forelse ($leaves ?? [] as $l)
                <div class="d-flex align-items-center gap-2 px-3 py-3 border-bottom">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $l->typeLabel() }} · {{ thai_date($l->start_date) }}@if ($l->days() > 1) – {{ thai_date($l->end_date) }} ({{ $l->days() }} วัน)@endif</div>
                        <div class="small text-muted">{{ $l->reason }}</div>
                    </div>
                    <span class="badge bg-{{ $l->statusColor() }}">{{ $l->statusLabel() }}</span>
                </div>
            @empty
                <div class="empty"><i class="bi bi-envelope-paper"></i>ยังไม่มีใบลา</div>
            @endforelse
        </div>
    </div>

    @unless (($isStudent ?? false) && $surveys->isEmpty())

    <div class="tab-pane fade {{ $tab === 'survey' ? 'show active' : '' }}" id="p-survey">
        <div class="card">
            <div class="card-header"><i class="bi bi-clipboard-heart"></i> {{ ($isStudent ?? false) ? 'แบบประเมินตนเอง' : 'แบบประเมินที่ผู้ปกครองตอบได้' }}</div>
            @forelse ($surveys as $sv)
                @php($resp = $surveyResponses[$sv->id] ?? null)
                <div class="d-flex align-items-center gap-2 px-3 py-3 border-bottom">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $sv->title }}</div>
                        <div class="small text-muted">{{ $resp ? 'ตอบแล้ว '.thai_date($resp->updated_at) : 'ยังไม่ได้ตอบ' }}</div>
                    </div>
                    <a href="{{ route('surveys.fill', [$sv, $student]) }}" class="btn btn-sm {{ $resp ? 'btn-light border' : 'btn-primary' }}">{{ $resp ? 'แก้ไขคำตอบ' : 'ทำแบบประเมิน' }}</a>
                </div>
            @empty
                <div class="empty py-4"><i class="bi bi-clipboard"></i>ยังไม่มีแบบประเมินสำหรับผู้ปกครอง</div>
            @endforelse
        </div>
    </div>
    @endunless

    <div class="tab-pane fade {{ $tab === 'health' ? 'show active' : '' }}" id="p-health">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><i class="bi bi-rulers"></i> น้ำหนัก / ส่วนสูง</div>
                    @forelse ($student->measurements as $m)
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom small">
                            <span>{{ thai_date($m->measured_on) }}</span><span>{{ $m->weight }} กก. · {{ $m->height }} ซม.</span>
                            <span class="badge bg-{{ $m->bmiLabel()[1] }}">{{ $m->bmiLabel()[0] }}</span>
                        </div>
                    @empty
                        <div class="empty py-4"><i class="bi bi-rulers"></i>ยังไม่มีข้อมูล</div>
                    @endforelse
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><i class="bi bi-heart-pulse"></i> การมาห้องพยาบาล</div>
                    @forelse ($student->healthVisits->take(10) as $v)
                        <div class="px-3 py-2 border-bottom small">
                            <b>{{ $v->symptom }}</b> · <span class="text-{{ $v->actionColor() }}">{{ $v->actionLabel() }}</span>
                            <div class="text-muted">{{ thai_datetime($v->visited_at) }} @if($v->treatment)· {{ $v->treatment }}@endif @if($v->medicine)· ยา: {{ $v->medicine }}@endif</div>
                        </div>
                    @empty
                        <div class="empty py-4"><i class="bi bi-emoji-smile"></i>ไม่เคยมาห้องพยาบาล</div>
                    @endforelse
                </div>
                @if ($student->bookLoans->whereNull('returned_on')->isNotEmpty())
                    <div class="card mt-3">
                        <div class="card-header"><i class="bi bi-book-half"></i> หนังสือห้องสมุดที่ยืมอยู่</div>
                        @foreach ($student->bookLoans->whereNull('returned_on') as $l)
                            <div class="d-flex justify-content-between px-3 py-2 border-bottom small {{ $l->isOverdue() ? 'text-danger fw-semibold' : '' }}">
                                <span>{{ $l->book->title }}</span><span>คืน {{ thai_date($l->due_on) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
