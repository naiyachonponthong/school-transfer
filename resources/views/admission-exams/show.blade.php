@extends('layouts.app')
@section('title', $round->label())

@section('content')
@php
    $fmt = fn ($v) => $v === null ? '-' : rtrim(rtrim(number_format($v, 2), '0'), '.');
    $by = $standings->countBy('result');
    $roomsText = collect($round->rooms ?? [])->map(fn ($r) => $r['name'].', '.$r['seats'])->implode("\n");
@endphp
<div class="page-head">
    <div>
        <h1>{{ $round->label() }}</h1>
        <div class="sub">
            @if ($round->exam_date)สอบ {{ thai_date($round->exam_date) }} · @endif
            {{ $exams->count() }} วิชา · ผู้เข้าสอบ {{ $takers->count() }} คน
            @if ($round->isPublished())<span class="badge text-bg-success ms-1"><i class="bi bi-megaphone"></i> ประกาศผลแล้ว {{ thai_date($round->published_at) }}</span>@endif
        </div>
    </div>
    <div class="actions">
        <a href="{{ route('admission-exams.index', ['year' => $round->year]) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> ทุกชั้น</a>
        <a href="{{ route('admissions.index', ['year' => $round->year, 'level' => $round->level]) }}" class="btn btn-light border"><i class="bi bi-person-lines-fill"></i> ใบสมัครชั้นนี้</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-blue"><i class="bi bi-person-lines-fill"></i></div><div><div class="stat-value">{{ number_format($counts['applied']) }}</div><div class="stat-label">ส่งใบสมัคร</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-person-check"></i></div><div><div class="stat-value">{{ number_format($takers->count()) }}</div><div class="stat-label">ได้เลขประจำตัวสอบ</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-value {{ $counts['waiting'] ? 'text-danger' : '' }}">{{ number_format($counts['waiting']) }}</div><div class="stat-label">มีสิทธิ์สอบ ยังไม่มีเลข</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-teal"><i class="bi bi-cash-coin"></i></div><div><div class="stat-value">{{ number_format($counts['unpaid']) }}</div><div class="stat-label">ค้างค่าสมัคร (ยังไม่มีสิทธิ์)</div></div></div></div></div>
</div>

<ul class="nav nav-pills mb-3 gap-1 flex-nowrap overflow-auto" role="tablist">
    <li class="nav-item"><button class="nav-link text-nowrap {{ $tab === 'seats' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#t-seats"><i class="bi bi-door-open"></i> 1. ห้องสอบ / ผู้เข้าสอบ</button></li>
    <li class="nav-item"><button class="nav-link text-nowrap {{ $tab === 'subjects' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#t-subjects"><i class="bi bi-ui-checks-grid"></i> 2. วิชาสอบ / ตรวจกระดาษ
        @if ($exams->sum('review_count'))<span class="badge bg-danger">{{ $exams->sum('review_count') }}</span>@endif</button></li>
    <li class="nav-item"><button class="nav-link text-nowrap {{ $tab === 'results' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#t-results"><i class="bi bi-trophy"></i> 3. จัดอันดับ / ประกาศผล</button></li>
</ul>

<div class="tab-content">
    {{-- ======================= 1. ห้องสอบ ======================= --}}
    <div class="tab-pane fade {{ $tab === 'seats' ? 'show active' : '' }}" id="t-seats">
        <div class="row g-3">
            <div class="col-lg-4">
                <form method="POST" action="{{ route('admission-exams.seats', $round) }}" class="card side-sticky">
                    @csrf
                    <div class="card-header"><i class="bi bi-grid-3x3-gap"></i> จัดห้องสอบและออกเลขประจำตัวสอบ</div>
                    <div class="card-body">
                        <label class="form-label">ห้องสอบ <span class="text-muted small">บรรทัดละห้อง: ชื่อห้อง, จำนวนที่นั่ง</span></label>
                        <textarea name="rooms" rows="5" class="form-control font-monospace @error('rooms') is-invalid @enderror" placeholder="321, 30&#10;322, 30&#10;หอประชุม, 60" required>{{ old('rooms', $roomsText) }}</textarea>
                        @error('rooms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">ที่นั่งรวม {{ $round->seatCount() }} ที่ · ไม่เกิน 99 ที่ต่อห้อง (กระดาษคำตอบระบายเลขที่ได้ 2 หลัก)</div>
                        <div class="row g-2 mt-1">
                            <div class="col-6"><label class="form-label">เลขประจำตัวสอบคนแรก</label>
                                <input type="number" name="exam_no_start" min="1" max="99999" value="{{ old('exam_no_start', $round->exam_no_start ?? $round->defaultExamNoStart()) }}" class="form-control font-monospace @error('exam_no_start') is-invalid @enderror" required>
                                @error('exam_no_start')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                            <div class="col-6"><label class="form-label">วันสอบ</label><input type="date" name="exam_date" value="{{ old('exam_date', $round->exam_date?->toDateString()) }}" class="form-control"></div>
                        </div>
                        <label class="form-label mt-2">เรียงเลขตาม</label>
                        <div class="d-flex gap-3">
                            <label class="form-check"><input type="radio" name="order_by" value="app_no" class="form-check-input" @checked($round->order_by !== 'name')> ลำดับการสมัคร</label>
                            <label class="form-check"><input type="radio" name="order_by" value="name" class="form-check-input" @checked($round->order_by === 'name')> ชื่อ (ก–ฮ)</label>
                        </div>
                        @error('mode')<div class="alert alert-danger small py-2 mt-2 mb-0">{{ $message }}</div>@enderror
                        <div class="small text-muted mt-2">ผู้มีสิทธิ์สอบ = ส่งใบสมัครแล้ว สถานะ "ส่งใบสมัครแล้ว/กำลังตรวจสอบ" และชำระค่าสมัครแล้ว (ถ้ามี)</div>
                    </div>
                    <div class="card-footer bg-transparent d-grid gap-2">
                        <button name="mode" value="append" class="btn btn-primary" @disabled(! $counts['waiting'])><i class="bi bi-person-plus"></i> ออกเลขให้ผู้ที่ยังไม่มีเลข ({{ $counts['waiting'] }} คน)</button>
                        <button name="mode" value="all" class="btn btn-outline-danger" @disabled($hasScans)
                                data-confirm="จัดห้องสอบและออกเลขใหม่ทั้งหมด? เลขเดิมของทุกคนจะเปลี่ยน (ถ้าพิมพ์บัตร/กระดาษไปแล้วต้องพิมพ์ใหม่)"><i class="bi bi-arrow-repeat"></i> จัดใหม่ทั้งหมด</button>
                        @if ($hasScans)<div class="small text-muted">สแกนกระดาษคำตอบแล้ว จัดใหม่ทั้งหมดไม่ได้</div>@endif
                    </div>
                </form>
            </div>
            <div class="col-lg-8">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-printer"></i> เอกสารห้องสอบ
                        <div class="ms-auto d-flex flex-wrap gap-1">
                            @foreach (['door' => 'รายชื่อหน้าห้อง', 'sign' => 'ใบลงชื่อ', 'desk' => 'บัตรติดโต๊ะ'] as $doc => $label)
                                <a href="{{ route('admission-exams.print', [$round, $doc]) }}" target="_blank" class="btn btn-sm btn-light border @if($takers->isEmpty()) disabled @endif">{{ $label }} ทุกห้อง</a>
                            @endforeach
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-cards align-middle mb-0">
                            <thead><tr><th>ห้องสอบ</th><th class="text-center">ที่นั่ง</th><th class="text-center">จัดแล้ว</th><th class="text-end">พิมพ์เฉพาะห้อง</th></tr></thead>
                            <tbody>
                            @forelse ($round->rooms ?? [] as $r)
                                <tr>
                                    <td class="tc-title fw-semibold">{{ $r['name'] }}</td>
                                    <td class="text-center">{{ $r['seats'] }}</td>
                                    <td class="text-center">{{ $roomUse[$r['name']] ?? 0 }}</td>
                                    <td class="text-end text-nowrap">
                                        @foreach (['door' => 'หน้าห้อง', 'sign' => 'ใบลงชื่อ', 'desk' => 'บัตรโต๊ะ'] as $doc => $label)
                                            <a href="{{ route('admission-exams.print', [$round, $doc, 'room' => $r['name']]) }}" target="_blank" class="btn btn-sm btn-link px-1">{{ $label }}</a>
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><div class="empty"><i class="bi bi-door-open"></i>ยังไม่ได้ใส่ห้องสอบ</div></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><i class="bi bi-people"></i> ผู้เข้าสอบ {{ $takers->count() }} คน <span class="small text-muted fw-normal ms-1">แก้เลข/ห้องรายคนได้ในหน้าใบสมัคร</span></div>
                    <div class="table-responsive" style="max-height:640px">
                        <table class="table table-sm table-hover table-cards align-middle mb-0">
                            <thead class="sticky-top"><tr><th>เลขประจำตัวสอบ</th><th>ชื่อ-สกุล</th><th>ห้องสอบ</th><th class="text-center">ที่นั่ง</th><th>โรงเรียนเดิม</th><th>สถานะ</th></tr></thead>
                            <tbody>
                            @forelse ($takers as $a)
                                <tr data-href="{{ route('admissions.show', $a) }}" style="cursor:pointer">
                                    <td class="font-monospace fw-semibold">{{ $a->exam_no }}</td>
                                    <td class="tc-title">{{ $a->fullName() }} <span class="small text-muted">{{ $a->app_no }}</span></td>
                                    <td>{{ $a->exam_room }}</td>
                                    <td class="text-center">{{ $a->exam_seat }}</td>
                                    <td class="small">{{ $a->previous_school }}</td>
                                    <td><span class="badge bg-{{ $a->statusColor() }}-subtle text-{{ $a->statusColor() }}-emphasis">{{ $a->statusLabel() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><div class="empty"><i class="bi bi-person-badge"></i>ยังไม่มีผู้เข้าสอบ — ใส่ห้องสอบแล้วกด "ออกเลข"</div></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ======================= 2. วิชาสอบ ======================= --}}
    <div class="tab-pane fade {{ $tab === 'subjects' ? 'show active' : '' }}" id="t-subjects">
        <div class="row g-3">
            <div class="col-lg-8">
                @forelse ($exams as $e)
                    <div class="card mb-3">
                        <div class="card-body d-flex flex-wrap align-items-center gap-3">
                            <div class="stat-icon tint-primary"><i class="bi bi-journal-check"></i></div>
                            <div class="flex-grow-1">
                                <div class="fw-bold fs-5">{{ $loop->iteration }}. {{ $e->subjectLabel() }}</div>
                                <div class="small text-muted">{{ $e->n_items }} ข้อ · ข้อละ {{ $fmt($e->points) }} · น้ำหนัก ×{{ $fmt($e->weight) }}
                                    · {!! $e->keyReady() ? '<span class="text-success">เฉลยครบ</span>' : '<span class="text-danger">เฉลยยังไม่ครบ</span>' !!}</div>
                                <div class="progress mt-2" style="height:6px;max-width:320px" title="ตรวจแล้ว {{ $e->sheets_count }} จาก {{ $takers->count() }}">
                                    <div class="progress-bar bg-success" style="width:{{ $takers->count() ? min(100, round($e->sheets_count / $takers->count() * 100)) : 0 }}%"></div>
                                </div>
                                <div class="small mt-1">ตรวจแล้ว <b>{{ $e->sheets_count }}</b>/{{ $takers->count() }} คน
                                    @if ($e->review_count)<span class="badge bg-danger ms-1">รอตรวจทาน {{ $e->review_count }}</span>@endif</div>
                            </div>
                            <div class="d-flex flex-wrap gap-1">
                                <a href="{{ route('exams.show', $e) }}" class="btn btn-sm btn-light border"><i class="bi bi-key"></i> เฉลย</a>
                                <a href="{{ route('exams.sheets', $e) }}" class="btn btn-sm btn-light border"><i class="bi bi-printer"></i> กระดาษคำตอบ</a>
                                <a href="{{ route('exams.scan', $e) }}" class="btn btn-sm btn-primary"><i class="bi bi-camera"></i> สแกน</a>
                                <a href="{{ route('exams.results', $e) }}" class="btn btn-sm btn-light border"><i class="bi bi-list-check"></i> ผลตรวจ</a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="card"><div class="empty"><i class="bi bi-journal-plus"></i>ยังไม่มีวิชาสอบ — เพิ่มทางขวา</div></div>
                @endforelse
                @if ($exams->isNotEmpty())
                    <div class="small text-muted"><i class="bi bi-info-circle"></i> คะแนนรวม = Σ (คะแนนวิชา × น้ำหนัก) · คะแนนรวมเท่ากัน ตัดสินด้วยคะแนนวิชาตามลำดับข้างบน แล้วจึงดูลำดับการสมัคร · แก้ชื่อวิชา/น้ำหนักได้ที่หน้า "เฉลย" ของแต่ละวิชา</div>
                @endif
            </div>
            <div class="col-lg-4">
                <form method="POST" action="{{ route('admission-exams.subjects', $round) }}" class="card side-sticky">
                    @csrf
                    <div class="card-header"><i class="bi bi-plus-lg"></i> เพิ่มวิชาสอบ</div>
                    <div class="card-body row g-2">
                        <div class="col-12"><label class="form-label">วิชา</label><input name="subject_name" class="form-control" list="subjectList" required placeholder="เช่น คณิตศาสตร์">
                            <datalist id="subjectList">@foreach (['คณิตศาสตร์', 'วิทยาศาสตร์', 'ภาษาไทย', 'ภาษาอังกฤษ', 'สังคมศึกษา', 'ความถนัดทั่วไป'] as $s)<option>{{ $s }}</option>@endforeach</datalist></div>
                        <div class="col-6"><label class="form-label">จำนวนข้อ</label><input type="number" name="n_items" min="1" max="{{ \App\Models\Exam::MAX_ITEMS }}" value="30" class="form-control" required></div>
                        <div class="col-6"><label class="form-label">น้ำหนัก (×)</label><input type="number" name="weight" min="0.01" step="0.01" value="1" class="form-control"></div>
                        <div class="col-12 small text-muted">1 วิชา = กระดาษคำตอบ 1 แผ่น (ไม่เกิน {{ \App\Models\Exam::MAX_ITEMS }} ข้อ 4 ตัวเลือก) · ถ้าข้อสอบรวมหลายวิชาในแผ่นเดียว ให้เพิ่มเป็นวิชาเดียว เช่น "ข้อสอบรวม"</div>
                    </div>
                    <div class="card-footer bg-transparent"><button class="btn btn-primary w-100">เพิ่มวิชาแล้วไปใส่เฉลย</button></div>
                </form>
            </div>
        </div>
    </div>

    {{-- ======================= 3. ผลคัดเลือก ======================= --}}
    <div class="tab-pane fade {{ $tab === 'results' ? 'show active' : '' }}" id="t-results">
        @error('publish')<div class="alert alert-danger">{{ $message }}</div>@enderror
        @if ($problems && ! $round->isPublished())
            <div class="alert alert-warning"><b><i class="bi bi-exclamation-triangle"></i> ก่อนประกาศผล</b>
                <ul class="mb-0 mt-1">@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul></div>
        @endif
        <div class="row g-3">
            <div class="col-lg-4">
                <form method="POST" action="{{ route('admission-exams.update', $round) }}" class="card mb-3">
                    @csrf @method('PUT')
                    <div class="card-header"><i class="bi bi-sliders"></i> เกณฑ์คัดเลือก</div>
                    <div class="card-body row g-2">
                        <div class="col-6"><label class="form-label">จำนวนรับ</label><input type="number" name="quota" min="1" value="{{ $round->quota }}" class="form-control" placeholder="ไม่จำกัด"></div>
                        <div class="col-6"><label class="form-label">จำนวนสำรอง</label><input type="number" name="reserve" min="0" value="{{ $round->reserve }}" class="form-control"></div>
                        <div class="col-12"><label class="form-label">คะแนนรวมขั้นต่ำ <span class="text-muted small">(ไม่บังคับ)</span></label><input type="number" step="0.01" min="0" name="min_score" value="{{ $round->min_score }}" class="form-control" placeholder="ไม่มี"></div>
                        <input type="hidden" name="exam_date" value="{{ $round->exam_date?->toDateString() }}">
                        <div class="col-12"><label class="form-label">ข้อความท้ายประกาศ</label><textarea name="announce_note" rows="3" class="form-control" placeholder="เช่น ให้ผู้ผ่านการคัดเลือกมารายงานตัวและมอบตัว วันที่ ... เวลา ... ณ ...">{{ $round->announce_note }}</textarea></div>
                        <div class="col-12 small text-muted">เรียงคะแนนรวมจากมากไปน้อย: อันดับ 1–{{ $round->quota ?: 'ทุกคน' }} ผ่าน ถัดไป {{ $round->reserve }} คนเป็นสำรอง · ต่ำกว่าคะแนนขั้นต่ำไม่ผ่าน · ไม่มีผลสอบทุกวิชา = ขาดสอบ</div>
                    </div>
                    <div class="card-footer bg-transparent"><button class="btn btn-light border w-100"><i class="bi bi-save"></i> บันทึกเกณฑ์ (ดูผลในตารางทันที)</button></div>
                </form>

                <div class="card side-sticky">
                    <div class="card-header"><i class="bi bi-megaphone"></i> ประกาศผล</div>
                    <div class="card-body d-grid gap-2">
                        @if ($round->isPublished())
                            <div class="small">ประกาศแล้วเมื่อ {{ thai_datetime($round->published_at) }}{{ $round->publisher ? ' โดย '.$round->publisher->name : '' }} — ผู้สมัครเห็นผลในหน้าตรวจสอบสถานะใบสมัคร</div>
                            <form method="POST" action="{{ route('admission-exams.unpublish', $round) }}" data-confirm="ยกเลิกประกาศผล? สถานะผู้สมัครจะกลับเป็น &quot;กำลังตรวจสอบ&quot; (ยกเว้นคนที่มอบตัวแล้ว)">@csrf @method('DELETE')
                                <button class="btn btn-outline-danger w-100"><i class="bi bi-x-circle"></i> ยกเลิกประกาศ</button></form>
                        @else
                            <form method="POST" action="{{ route('admission-exams.publish', $round) }}" data-confirm="ประกาศผลตามตาราง? ผ่าน {{ $by['pass'] ?? 0 }} · สำรอง {{ $by['reserve'] ?? 0 }} · ไม่ผ่าน {{ $by['fail'] ?? 0 }} · ขาดสอบ {{ $by['absent'] ?? 0 }} — ผู้สมัครจะเห็นผลทันที">@csrf
                                <button class="btn btn-success btn-lg w-100" @disabled($problems)><i class="bi bi-megaphone"></i> ประกาศผล</button></form>
                        @endif
                        <a href="{{ route('admission-exams.print', [$round, 'announce']) }}" target="_blank" class="btn btn-light border @if($standings->isEmpty()) disabled @endif"><i class="bi bi-printer"></i> พิมพ์ประกาศรายชื่อ</a>
                        <a href="{{ route('admission-exams.print', [$round, 'scores']) }}" target="_blank" class="btn btn-light border @if($standings->isEmpty()) disabled @endif"><i class="bi bi-table"></i> พิมพ์รายงานคะแนน</a>
                        <a href="{{ route('admission-exams.export', $round) }}" class="btn btn-light border"><i class="bi bi-file-earmark-spreadsheet"></i> ส่งออก Excel</a>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    @foreach (\App\Models\AdmissionRound::RESULTS as $k => [$label, $color])
                        <span class="badge rounded-pill text-bg-{{ $color }} p-2">{{ $label }} {{ $by[$k] ?? 0 }}</span>
                    @endforeach
                    @unless ($round->isPublished())<span class="small text-muted align-self-center">ผลในตารางนี้ยังไม่ประกาศ ผู้สมัครยังไม่เห็น</span>@endunless
                </div>
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover table-cards align-middle mb-0">
                            <thead><tr><th class="text-center">อันดับ</th><th>เลขสอบ</th><th>ชื่อ-สกุล</th>
                                @foreach ($exams as $e)<th class="text-center">{{ $e->subjectLabel() }}@if($e->weight != 1)<div class="small fw-normal text-muted">×{{ $fmt($e->weight) }}</div>@endif</th>@endforeach
                                <th class="text-center">รวม</th><th>ผล</th></tr></thead>
                            <tbody>
                            @forelse ($standings as $row)
                                @php($a = $row['application'])
                                <tr data-href="{{ route('admissions.show', $a) }}" style="cursor:pointer">
                                    <td class="text-center fw-semibold">{{ $row['rank'] ?? '-' }}</td>
                                    <td class="font-monospace">{{ $a->exam_no }}</td>
                                    <td class="tc-title">{{ $a->fullName() }}</td>
                                    @foreach ($exams as $e)<td class="text-center {{ $row['scores'][$e->id] === null ? 'text-danger' : '' }}">{{ $row['scores'][$e->id] === null ? 'ขาด' : $fmt($row['scores'][$e->id]) }}</td>@endforeach
                                    <td class="text-center fw-bold">{{ $row['absent'] ? '-' : $fmt($row['total']) }}</td>
                                    <td><span class="badge text-bg-{{ \App\Models\AdmissionRound::RESULTS[$row['result']][1] }}">{{ \App\Models\AdmissionRound::RESULTS[$row['result']][0] }}{{ $row['reserve_no'] ? ' '.$row['reserve_no'] : '' }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ 5 + $exams->count() }}"><div class="empty"><i class="bi bi-trophy"></i>ยังไม่มีผู้เข้าสอบ</div></td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
