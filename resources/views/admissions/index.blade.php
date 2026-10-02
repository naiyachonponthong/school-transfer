@extends('layouts.app')
@section('title', 'รับสมัครนักเรียน')

@section('content')
<div class="page-head">
    <div><h1>รับสมัครนักเรียน ปี {{ $year }}</h1><div class="sub">{{ $open ? 'เปิดรับสมัครอยู่' : 'ปิดรับสมัคร' }} · ลิงก์สำหรับผู้ปกครอง: <a href="{{ route('apply') }}" target="_blank">{{ route('apply') }}</a></div></div>
    <div class="actions">
        <button class="btn btn-light border" onclick="navigator.clipboard.writeText('{{ route('apply') }}');this.innerHTML='<i class=\'bi bi-check2\'></i> คัดลอกแล้ว'"><i class="bi bi-link-45deg"></i> คัดลอกลิงก์สมัคร</button>
        <a href="{{ route('admissions.export', ['year' => $year]) }}" class="btn btn-light border"><i class="bi bi-file-earmark-spreadsheet"></i> ส่งออก Excel</a>
        <a href="{{ route('admissions.form') }}" class="btn btn-primary"><i class="bi bi-ui-checks"></i> ตั้งค่าฟอร์มรับสมัคร</a>
    </div>
</div>

@if ($slipsPending)
    <a href="{{ request()->fullUrlWithQuery(['fee' => 'pending', 'status' => 'all']) }}" class="alert alert-info d-flex align-items-center gap-2 text-decoration-none">
        <i class="bi bi-receipt-cutoff fs-5"></i> มีสลิปค่าสมัครรอตรวจ {{ $slipsPending }} ใบ <span class="ms-auto">ดูรายการ →</span>
    </a>
@endif
<div class="row g-3 mb-3">
    @foreach (\App\Models\Admission::STATUSES as $k => [$label, $color])
        <div class="col-6 col-md">
            <a href="{{ request()->fullUrlWithQuery(['status' => $k]) }}" class="card text-body"><div class="stat">
                <div class="stat-icon tint-{{ ['secondary' => 'blue', 'light' => 'teal'][$color] ?? $color }}"><i class="bi {{ $k === 'draft' ? 'bi-pencil' : 'bi-person-lines-fill' }}"></i></div>
                <div><div class="stat-value">{{ $counts[$k] ?? 0 }}</div><div class="stat-label">{{ $label }}</div></div>
            </div></a>
        </div>
    @endforeach
</div>

<form class="d-flex flex-wrap gap-2 mb-3" method="GET">
    <input type="hidden" name="year" value="{{ $year }}">
    <input name="q" value="{{ request('q') }}" class="form-control" style="max-width:280px" placeholder="ชื่อ เลขที่ใบสมัคร เลขบัตร">
    <select name="status" class="form-select w-auto" data-autosubmit><option value="all">ทุกสถานะ</option>@foreach (\App\Models\Admission::STATUSES as $k => [$l])<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach</select>
    <select name="level" class="form-select w-auto" data-autosubmit><option value="">ทุกชั้น</option>@foreach ($levels as $l)<option @selected(request('level') === $l)>{{ $l }} ({{ $byLevel[$l] ?? 0 }}{{ isset($caps[$l]) ? '/'.$caps[$l] : '' }})</option>@endforeach</select>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-cards table-hover align-middle">
            <thead><tr><th>เลขที่</th><th>ชื่อ-สกุล</th><th>ชั้น</th><th>โรงเรียนเดิม</th><th class="text-center">GPA</th><th>ผู้ปกครอง</th><th>วันที่ส่ง</th><th>สถานะ</th></tr></thead>
            <tbody>
            @forelse ($items as $a)
                <tr data-href="{{ route('admissions.show', $a) }}" style="cursor:pointer">
                    <td class="small text-muted">{{ $a->app_no }}</td>
                    <td class="fw-semibold">{{ $a->fullName() }}</td>
                    <td>{{ $a->level }}</td>
                    <td class="small">{{ $a->previous_school }}</td>
                    <td class="text-center">{{ $a->gpa ? number_format($a->gpa, 2) : '-' }}</td>
                    <td class="small">{{ $a->parent_name }}<br><span class="text-muted">{{ $a->parent_phone }}</span></td>
                    <td class="small">{{ $a->submitted_at ? thai_date($a->submitted_at) : 'บันทึก '.thai_date($a->updated_at) }}</td>
                    <td><span class="badge bg-{{ $a->statusColor() }} {{ $a->statusColor() === 'light' ? 'text-dark border' : '' }}">{{ $a->statusLabel() }}</span>
                        @if ($a->fee_amount > 0)<div><span class="badge bg-{{ $a->feeColor() }}-subtle text-{{ $a->feeColor() }}-emphasis fw-normal">{{ $a->feeLabel() }}</span></div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><i class="bi bi-person-plus"></i>ยังไม่มีใบสมัคร</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $items->links() }}</div>
@endsection
