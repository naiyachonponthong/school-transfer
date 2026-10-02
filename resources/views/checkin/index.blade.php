@extends('layouts.app')
@section('title', 'ลงเวลาปฏิบัติงาน')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card text-center mb-3">
            <div class="card-body py-5">
                <div class="text-muted">{{ \App\Support\Thai::fullDate(today()) }}</div>
                <div class="display-4 fw-bold my-2" data-clock>--:--:--</div>
                @if (! $today?->check_in)
                    <form method="POST" action="{{ route('checkin.store') }}" data-geo>@csrf <input type="hidden" name="action" value="in">
                        <button class="btn btn-primary btn-lg rounded-pill px-5 py-3 fs-4 mt-3"><i class="bi bi-fingerprint"></i> ลงเวลาเข้างาน</button>
                    </form>
                    <div class="small text-muted mt-3">หลัง {{ school('staff_late_time') }} น. นับเป็นมาสาย</div>
                @else
                    <div class="d-flex justify-content-center gap-4 my-3">
                        <div><div class="small text-muted">เข้างาน</div><div class="fs-3 fw-bold text-success">{{ substr($today->check_in, 0, 5) }}</div></div>
                        <div><div class="small text-muted">ออกงาน</div><div class="fs-3 fw-bold {{ $today->check_out ? 'text-primary' : 'text-muted' }}">{{ $today->check_out ? substr($today->check_out, 0, 5) : '--:--' }}</div></div>
                    </div>
                    <span class="badge bg-{{ \App\Models\StaffAttendance::STATUSES[$today->status][1] ?? 'secondary' }} fs-6 mb-3">{{ \App\Models\StaffAttendance::STATUSES[$today->status][0] ?? $today->status }}</span>
                    <form method="POST" action="{{ route('checkin.store') }}" data-geo>@csrf <input type="hidden" name="action" value="out">
                        <button class="btn btn-outline-primary btn-lg rounded-pill px-5"><i class="bi bi-box-arrow-right"></i> {{ $today->check_out ? 'ลงเวลาออกใหม่' : 'ลงเวลากลับ' }}</button>
                    </form>
                @endif
            </div>
            <div class="card-footer bg-transparent">
                <form method="POST" action="{{ route('checkin.store') }}" class="d-flex gap-2 justify-content-center flex-wrap">
                    @csrf
                    <input name="note" class="form-control form-control-sm" style="max-width:220px" placeholder="หมายเหตุ เช่น ประชุมที่เขต">
                    <button name="action" value="duty" class="btn btn-sm btn-light border">ไปราชการ</button>
                    <button name="action" value="leave" class="btn btn-sm btn-light border">ลา</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> 30 วันล่าสุด</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>วันที่</th><th>เข้า</th><th>ออก</th><th>สถานะ</th></tr></thead>
                    <tbody>
                    @forelse ($history as $h)
                        <tr>
                            <td>{{ \App\Support\Thai::DAYS_SHORT[$h->date->dayOfWeek] }}. {{ thai_date($h->date) }}</td>
                            <td>{{ $h->check_in ? substr($h->check_in, 0, 5) : '-' }}</td>
                            <td>{{ $h->check_out ? substr($h->check_out, 0, 5) : '-' }}</td>
                            <td><span class="badge bg-{{ \App\Models\StaffAttendance::STATUSES[$h->status][1] ?? 'secondary' }}">{{ \App\Models\StaffAttendance::STATUSES[$h->status][0] ?? $h->status }}</span> <span class="small text-muted">{{ $h->note }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-3">ยังไม่มีประวัติ</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
