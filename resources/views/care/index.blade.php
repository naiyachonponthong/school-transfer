@extends('layouts.app')
@section('title', 'ดูแลช่วยเหลือนักเรียน')

@section('content')
<div class="page-head">
    <div><h1>ดูแลช่วยเหลือนักเรียน</h1><div class="sub">สัญญาณเตือน {{ $risks->count() }} คน · กรณีที่กำลังดูแล {{ $cases->where('status', '!=', 'closed')->count() }} กรณี · ข้อมูลในหน้านี้เห็นเฉพาะครูประจำชั้นและผู้ที่ได้รับสิทธิ์</div></div>
    <div class="actions">
        <form method="GET" class="d-flex gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <select name="classroom" class="form-select" data-autosubmit aria-label="ห้องเรียน">
                <option value="">ทุกห้องที่ดูแล</option>
                @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected($classroom?->id === $c->id)>{{ $c->name() }}</option>@endforeach
            </select>
        </form>
        <a href="{{ route('care.visits', ['classroom' => $classroom?->id]) }}" class="btn btn-light border"><i class="bi bi-house-heart"></i> เยี่ยมบ้าน</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-bell text-danger"></i> สัญญาณเตือนกลุ่มเสี่ยง</div>
            @forelse ($risks as $r)
                <div class="d-flex align-items-start gap-2 px-3 py-2 border-bottom">
                    <div class="flex-grow-1">
                        <a href="{{ route('students.show', $r['student']) }}" class="fw-semibold">{{ $r['student']->fullName() }}</a>
                        <span class="text-muted small">{{ $r['student']->classroom?->name() }}</span>
                        <div class="d-flex flex-wrap gap-1 mt-1">
                            @foreach ($r['signals'] as $key => $text)
                                @php([$label, $icon, $color] = \App\Support\RiskScan::SIGNALS[$key])
                                <span class="badge bg-{{ $color }}-subtle text-{{ $color }} fw-normal"><i class="bi {{ $icon }}"></i> {{ $label }} · {{ $text }}</span>
                            @endforeach
                        </div>
                    </div>
                    @if (isset($openByStudent[$r['student']->id]))
                        <span class="badge bg-primary align-self-center">มีกรณีแล้ว</span>
                    @else
                        <a href="{{ route('care.create', ['student' => $r['student']->id, 'title' => collect($r['signals'])->map(fn ($t, $k) => \App\Support\RiskScan::SIGNALS[$k][0].' '.$t)->implode(', ')]) }}" class="btn btn-sm btn-light border text-nowrap align-self-center">เปิดกรณี</a>
                    @endif
                </div>
            @empty
                <div class="empty py-4"><i class="bi bi-emoji-smile"></i>ไม่พบสัญญาณเตือนในห้องที่ดูแล</div>
            @endforelse
            <div class="card-footer bg-transparent small text-muted">คำนวณจาก: ขาดติดกัน {{ \App\Support\RiskScan::ABSENT_STREAK }} วัน · เวลาเรียนรายวิชาต่ำกว่า 80% · คะแนนต่ำกว่า {{ \App\Support\RiskScan::SCORE_MIN_PERCENT }}% ตั้งแต่ {{ \App\Support\RiskScan::WEAK_COURSES }} วิชา · ความประพฤติต่ำกว่า {{ \App\Support\RiskScan::BEHAVIOR_MIN }}</div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-clipboard-heart"></i> กรณีดูแลช่วยเหลือ
                <span class="ms-auto">
                    @foreach (['active' => 'กำลังดูแล', 'closed' => 'ปิดแล้ว', 'all' => 'ทั้งหมด'] as $k => $v)
                        <a href="{{ route('care.index', ['status' => $k, 'classroom' => $classroom?->id]) }}" class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-light border' }}">{{ $v }}</a>
                    @endforeach
                </span>
            </div>
            @forelse ($cases as $c)
                <a href="{{ route('care.show', $c) }}" class="d-flex align-items-center gap-2 px-3 py-2 border-bottom text-decoration-none text-body">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $c->student->fullName() }} <span class="text-muted small fw-normal">{{ $c->student->classroom?->name() }}</span></div>
                        <div class="small">{{ $c->categoryLabel() }} · {{ $c->title }}</div>
                        <div class="small text-muted">ผู้รับผิดชอบ {{ $c->owner?->name ?? '-' }} · บันทึก {{ $c->actions_count }} ครั้ง · เปิดเมื่อ {{ thai_date($c->created_at) }}</div>
                    </div>
                    <span class="badge bg-{{ \App\Models\CareCase::LEVELS[$c->level][1] }}">{{ \App\Models\CareCase::LEVELS[$c->level][0] }}</span>
                    <span class="badge bg-{{ \App\Models\CareCase::STATUSES[$c->status][1] }}">{{ \App\Models\CareCase::STATUSES[$c->status][0] }}</span>
                </a>
            @empty
                <div class="empty py-4"><i class="bi bi-clipboard"></i>ไม่มีกรณีในรายการนี้</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
