{{-- หัวหน้าชุดข้อสอบ + แท็บขั้นตอน: เฉลย → พิมพ์กระดาษ → สแกน → ผลตรวจ → วิเคราะห์ --}}
@php
    $review = $exam->responses()->where('status', 'review')->count();
    $steps = [
        ['exams.show', 'เฉลย', 'bi-key', route('exams.show', $exam), $exam->keyReady() ? null : 'ยังไม่ครบ'],
        ['exams.sheets', 'กระดาษคำตอบ', 'bi-printer', route('exams.sheets', $exam), null],
        ['exams.scan', 'สแกน', 'bi-camera', route('exams.scan', $exam), null],
        ['exams.results', 'ผลตรวจ', 'bi-list-check', route('exams.results', $exam), $review ?: null],
        ['exams.analysis', 'วิเคราะห์ข้อสอบ', 'bi-graph-up', route('exams.analysis', $exam), null],
    ];
@endphp
<div class="page-head no-print">
    <div>
        <h1>{{ $exam->title }} <span class="text-muted fw-normal fs-5">· {{ $exam->subjectLabel() }}</span></h1>
        <div class="sub">
            @if ($exam->isAdmission())
                <span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-person-plus"></i> สอบคัดเลือก</span>
                ชั้น {{ $exam->round->level }} ปี {{ $exam->round->year }} · {{ $exam->n_items }} ข้อ · น้ำหนัก ×{{ rtrim(rtrim(number_format($exam->weight, 2), '0'), '.') }}
                @if ($exam->exam_date) · สอบ {{ thai_date($exam->exam_date) }}@endif
            @else
                {{ $exam->subjectCode() }} · {{ $exam->n_items }} ข้อ
                @if ($exam->exam_date) · สอบ {{ thai_date($exam->exam_date) }}@endif
                · ห้อง {{ $exam->courses->map(fn ($c) => $c->classroom?->name())->filter()->implode(', ') ?: '-' }}
                @if ($exam->published)<span class="badge bg-success-subtle text-success-emphasis ms-1"><i class="bi bi-eye"></i> นักเรียนเห็นคะแนน</span>@endif
            @endif
        </div>
    </div>
    <div class="actions">
        @if ($exam->isAdmission())
            <a href="{{ route('admission-exams.show', [$exam->admission_round_id, 'tab' => 'subjects']) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> {{ $exam->round->label() }}</a>
        @else
            <a href="{{ route('exams.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> ชุดข้อสอบทั้งหมด</a>
        @endif
    </div>
</div>
<ul class="nav nav-pills mb-3 gap-1 flex-nowrap overflow-auto no-print">
    @foreach ($steps as [$route, $label, $icon, $url, $badge])
        <li class="nav-item">
            <a href="{{ $url }}" class="nav-link text-nowrap {{ request()->routeIs($route, $route === 'exams.results' ? 'exams.review' : $route) ? 'active' : '' }}">
                <i class="bi {{ $icon }}"></i> {{ $label }}
                @if ($badge)<span class="badge {{ is_int($badge) ? 'bg-danger' : 'bg-warning text-dark' }} ms-1">{{ $badge }}</span>@endif
            </a>
        </li>
    @endforeach
</ul>
