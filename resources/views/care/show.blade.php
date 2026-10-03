@extends('layouts.app')
@section('title', 'กรณีดูแลช่วยเหลือ')

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $case->student->fullName() }} <span class="badge bg-{{ \App\Models\CareCase::LEVELS[$case->level][1] }} align-middle">{{ \App\Models\CareCase::LEVELS[$case->level][0] }}</span></h1>
        <div class="sub">{{ $case->student->classroom?->name() }} · {{ $case->categoryLabel() }} · ผู้รับผิดชอบ {{ $case->owner?->name ?? '-' }} · เปิดเมื่อ {{ thai_date($case->created_at) }}</div>
    </div>
    <div class="actions">
        <a href="{{ route('care.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a>
        <a href="{{ route('students.show', $case->student) }}" class="btn btn-light border"><i class="bi bi-person"></i> ข้อมูลนักเรียน</a>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clipboard-heart"></i> {{ $case->title }}</div>
            <div class="card-body" style="white-space:pre-line">{{ $case->detail ?: 'ไม่มีรายละเอียด' }}</div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-journal-text"></i> บันทึกการช่วยเหลือ</div>
            @forelse ($case->actions as $a)
                <div class="px-3 py-2 border-bottom">
                    <div class="small text-muted">{{ thai_date($a->date) }} · {{ $a->user?->name }}{{ $a->follow_up_on ? ' · นัดติดตาม '.thai_date($a->follow_up_on) : '' }}</div>
                    <div style="white-space:pre-line">{{ $a->action }}</div>
                    @if ($a->result)<div class="small text-success" style="white-space:pre-line">ผล: {{ $a->result }}</div>@endif
                </div>
            @empty
                <div class="empty py-4"><i class="bi bi-journal"></i>ยังไม่มีบันทึก</div>
            @endforelse
            <form method="POST" action="{{ route('care.actions.store', $case) }}" class="card-body row g-2">
                @csrf
                <div class="col-md-4"><label class="form-label small mb-1">วันที่</label><input type="date" name="date" value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control form-control-sm" required></div>
                <div class="col-md-4"><label class="form-label small mb-1">นัดติดตามครั้งถัดไป</label><input type="date" name="follow_up_on" value="{{ old('follow_up_on') }}" class="form-control form-control-sm"></div>
                <div class="col-12"><textarea name="action" rows="2" class="form-control form-control-sm @error('action') is-invalid @enderror" placeholder="สิ่งที่ทำ เช่น พูดคุยกับนักเรียน โทรหาผู้ปกครอง ประสานนักจิตวิทยา" required>{{ old('action') }}</textarea></div>
                <div class="col-12"><textarea name="result" rows="2" class="form-control form-control-sm" placeholder="ผลที่ได้ (ถ้ามี)">{{ old('result') }}</textarea></div>
                <div class="col-12 text-end"><button class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> เพิ่มบันทึก</button></div>
            </form>
        </div>
    </div>

    <div class="col-lg-5">
        <form method="POST" action="{{ route('care.update', $case) }}" class="card mb-3">
            @csrf @method('PUT')
            <div class="card-header"><i class="bi bi-flag"></i> สถานะ</div>
            <div class="card-body row g-2">
                <div class="col-6"><select name="status" class="form-select" aria-label="สถานะ">@foreach (\App\Models\CareCase::STATUSES as $k => [$v])<option value="{{ $k }}" @selected($case->status === $k)>{{ $v }}</option>@endforeach</select></div>
                <div class="col-6"><select name="level" class="form-select" aria-label="ระดับ">@foreach (\App\Models\CareCase::LEVELS as $k => [$v])<option value="{{ $k }}" @selected($case->level === $k)>{{ $v }}</option>@endforeach</select></div>
                <div class="col-12 d-grid"><button class="btn btn-light border">บันทึกสถานะ</button></div>
                @if ($case->closed_at)<div class="col-12 small text-muted">ปิดกรณีเมื่อ {{ thai_date($case->closed_at) }}</div>@endif
            </div>
        </form>

        <div class="card">
            <div class="card-header"><i class="bi bi-house-heart"></i> เยี่ยมบ้านล่าสุด</div>
            <div class="card-body small">
                @if ($visit)
                    <div>{{ thai_date($visit->visited_on) }} · พบ {{ $visit->guardian_met ?: '-' }}</div>
                    <div>ที่อยู่อาศัย: {{ \App\Models\HomeVisit::HOUSING[$visit->housing] ?? '-' }} · ครอบครัว: {{ \App\Models\HomeVisit::FAMILY[$visit->family_status] ?? '-' }}</div>
                    @if ($visit->riskLabels())<div class="text-danger">ความเสี่ยง: {{ implode(', ', $visit->riskLabels()) }}</div>@endif
                    @if ($visit->note)<div class="text-muted mt-1" style="white-space:pre-line">{{ $visit->note }}</div>@endif
                @else
                    <span class="text-muted">ยังไม่มีบันทึกเยี่ยมบ้าน</span>
                @endif
                <div class="mt-2"><a href="{{ route('care.visits.form', $case->student) }}" class="btn btn-sm btn-light border">บันทึกเยี่ยมบ้าน</a></div>
            </div>
        </div>
    </div>
</div>
@endsection
