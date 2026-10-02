@extends('layouts.app')
@section('title', 'ผลตรวจ · '.$exam->title)

@section('content')
@include('exams._nav')
@php
    $fmt = fn ($v) => $v === null ? '-' : rtrim(rtrim(number_format($v, 2), '0'), '.');
    $review = $responses->where('status', 'review');
    $done = $rows->filter(fn ($r) => $r['response']?->status === 'ok')->count();
    $pending = $rows->filter(fn ($r) => $r['response']?->status === 'review')->count();
    $absent = $rows->count() - $done - $pending;
@endphp

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-success"><i class="bi bi-check2-circle"></i></div><div><div class="stat-value">{{ $done }}<small class="fs-6 text-muted">/{{ $rows->count() }}</small></div><div class="stat-label">ตรวจแล้ว</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-eye"></i></div><div><div class="stat-value {{ $review->count() ? 'text-danger' : '' }}">{{ $review->count() }}</div><div class="stat-label">รอตรวจทาน</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-bar-chart"></i></div><div><div class="stat-value">{{ $stats ? $fmt(round($stats['mean'], 2)) : '-' }}</div><div class="stat-label">เฉลี่ย{{ $stats ? ' · S.D. '.number_format($stats['sd'], 2) : '' }}</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-blue"><i class="bi bi-arrows-vertical"></i></div><div><div class="stat-value fs-5">{{ $stats ? $fmt($stats['max']).' / '.$fmt($stats['min']) : '-' }}</div><div class="stat-label">สูงสุด / ต่ำสุด{{ $stats ? ' · มัธยฐาน '.$fmt($stats['median']) : '' }}</div></div></div></div></div>
</div>

<ul class="nav nav-pills mb-3 gap-1" role="tablist">
    <li class="nav-item"><button class="nav-link {{ $tab === 'review' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#t-review">รอตรวจทาน @if ($review->count())<span class="badge bg-danger">{{ $review->count() }}</span>@endif</button></li>
    <li class="nav-item"><button class="nav-link {{ $tab === 'scores' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#t-scores">ตารางคะแนน</button></li>
    <li class="nav-item"><button class="nav-link {{ $tab === 'all' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#t-all">ทุกแผ่น ({{ $responses->count() }})</button></li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade {{ $tab === 'review' ? 'show active' : '' }}" id="t-review">
        <div class="card">
            @if ($review->isNotEmpty())
                <div class="card-header"><i class="bi bi-eye"></i> แผ่นที่ระบบไม่แน่ใจ ดูภาพจริงเทียบแล้วยืนยัน/แก้
                    <a href="{{ route('exams.review', [$exam, $review->sortBy('scanned_at')->first()]) }}" class="btn btn-sm btn-primary ms-auto"><i class="bi bi-play-fill"></i> ตรวจทานทีละแผ่น</a>
                </div>
            @endif
            @forelse ($review->sortBy('scanned_at') as $r)
                <a href="{{ route('exams.review', [$exam, $r]) }}" class="d-flex align-items-center gap-3 px-3 py-2 border-bottom text-decoration-none text-body list-link">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold">{{ $r->student?->fullName() ?? 'ไม่ทราบเจ้าของ (รหัส '.$r->code_read.')' }}</div>
                        <div class="small text-muted">
                            @foreach ($r->reasons() as $why)<span class="badge bg-warning-subtle text-warning-emphasis me-1">{{ $why }}</span>@endforeach
                            {{ $r->scanned_at?->format('H:i') }} น. · {{ $r->source === 'photo' ? 'จากรูป' : 'กล้อง' }}
                        </div>
                    </div>
                    <div class="fw-bold">{{ $fmt($r->score) }}</div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
            @empty
                <div class="empty"><i class="bi bi-emoji-smile"></i>ไม่มีแผ่นรอตรวจทาน</div>
            @endforelse
        </div>
    </div>

    <div class="tab-pane fade {{ $tab === 'scores' ? 'show active' : '' }}" id="t-scores">
        <div class="card mb-3">
            <div class="card-body d-flex flex-wrap gap-2 align-items-end">
                <form method="POST" action="{{ route('exams.sync', $exam) }}" class="d-flex gap-2 align-items-end flex-wrap" data-confirm="ส่งคะแนนที่ตรวจแล้วเข้าสมุดคะแนนทุกห้อง? (แปลงสัดส่วนตามคะแนนเต็มของช่อง · คะแนนเดิมในช่องนี้จะถูกแทนที่)">
                    @csrf
                    <div>
                        <label class="form-label small">ส่งเข้าช่องคะแนน</label>
                        <select name="assessment_name" class="form-select" required>
                            <option value="">- เลือกช่อง -</option>
                            @foreach ($assessmentNames as $n)<option @selected($exam->assessment_name === $n)>{{ $n }}</option>@endforeach
                        </select>
                    </div>
                    <button class="btn btn-primary" @disabled(! $done)><i class="bi bi-journal-arrow-down"></i> ส่งเข้าสมุดคะแนน</button>
                </form>
                <div class="ms-auto d-flex gap-2">
                    <a href="{{ route('exams.export', $exam) }}" class="btn btn-light border"><i class="bi bi-file-earmark-spreadsheet"></i> ส่งออกคะแนน</a>
                    <a href="{{ route('exams.export', [$exam, 'format' => 'evana']) }}" class="btn btn-light border" title="แถว KEY + คำตอบรายคน สำหรับโปรแกรม EVANA"><i class="bi bi-filetype-csv"></i> แบบ EVANA</a>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>ห้อง</th><th>เลขที่</th><th class="d-none d-md-table-cell">เลขประจำตัว</th><th>ชื่อ-สกุล</th><th class="text-center">คะแนน</th><th>สถานะ</th></tr></thead>
                    <tbody>
                    @forelse ($rows as $row)
                        @php($r = $row['response'])
                        <tr @if($r) data-href="{{ route('exams.review', [$exam, $r]) }}" style="cursor:pointer" @endif>
                            <td class="small">{{ $row['student']->classroom?->name() }}</td>
                            <td>{{ $row['student']->number }}</td>
                            <td class="text-muted d-none d-md-table-cell">{{ $row['student']->student_code }}</td>
                            <td>{{ $row['student']->fullName() }}</td>
                            <td class="text-center fw-semibold">{{ $r ? $fmt($r->score).' / '.$fmt($r->max_score) : '' }}</td>
                            <td>
                                @if ($r)<span class="badge bg-{{ $r->statusColor() }}-subtle text-{{ $r->statusColor() }}-emphasis">{{ $r->statusLabel() }}</span>
                                @else<span class="badge bg-light text-muted border">ไม่มีแผ่น / ขาดสอบ</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><div class="empty">ห้องที่สอบยังไม่มีนักเรียน</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($absent)<div class="small text-muted mt-2">ยังไม่มีแผ่น {{ $absent }} คน</div>@endif
    </div>

    <div class="tab-pane fade {{ $tab === 'all' ? 'show active' : '' }}" id="t-all">
        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>เวลา</th><th>นักเรียน</th><th class="text-center">คะแนน</th><th>สถานะ</th><th class="d-none d-md-table-cell">สแกนโดย</th></tr></thead>
                    <tbody>
                    @forelse ($responses as $r)
                        <tr data-href="{{ route('exams.review', [$exam, $r]) }}" style="cursor:pointer" class="{{ $r->status === 'void' ? 'text-muted' : '' }}">
                            <td class="small text-nowrap">{{ $r->scanned_at ? thai_date($r->scanned_at).' '.$r->scanned_at->format('H:i') : '' }}</td>
                            <td>{{ $r->student?->fullName() ?? 'รหัส '.$r->code_read }} <span class="small text-muted">{{ $r->student?->classroom?->name() }}</span></td>
                            <td class="text-center">{{ $fmt($r->score) }}</td>
                            <td><span class="badge bg-{{ $r->statusColor() }}-subtle text-{{ $r->statusColor() }}-emphasis">{{ $r->statusLabel() }}</span></td>
                            <td class="small d-none d-md-table-cell">{{ $r->scanner?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty"><i class="bi bi-camera"></i>ยังไม่มีแผ่นที่สแกน <a href="{{ route('exams.scan', $exam) }}">เริ่มสแกน</a></div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
