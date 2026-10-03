@extends('layouts.app')
@section('title', 'ภาพรวมผู้บริหาร')

@push('head')
<style>
    .ex-tile .num { font-size: 1.75rem; font-weight: 700; line-height: 1.1; }
    .ex-tile .lbl { font-size: .8rem; color: var(--bs-secondary-color); }
    /* แท่งสีเดียว (ขนาด) ปลายมน ยึดฐานซ้าย ราง = สีพื้นอ่อน */
    .ex-bar { height: 10px; border-radius: 4px; background: var(--sb-primary-100); overflow: hidden; }
    .ex-bar > span { display: block; height: 100%; border-radius: 0 4px 4px 0; background: var(--sb-primary); }
</style>
@endpush

@section('content')
@php($fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.'))
<div class="page-head">
    <div><h1>ภาพรวมผู้บริหาร</h1><div class="sub">{{ $term?->label() ?? 'ยังไม่ได้ตั้งภาคเรียน' }} · นักเรียน {{ number_format($students) }} คน · ข้อมูล ณ {{ thai_datetime(now()) }}</div></div>
    <div class="actions"><button onclick="print()" class="btn btn-light border"><i class="bi bi-printer"></i> พิมพ์รายงาน</button></div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body ex-tile">
        <div class="lbl">มาเรียนสัปดาห์นี้</div>
        <div class="num">{{ ($w = end($weeks))['percent'] !== null ? $fmt($w['percent']).'%' : '-' }}</div>
        <div class="lbl">ขาด {{ number_format($w['absent']) }} ครั้ง จาก {{ number_format($w['total']) }} รายการ</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body ex-tile">
        <div class="lbl">เก็บค่าธรรมเนียมภาคนี้</div>
        <div class="num">{{ $finance['percent'] !== null ? $fmt($finance['percent']).'%' : '-' }}</div>
        <div class="lbl">{{ baht($finance['collected'], 0) }} จาก {{ baht($finance['billed'], 0) }} บาท</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body ex-tile">
        <div class="lbl">เกรดเฉลี่ยรวมภาคนี้</div>
        <div class="num">{{ $grades['average'] !== null ? number_format($grades['average'], 2) : '-' }}</div>
        <div class="lbl">ไม่ผ่าน (0 / ร / มส / มผ) {{ number_format($grades['failing']) }} จาก {{ number_format($grades['total']) }} ผล</div>
    </div></div></div>
    <div class="col-6 col-lg-3"><div class="card h-100"><div class="card-body ex-tile">
        <div class="lbl">นักเรียนที่มีสัญญาณเตือน</div>
        <div class="num">{{ number_format($care['risk']) }}</div>
        <div class="lbl">กรณีที่กำลังดูแล {{ $care['cases'] }} · กลุ่มมีปัญหา {{ $care['problem'] }}</div>
    </div></div></div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-calendar-check"></i> อัตรามาเรียนรายสัปดาห์ ({{ count($weeks) }} สัปดาห์ล่าสุด)</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>สัปดาห์</th><th style="width:45%">มาเรียน</th><th class="text-end">%</th><th class="text-end">ขาด (ครั้ง)</th></tr></thead>
                    <tbody>
                    @foreach ($weeks as $wk)
                        <tr title="{{ $wk['label'] }}: {{ $wk['percent'] !== null ? $fmt($wk['percent']).'% จาก '.number_format($wk['total']).' รายการ' : 'ไม่มีการเช็คชื่อ' }}">
                            <td class="small text-nowrap">{{ $wk['label'] }}</td>
                            <td><div class="ex-bar"><span style="width:{{ $wk['percent'] ?? 0 }}%"></span></div></td>
                            <td class="text-end">{{ $wk['percent'] !== null ? $fmt($wk['percent']) : '-' }}</td>
                            <td class="text-end text-muted">{{ $wk['total'] ? number_format($wk['absent']) : '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-diagram-3"></i> มาเรียน 30 วันล่าสุด แยกระดับชั้น</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>ระดับชั้น</th><th style="width:45%">มาเรียน</th><th class="text-end">%</th><th class="text-end">ขาด (ครั้ง)</th></tr></thead>
                    <tbody>
                    @forelse ($levels as $lv)
                        <tr title="{{ $lv['level'] }}: {{ $lv['percent'] !== null ? $fmt($lv['percent']).'%' : 'ไม่มีการเช็คชื่อ' }}">
                            <td class="text-nowrap">{{ $lv['level'] }} <span class="small text-muted">{{ $lv['rooms'] }} ห้อง</span></td>
                            <td><div class="ex-bar"><span style="width:{{ $lv['percent'] ?? 0 }}%"></span></div></td>
                            <td class="text-end">{{ $lv['percent'] !== null ? $fmt($lv['percent']) : '-' }}</td>
                            <td class="text-end text-muted">{{ number_format($lv['absent']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty py-3">ยังไม่มีห้องเรียนในปีนี้</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-mortarboard"></i> การกระจายผลการเรียนภาคนี้
                <span class="ms-auto small text-muted fw-normal">อนุมัติแล้ว {{ $grades['locked'] }}/{{ $grades['courses'] }} รายวิชา · รอตรวจ {{ $grades['submitted'] }}</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>ผลการเรียน</th><th style="width:55%">สัดส่วน</th><th class="text-end">จำนวน</th><th class="text-end">%</th></tr></thead>
                    <tbody>
                    @forelse ($grades['distribution'] as $g)
                        @php($pct = $grades['total'] ? $g['count'] / $grades['total'] * 100 : 0)
                        <tr title="ผล {{ $g['grade'] }}: {{ number_format($g['count']) }} ผล ({{ $fmt($pct) }}%)">
                            <td class="fw-semibold">{{ $g['grade'] }}</td>
                            <td><div class="ex-bar"><span style="width:{{ $pct }}%"></span></div></td>
                            <td class="text-end">{{ number_format($g['count']) }}</td>
                            <td class="text-end text-muted">{{ $fmt($pct) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><div class="empty py-3">ยังไม่มีผลการเรียน</div></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($grades['locked'] < $grades['courses'])<div class="card-footer bg-transparent small text-muted">รายวิชาที่ยังไม่อนุมัติคำนวณจากคะแนนปัจจุบัน ตัวเลขอาจเปลี่ยนได้</div>@endif
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-wallet2"></i> การเงิน</div>
            <div class="card-body row g-3 ex-tile">
                <div class="col-6"><div class="lbl">ค้างชำระทั้งหมด</div><div class="fs-5 fw-bold">{{ baht($finance['outstanding'], 0) }}</div></div>
                <div class="col-6"><div class="lbl">รับเดือนนี้</div><div class="fs-5 fw-bold">{{ baht($finance['month'], 0) }}</div></div>
                <div class="col-12 small">
                    @if ($finance['overdue'])<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> เลยกำหนดชำระ {{ number_format($finance['overdue']) }} ใบ</span>
                    @else<span class="text-success"><i class="bi bi-check-circle"></i> ไม่มีใบแจ้งหนี้เลยกำหนด</span>@endif
                    @if (auth()->user()->hasPermission('finance.view')) · <a href="{{ route('finance.reports', ['tab' => 'outstanding']) }}">ดูลูกหนี้แยกห้อง</a>@endif
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-person-badge"></i> บุคลากรวันนี้</div>
            <div class="card-body row g-3 ex-tile">
                <div class="col-4"><div class="lbl">ลงเวลาแล้ว</div><div class="fs-5 fw-bold">{{ $staff['checked_in'] }}<span class="fs-6 text-muted fw-normal">/{{ $staff['total'] }}</span></div></div>
                <div class="col-4"><div class="lbl">มาสาย</div><div class="fs-5 fw-bold">{{ $staff['late'] }}</div></div>
                <div class="col-4"><div class="lbl">ลา/ไปราชการ</div><div class="fs-5 fw-bold">{{ $staff['leave'] }}</div></div>
                @if ($staff['pending_leaves'])<div class="col-12 small text-warning"><i class="bi bi-hourglass-split"></i> ใบลารออนุมัติ {{ $staff['pending_leaves'] }} ใบ</div>@endif
            </div>
        </div>
    </div>
</div>
@endsection
