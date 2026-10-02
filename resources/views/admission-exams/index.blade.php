@extends('layouts.app')
@section('title', 'สอบคัดเลือก')

@section('content')
<div class="page-head">
    <div><h1>สอบคัดเลือกนักเรียน ปีการศึกษา {{ $year }}</h1><div class="sub">จัดห้องสอบ · ตรวจกระดาษคำตอบด้วยมือถือ · รวมคะแนนและจัดอันดับ · ประกาศผลให้ผู้สมัครดูออนไลน์</div></div>
    <div class="actions">
        <a href="{{ route('admission-exams.index', ['year' => $year - 1]) }}" class="btn btn-light border"><i class="bi bi-chevron-left"></i> {{ $year - 1 }}</a>
        <a href="{{ route('admission-exams.index', ['year' => $year + 1]) }}" class="btn btn-light border">{{ $year + 1 }} <i class="bi bi-chevron-right"></i></a>
        <a href="{{ route('admissions.index', ['year' => $year]) }}" class="btn btn-light border"><i class="bi bi-person-plus"></i> ใบสมัคร</a>
    </div>
</div>

<div class="row g-3 mb-3">
    @forelse ($levels as $level)
        @php($round = $rounds->get($level))
        <div class="col-md-6 col-xl-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-2">
                        <div class="stat-icon tint-primary"><i class="bi bi-trophy"></i></div>
                        <div class="flex-grow-1">
                            <div class="fw-bold fs-5">ชั้น {{ $level }}</div>
                            <div class="small text-muted">
                                สมัคร {{ number_format($applied[$level] ?? 0) }} คน · ได้เลขสอบ {{ number_format($takers[$level] ?? 0) }} คน
                                · {{ $round?->exams_count ?? 0 }} วิชา
                            </div>
                        </div>
                        @if ($round?->isPublished())
                            <span class="badge text-bg-success">ประกาศผลแล้ว</span>
                        @elseif ($round)
                            <span class="badge text-bg-light border">กำลังดำเนินการ</span>
                        @endif
                    </div>
                    @if ($round?->exam_date)<div class="small mt-2"><i class="bi bi-calendar-event"></i> สอบ {{ thai_date($round->exam_date) }}</div>@endif
                </div>
                <div class="card-footer bg-transparent">
                    @if ($round)
                        <a href="{{ route('admission-exams.show', $round) }}" class="btn btn-primary w-100">เปิด <i class="bi bi-arrow-right"></i></a>
                    @else
                        <form method="POST" action="{{ route('admission-exams.store') }}">@csrf
                            <input type="hidden" name="year" value="{{ $year }}"><input type="hidden" name="level" value="{{ $level }}">
                            <button class="btn btn-outline-primary w-100"><i class="bi bi-plus-lg"></i> เริ่มจัดสอบคัดเลือกชั้น {{ $level }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card"><div class="empty"><i class="bi bi-person-plus"></i>ยังไม่ได้ตั้งชั้นที่รับสมัคร — ไปที่ <a href="{{ route('admissions.form') }}">ตั้งค่าฟอร์มรับสมัคร</a></div></div></div>
    @endforelse
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-signpost-split"></i> ขั้นตอน</div>
    <div class="card-body row g-3 small">
        <div class="col-md-3"><b>1. จัดห้องสอบ</b><br>ใส่ห้องสอบและจำนวนที่นั่ง ระบบออกเลขประจำตัวสอบ 5 หลักและเลขที่นั่งให้ผู้มีสิทธิ์สอบทุกคน (ส่งใบสมัครแล้ว ไม่ค้างค่าสมัคร) พิมพ์รายชื่อหน้าห้อง ใบลงชื่อ บัตรติดโต๊ะ</div>
        <div class="col-md-3"><b>2. วิชาสอบ + เฉลย</b><br>เพิ่มวิชาละ 1 ชุด (ไม่เกิน 100 ข้อ) ตั้งน้ำหนักได้ ใส่เฉลย แล้วพิมพ์กระดาษคำตอบรายบุคคล (ชื่อและเลขสอบระบายไว้ให้แล้ว)</div>
        <div class="col-md-3"><b>3. สแกนตรวจ</b><br>ใช้มือถือหลายเครื่องสแกนพร้อมกันได้ ระบบจับคู่แผ่นกับผู้สมัครจากเลขประจำตัวสอบ แผ่นที่ไม่ชัดเข้าคิวตรวจทาน</div>
        <div class="col-md-3"><b>4. จัดอันดับ + ประกาศผล</b><br>ตั้งจำนวนรับ/สำรอง/คะแนนขั้นต่ำ ระบบรวมคะแนนและจัดอันดับ กดประกาศ → ผู้สมัครเห็นผลในหน้าตรวจสอบสถานะ พิมพ์ประกาศรายชื่อได้</div>
    </div>
</div>
@endsection
