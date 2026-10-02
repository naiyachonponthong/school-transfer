@extends('layouts.app')
@section('title', $student->fullName())

@section('content')
@php
    $score = $student->behaviorScore();
    $scoreColor = $score >= 80 ? '#10b981' : ($score >= 60 ? '#f59e0b' : '#ef4444');
    $attTotal = $attCounts->sum();
    $attPct = $attTotal ? round((($attCounts['present'] ?? 0) + ($attCounts['late'] ?? 0)) / $attTotal * 100, 1) : null;
@endphp

<div class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <span class="sb-avatar lg">@if($student->photoUrl())<img src="{{ $student->photoUrl() }}" alt="">@else{{ $student->initials() }}@endif</span>
        <div class="flex-grow-1">
            <h1 class="h4 fw-bold mb-1">{{ $student->fullName() }} @if($student->nickname)<span class="text-muted fw-normal fs-6">({{ $student->nickname }})</span>@endif</h1>
            <div class="text-muted d-flex flex-wrap gap-3 small">
                <span><i class="bi bi-hash"></i> {{ $student->student_code }}</span>
                <span><i class="bi bi-door-open"></i> {{ $student->classroom ? 'ห้อง '.$student->classroom->name().' เลขที่ '.$student->number : 'ยังไม่มีห้อง' }}</span>
                @if ($student->age())<span><i class="bi bi-cake2"></i> {{ $student->age() }} ปี</span>@endif
                <span class="badge bg-{{ $student->status === 'active' ? 'success' : 'secondary' }}">{{ \App\Models\Student::STATUSES[$student->status] }}</span>
            </div>
            @if ($student->medical_note)
                <div class="mt-2 small text-danger"><i class="bi bi-heart-pulse-fill"></i> {{ $student->medical_note }}</div>
            @endif
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('report-card', ['student' => $student, 'term' => $term?->id]) }}" class="btn btn-light border"><i class="bi bi-file-earmark-text"></i> สมุดพก</a>
            <a href="{{ route('transcript', $student) }}" class="btn btn-light border"><i class="bi bi-journal-text"></i> ปพ.1</a>
            @if (auth()->user()->isAdmin())<a href="{{ route('certificates.create', $student) }}" class="btn btn-light border"><i class="bi bi-file-earmark-check"></i> ปพ.7</a>
                <a href="{{ route('audit.index', ['subject' => 'Student:'.$student->id]) }}" class="btn btn-light border" title="ประวัติการแก้ไข"><i class="bi bi-clock-history"></i></a>@endif
            <a href="{{ route('portfolio.show', $student) }}" class="btn btn-light border"><i class="bi bi-folder2-open"></i> แฟ้มผลงาน</a>
            @if ($student->guardians->isNotEmpty())
                <form method="POST" action="{{ route('chat.start') }}">@csrf<input type="hidden" name="student_id" value="{{ $student->id }}">
                    <button class="btn btn-light border"><i class="bi bi-chat-dots"></i> ส่งข้อความผู้ปกครอง</button></form>
            @endif
            <a href="{{ route('students.edit', $student) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> แก้ไข</a>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-success"><i class="bi bi-calendar-check"></i></div><div><div class="stat-value">{{ $attPct !== null ? $attPct.'%' : '-' }}</div><div class="stat-label">มาเรียน (ภาคนี้)</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-danger"><i class="bi bi-calendar-x"></i></div><div><div class="stat-value">{{ $attCounts['absent'] ?? 0 }}</div><div class="stat-label">ขาด · สาย {{ $attCounts['late'] ?? 0 }}</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-mortarboard"></i></div><div><div class="stat-value">{{ $gpa !== null ? number_format($gpa, 2) : '-' }}</div><div class="stat-label">เกรดเฉลี่ย (GPA)</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-award"></i></div><div class="flex-grow-1"><div class="stat-value" style="color:{{ $scoreColor }}">{{ $score }}</div><div class="behavior-meter mt-1"><span style="width:{{ max(0, min(100, $score)) }}%;background:{{ $scoreColor }}"></span></div><div class="stat-label">คะแนนความประพฤติ</div></div></div></div></div>
</div>

<ul class="nav nav-pills mb-3 gap-1 flex-nowrap overflow-auto" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#t-info">ข้อมูลทั่วไป</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#t-att">การมาเรียน</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#t-grade">ผลการเรียน</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#t-beh">ความประพฤติ</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#t-fin">ค่าธรรมเนียม</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#t-health">สุขภาพ/ห้องสมุด</button></li>
</ul>

<div class="tab-content">
    {{-- ข้อมูลทั่วไป --}}
    <div class="tab-pane fade show active" id="t-info">
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><i class="bi bi-person-vcard"></i> ข้อมูลส่วนตัว</div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4 text-muted fw-normal">เลขประจำตัวประชาชน</dt><dd class="col-sm-8">{{ $student->citizen_id ?: '-' }}</dd>
                            <dt class="col-sm-4 text-muted fw-normal">วันเกิด</dt><dd class="col-sm-8">{{ $student->birthdate ? thai_date($student->birthdate, true) : '-' }}</dd>
                            <dt class="col-sm-4 text-muted fw-normal">เพศ</dt><dd class="col-sm-8">{{ ['M' => 'ชาย', 'F' => 'หญิง'][$student->gender] ?? '-' }}</dd>
                            <dt class="col-sm-4 text-muted fw-normal">กรุ๊ปเลือด</dt><dd class="col-sm-8">{{ $student->blood_type ?: '-' }}</dd>
                            <dt class="col-sm-4 text-muted fw-normal">เบอร์โทร</dt><dd class="col-sm-8">{{ $student->phone ?: '-' }}</dd>
                            <dt class="col-sm-4 text-muted fw-normal">ที่อยู่</dt><dd class="col-sm-8">{{ $student->address ?: '-' }}</dd>
                            <dt class="col-sm-4 text-muted fw-normal">ครูประจำชั้น</dt><dd class="col-sm-8">{{ $student->classroom?->homeroomTeacher?->name ?? '-' }}</dd>
                            @php
                                $pp1 = array_filter([
                                    'สัญชาติ / เชื้อชาติ / ศาสนา' => collect([$student->nationality, $student->ethnicity, $student->religion])->map(fn ($v) => $v ?: '-')->implode(' / '),
                                    'บิดา' => $student->father_name,
                                    'มารดา' => $student->mother_name,
                                    'วันเข้าเรียน' => $student->admitted_on ? thai_date($student->admitted_on, true) : null,
                                    'โรงเรียนเดิม' => collect([$student->previous_school, $student->previous_level, $student->previous_school_province ? 'จ.'.$student->previous_school_province : null])->filter()->implode(' · '),
                                    'วันที่จบ/ออก' => $student->left_on ? thai_date($student->left_on, true).($student->leave_reason ? ' ('.$student->leave_reason.')' : '') : null,
                                ], fn ($v) => $v !== null && $v !== '' && $v !== '- / - / -');
                            @endphp
                            @foreach ($pp1 as $label => $value)
                                <dt class="col-sm-4 text-muted fw-normal">{{ $label }}</dt><dd class="col-sm-8">{{ $value }}</dd>
                            @endforeach
                        </dl>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="bi bi-person-hearts"></i> ผู้ปกครอง</div>
                    @forelse ($student->guardians as $g)
                        <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                            <span class="sb-avatar sm">{{ $g->initials() }}</span>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $g->name }} <span class="small text-muted fw-normal">{{ $g->pivot->relation }}</span></div>
                                <div class="small">@if($g->phone)<a href="tel:{{ $g->phone }}"><i class="bi bi-telephone"></i> {{ $g->phone }}</a>@endif
                                    <span class="text-muted ms-1">{{ $g->last_login_at ? 'เข้าระบบล่าสุด '.\App\Support\Thai::ago($g->last_login_at) : 'ยังไม่เคยเข้าระบบ' }}</span></div>
                            </div>
                            <form method="POST" action="{{ route('students.guardians.destroy', [$student, $g]) }}" data-confirm="นำ {{ $g->name }} ออกจากผู้ปกครองของนักเรียนคนนี้?">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-link text-danger"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </div>
                    @empty
                        <div class="px-3 py-3 text-muted small">ยังไม่มีผู้ปกครองในระบบ</div>
                    @endforelse
                    <div class="card-body">
                        <form method="POST" action="{{ route('students.guardians.store', $student) }}" class="row g-2">
                            @csrf
                            <div class="col-12 small fw-semibold">เพิ่มผู้ปกครอง</div>
                            <div class="col-7"><input name="guardian_name" class="form-control form-control-sm" placeholder="ชื่อ-สกุล"></div>
                            <div class="col-5"><input name="guardian_phone" class="form-control form-control-sm" placeholder="เบอร์โทร" inputmode="tel"></div>
                            <div class="col-7"><input name="relation" class="form-control form-control-sm" placeholder="ความสัมพันธ์ เช่น มารดา"></div>
                            <div class="col-5"><button class="btn btn-sm btn-primary w-100"><i class="bi bi-plus"></i> เพิ่ม</button></div>
                            <div class="col-12 small text-muted">ถ้าเบอร์นี้มีบัญชีอยู่แล้ว (เช่น พี่น้อง) ระบบจะผูกบัญชีเดิมให้อัตโนมัติ</div>
                        </form>
                    </div>
                </div>

                @if (auth()->user()->isAdmin() || $student->classroom?->isManagedBy(auth()->user()))
                    @php($acc = $student->user)
                    <div class="card mt-3">
                        <div class="card-header"><i class="bi bi-person-badge"></i> บัญชีนักเรียน</div>
                        <div class="card-body">
                            @if ($acc)
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="flex-grow-1 small">
                                        ชื่อผู้ใช้ <code class="fs-6">{{ $acc->username }}</code>
                                        <span class="badge bg-{{ $acc->is_active ? 'success' : 'secondary' }} ms-1">{{ $acc->is_active ? 'ใช้งานได้' : 'ปิดใช้งาน' }}</span>
                                        <div class="text-muted">{{ $acc->last_login_at ? 'เข้าระบบล่าสุด '.\App\Support\Thai::ago($acc->last_login_at) : 'ยังไม่เคยเข้าระบบ' }}</div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="{{ route('student-accounts.reset', $student) }}" data-confirm="ออกรหัสผ่านใหม่ให้ {{ $student->fullName() }}? รหัสเดิมจะใช้ไม่ได้">@csrf
                                        <button class="btn btn-sm btn-light border"><i class="bi bi-key"></i> รีเซ็ตรหัสผ่าน</button></form>
                                    <form method="POST" action="{{ route('student-accounts.toggle', $student) }}">@csrf
                                        <button class="btn btn-sm btn-link {{ $acc->is_active ? 'text-danger' : '' }}">{{ $acc->is_active ? 'ปิดใช้งาน' : 'เปิดใช้งาน' }}</button></form>
                                </div>
                            @else
                                <p class="small text-muted mb-2">ให้นักเรียนเข้าระบบดูเกรด ตารางเรียน เวลาเรียน และส่งการบ้านเองได้</p>
                                <form method="POST" action="{{ route('student-accounts.reset', $student) }}">@csrf
                                    <button class="btn btn-sm btn-primary"><i class="bi bi-person-plus"></i> สร้างบัญชีนักเรียน</button></form>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- การมาเรียน --}}
    <div class="tab-pane fade" id="t-att">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-calendar-check"></i> ประวัติการมาเรียน
                <form class="ms-auto" method="GET"><select name="term" class="form-select form-select-sm" data-autosubmit>
                    @foreach ($terms as $t)<option value="{{ $t->id }}" @selected($term?->id === $t->id)>{{ $t->label() }}</option>@endforeach
                </select></form>
            </div>
            <div class="card-body d-flex flex-wrap gap-2 border-bottom">
                @foreach (\App\Models\Attendance::STATUSES as $k => [$label, , $color])
                    <span class="badge bg-{{ $color }}-subtle text-{{ $color }}-emphasis fs-6 fw-normal">{{ $label }} {{ $attCounts[$k] ?? 0 }}</span>
                @endforeach
            </div>
            <div class="table-responsive" style="max-height:420px">
                <table class="table table-sm align-middle">
                    <tbody>
                    @forelse ($attendance->where('status', '!=', 'present') as $a)
                        <tr><td style="width:160px">{{ \App\Support\Thai::fullDate($a->date) }}</td><td><span class="badge bg-{{ \App\Models\Attendance::color($a->status) }}">{{ \App\Models\Attendance::label($a->status) }}</span></td><td class="text-muted small">{{ $a->note }}</td></tr>
                    @empty
                        <tr><td><div class="empty py-3"><i class="bi bi-emoji-smile"></i>มาเรียนปกติทุกวัน</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ผลการเรียน --}}
    <div class="tab-pane fade" id="t-grade">
        <div class="card">
            <div class="card-header"><i class="bi bi-mortarboard"></i> ผลการเรียน {{ $term?->label() }} <span class="ms-auto">GPA <b>{{ $gpa !== null ? number_format($gpa, 2) : '-' }}</b></span></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>รหัสวิชา</th><th>รายวิชา</th><th class="text-center">หน่วยกิต</th><th>ครูผู้สอน</th><th class="text-center">คะแนน</th><th class="text-center">เกรด</th></tr></thead>
                    <tbody>
                    @forelse ($grades as $g)
                        <tr>
                            <td class="text-muted">{{ $g['course']->subject->code }}</td>
                            <td>{{ $g['course']->subject->name }}</td>
                            <td class="text-center">{{ $g['course']->subject->credit }}</td>
                            <td class="small">{{ $g['course']->teacher?->name ?? '-' }}</td>
                            <td class="text-center">{{ $g['total'] !== null ? $g['total'].' / '.$g['max'] : '-' }}</td>
                            <td class="text-center text-nowrap"><x-grade :grade="$g['grade']" :original="$g['original']" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty"><i class="bi bi-journal"></i>ยังไม่มีรายวิชาในภาคเรียนนี้</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ความประพฤติ --}}
    <div class="tab-pane fade" id="t-beh">
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header"><i class="bi bi-plus-circle"></i> บันทึกพฤติกรรม</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('behavior.store') }}">
                            @csrf
                            <input type="hidden" name="student_ids[]" value="{{ $student->id }}">
                            <div class="d-flex flex-wrap gap-2 mb-3">
                                @foreach ($rules as $r)
                                    <button name="behavior_rule_id" value="{{ $r->id }}" class="btn btn-sm {{ $r->points > 0 ? 'btn-outline-success' : 'btn-outline-danger' }}">{{ $r->name }} <b>{{ $r->points > 0 ? '+' : '' }}{{ $r->points }}</b></button>
                                @endforeach
                            </div>
                        </form>
                        <form method="POST" action="{{ route('behavior.store') }}" class="border-top pt-3">
                            @csrf
                            <input type="hidden" name="student_ids[]" value="{{ $student->id }}">
                            <div class="small fw-semibold mb-2">หรือระบุเอง</div>
                            <div class="row g-2">
                                <div class="col-8"><input name="title" class="form-control form-control-sm" placeholder="เรื่อง" required></div>
                                <div class="col-4"><input type="number" name="points" class="form-control form-control-sm" placeholder="+/- คะแนน" required></div>
                                <div class="col-12"><input name="note" class="form-control form-control-sm" placeholder="รายละเอียด (ไม่บังคับ)"></div>
                                <div class="col-12"><button class="btn btn-sm btn-primary w-100">บันทึก</button></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="card">
                    <div class="card-header"><i class="bi bi-clock-history"></i> ประวัติ</div>
                    @forelse ($student->behaviorRecords as $b)
                        <div class="d-flex gap-2 align-items-center px-3 py-2 border-bottom">
                            <span class="badge {{ $b->points > 0 ? 'bg-success' : 'bg-danger' }}" style="width:44px">{{ $b->points > 0 ? '+' : '' }}{{ $b->points }}</span>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $b->title }}</div>
                                <div class="small text-muted">{{ thai_date($b->date) }} · {{ $b->recorder?->name }} @if($b->note)· {{ $b->note }}@endif</div>
                            </div>
                            @if (auth()->user()->isAdmin() || $b->recorded_by === auth()->id())
                                <form method="POST" action="{{ route('behavior.destroy', $b) }}" data-confirm="ลบรายการนี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-muted"><i class="bi bi-trash"></i></button></form>
                            @endif
                        </div>
                    @empty
                        <div class="empty"><i class="bi bi-award"></i>ยังไม่มีบันทึก</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- การเงิน --}}
    <div class="tab-pane fade" id="t-fin">
        <div class="card">
            <div class="card-header"><i class="bi bi-receipt"></i> ใบแจ้งหนี้</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead><tr><th>เลขที่</th><th>รายการ</th><th class="text-end">ยอด</th><th class="text-end">ค้าง</th><th>สถานะ</th></tr></thead>
                    <tbody>
                    @forelse ($student->invoices as $inv)
                        <tr data-href="{{ route('invoices.show', $inv) }}" style="cursor:pointer">
                            <td class="text-muted">{{ $inv->invoice_no }}</td><td>{{ $inv->title }}</td>
                            <td class="text-end">{{ baht($inv->netTotal()) }}</td><td class="text-end">{{ baht($inv->balance()) }}</td>
                            <td><span class="badge bg-{{ $inv->statusColor() }}">{{ $inv->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><i class="bi bi-receipt"></i>ไม่มีใบแจ้งหนี้</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- สุขภาพ + ห้องสมุด --}}
    <div class="tab-pane fade" id="t-health">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-rulers"></i> น้ำหนัก / ส่วนสูง</div>
                    @forelse ($student->measurements as $m)
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom small">
                            <span>{{ thai_date($m->measured_on) }}</span><span>{{ $m->weight }} กก. · {{ $m->height }} ซม. · BMI {{ $m->bmi() ?? '-' }}</span>
                            <span class="badge bg-{{ $m->bmiLabel()[1] }}">{{ $m->bmiLabel()[0] }}</span>
                        </div>
                    @empty
                        <div class="px-3 py-3 small text-muted">ยังไม่มีข้อมูล</div>
                    @endforelse
                </div>
                <div class="card">
                    <div class="card-header"><i class="bi bi-heart-pulse"></i> ห้องพยาบาล</div>
                    @forelse ($student->healthVisits->take(10) as $v)
                        <div class="px-3 py-2 border-bottom small">
                            <b>{{ $v->symptom }}</b> · <span class="text-{{ $v->actionColor() }}">{{ $v->actionLabel() }}</span>
                            <div class="text-muted">{{ thai_datetime($v->visited_at) }} @if($v->medicine)· ยา: {{ $v->medicine }}@endif</div>
                        </div>
                    @empty
                        <div class="px-3 py-3 small text-muted">ไม่เคยมาห้องพยาบาล</div>
                    @endforelse
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header"><i class="bi bi-book-half"></i> ประวัติยืมหนังสือ</div>
                    @forelse ($student->bookLoans->take(15) as $l)
                        <div class="d-flex justify-content-between px-3 py-2 border-bottom small {{ $l->isOverdue() ? 'text-danger' : '' }}">
                            <span>{{ $l->book->title }}</span>
                            <span>{{ thai_date($l->borrowed_on) }} → {{ $l->returned_on ? 'คืนแล้ว' : 'กำหนด '.thai_date($l->due_on) }}</span>
                        </div>
                    @empty
                        <div class="px-3 py-3 small text-muted">ยังไม่เคยยืม</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@if (auth()->user()->isAdmin())
    <form method="POST" action="{{ route('students.destroy', $student) }}" class="mt-4 text-end" data-confirm="ลบนักเรียนคนนี้? ลบได้เฉพาะคนที่ยังไม่มีคะแนน การมาเรียน หรือใบแจ้งหนี้ (เช่น เพิ่มผิด) — ถ้าย้ายหรือลาออกให้เปลี่ยนสถานะแทน">
        @csrf @method('DELETE')
        <button class="btn btn-sm btn-link text-danger"><i class="bi bi-trash"></i> ลบนักเรียน</button>
    </form>
@endif
@endsection

@push('scripts')
<script>
// จำแท็บที่เปิดไว้ เวลา reload หลังบันทึก จะกลับมาที่เดิม
document.addEventListener('DOMContentLoaded', () => {
    const key = 'student-tab';
    try {
        const last = sessionStorage.getItem(key);
        if (last && document.querySelector(`[data-bs-target="${last}"]`)) bootstrap.Tab.getOrCreateInstance(document.querySelector(`[data-bs-target="${last}"]`)).show();
    } catch (e) {}
    document.querySelectorAll('[data-bs-toggle="pill"]').forEach(b => b.addEventListener('shown.bs.tab', () => { try { sessionStorage.setItem(key, b.dataset.bsTarget); } catch (e) {} }));
});
</script>
@endpush
