@extends('layouts.app')
@section('title', $scholarship->name)

@section('content')
@php
    $s = $scholarship;
    $pending = $awards->whereIn('status', ['nominated', 'reserve']);
    $year = $s->year;
@endphp
<div class="page-head">
    <div><h1>{{ $s->name }}</h1><div class="sub">{{ \App\Models\Scholarship::CATEGORIES[$s->category] ?? $s->category }} · ปีการศึกษา {{ $s->year }}{{ $s->donor ? ' · '.$s->donor : '' }} · {{ \App\Models\Scholarship::MODES[$s->mode] }} {{ $s->valueLabel() }}{{ $s->feeItem ? ' จาก'.$s->feeItem->name : '' }}</div></div>
    <div class="actions">
        <a href="{{ route('scholarships.index', ['year' => $s->year]) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> ทุนทั้งหมด</a>
        @if ($canManage)
            <a href="{{ route('scholarships.announce', $s) }}" class="btn btn-light border"><i class="bi bi-megaphone"></i> ประกาศรายชื่อ</a>
            <a href="{{ route('scholarships.export', $s) }}" class="btn btn-light border"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
            <button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#editScholarship"><i class="bi bi-pencil"></i> แก้ไขทุน</button>
        @endif
        @if ($candidates->isNotEmpty())<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#nominate"><i class="bi bi-person-plus"></i> เสนอชื่อ</button>@endif
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-success"><i class="bi bi-mortarboard"></i></div><div><div class="stat-value">{{ number_format($approvedCount) }}{{ $s->slots !== null ? ' / '.number_format($s->slots) : '' }}</div><div class="stat-label">ได้รับทุน{{ $s->slots !== null ? ' / จำนวนทุน' : '' }}</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-primary"><i class="bi bi-cash-coin"></i></div><div><div class="stat-value">{{ $s->isPercent() ? '-' : baht($approvedAmount) }}</div><div class="stat-label">{{ $s->budget !== null ? 'ใช้ไปจากงบ '.baht($s->budget) : 'มูลค่าที่อนุมัติแล้ว' }}</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-warning"><i class="bi bi-hourglass-split"></i></div><div><div class="stat-value">{{ $awards->where('status', 'nominated')->count() }}</div><div class="stat-label">รอพิจารณา</div></div></div></div></div>
    <div class="col-6 col-md-3"><div class="card"><div class="stat"><div class="stat-icon tint-info"><i class="bi bi-calendar-check"></i></div><div><div class="stat-value fs-6">{{ $s->acceptsNominations() ? 'เปิดรับ' : 'ปิดรับ' }}</div><div class="stat-label">{{ $s->closes_on ? 'เสนอชื่อได้ถึง '.thai_date($s->closes_on) : 'การเสนอชื่อ' }}</div></div></div></div></div>
</div>

@if ($s->conditions)
    <div class="card mb-3"><div class="card-header"><i class="bi bi-list-check"></i> เงื่อนไข/คุณสมบัติ</div><div class="card-body small" style="white-space:pre-line">{{ $s->conditions }}</div></div>
@endif

<form method="POST" action="{{ route('scholarships.decide', $s) }}" id="decideForm">
    @csrf
    <div class="card">
        <div class="card-header"><i class="bi bi-people"></i> รายชื่อ {{ $canManage ? '' : '(เฉพาะนักเรียนในห้องที่คุณดูแล)' }}</div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr>
                @if ($canManage)<th style="width:40px"><input type="checkbox" class="form-check-input" id="checkAll" aria-label="เลือกทั้งหมดที่พิจารณาได้" @disabled($pending->isEmpty())></th>@endif
                <th>นักเรียน</th><th>เหตุผลที่เสนอ</th><th class="text-end">ผลการเรียน</th><th class="text-end">ความประพฤติ</th><th class="text-end">มาเรียน</th><th>เยี่ยมบ้าน</th><th>สถานะ</th><th></th>
            </tr></thead>
            <tbody>
            @forelse ($awards as $a)
                @php($f = $facts[$a->student_id])
                <tr>
                    @if ($canManage)<td>@if (in_array($a->status, ['nominated', 'reserve'], true))<input type="checkbox" class="form-check-input award" name="awards[]" value="{{ $a->id }}" aria-label="เลือก {{ $a->student->fullName() }}">@endif</td>@endif
                    <td><a href="{{ route('students.show', $a->student) }}" class="fw-semibold">{{ $a->student->fullName() }}</a><div class="small text-muted">{{ $a->student->student_code }} · {{ $a->student->classroom?->name() ?? '-' }}</div></td>
                    <td class="small" style="max-width:320px">{{ $a->reason }}<div class="text-muted">เสนอโดย {{ $a->nominator?->name ?? '-' }}</div></td>
                    <td class="text-end">@if ($f['gpa'] !== null){{ number_format($f['gpa'], 2) }}<div class="small text-muted">เกรดเฉลี่ย</div>@elseif ($f['percent'] !== null){{ $f['percent'] }}%<div class="small text-muted">คะแนนที่เก็บแล้ว</div>@else-@endif</td>
                    <td class="text-end">{{ $f['behavior'] }}</td>
                    <td class="text-end">{{ $f['attendance'] !== null ? $f['attendance'].'%' : '-' }}</td>
                    <td class="small">{{ $f['visited'] ? 'เยี่ยมแล้ว' : 'ยังไม่ได้เยี่ยม' }}</td>
                    <td>
                        <span class="badge bg-{{ $a->statusColor() }}">{{ $a->statusLabel() }}</span>
                        @if ($a->decision_note)<div class="small text-muted">{{ $a->decision_note }}</div>@endif
                        @if ($a->paid_at)<div class="small text-muted">จ่ายแล้ว {{ thai_date($a->paid_at) }} · <a href="{{ route('scholarships.receipt', $a) }}">{{ $a->doc_no }}</a></div>@endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if ($a->status === 'nominated' && ($canManage || $a->nominated_by === auth()->id()))
                            <button type="submit" form="withdraw{{ $a->id }}" class="btn btn-sm btn-light border">ถอนชื่อ</button>
                        @endif
                        @if ($canManage && $a->status === 'approved')
                            @if ($s->mode === 'cash' && ! $a->paid_at)<button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#pay{{ $a->id }}">จ่ายทุน</button>@endif
                            @unless ($a->paid_at)<button type="button" class="btn btn-sm btn-light border text-danger" data-bs-toggle="modal" data-bs-target="#revoke{{ $a->id }}">เพิกถอน</button>@endunless
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="empty py-4"><i class="bi bi-people"></i>ยังไม่มีการเสนอชื่อ{{ $candidates->isNotEmpty() ? ' กด "เสนอชื่อ" เพื่อเริ่ม' : '' }}</div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        @if ($canManage && $pending->isNotEmpty())
            <div class="card-body border-top row g-3">
                <div class="col-md-3">
                    <label class="form-label">ผลการพิจารณา</label>
                    <select name="decision" class="form-select">
                        <option value="approved">อนุมัติให้ทุน</option><option value="reserve">สำรอง</option><option value="rejected">ไม่อนุมัติ</option>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">หมายเหตุ <span class="text-muted small">(ไม่บังคับ)</span></label><input name="note" class="form-control" maxlength="255" placeholder="เช่น มติคณะกรรมการครั้งที่ 2/2569"></div>
                <div class="col-md-3 d-flex align-items-end"><button class="btn btn-primary w-100" id="decideBtn" disabled><i class="bi bi-check-lg"></i> บันทึกผลของคนที่เลือก</button></div>
                <div class="col-12 small text-muted">อนุมัติแล้วผู้ปกครองได้รับแจ้งเตือน{{ $s->mode === 'discount' ? ' และระบบสร้างส่วนลดประจำตัวให้ มีผลกับใบแจ้งหนี้ที่ออกจากผังค่าธรรมเนียมหลังจากนี้' : ' จากนั้นกด "จ่ายทุน" เมื่อมอบเงินแล้ว' }} · ผู้ปกครองเห็นเฉพาะทุนที่อนุมัติแล้ว</div>
            </div>
        @endif
    </div>
</form>

@foreach ($awards->where('status', 'nominated') as $a)
    <form method="POST" action="{{ route('scholarships.withdraw', $a) }}" id="withdraw{{ $a->id }}" data-confirm="ถอนการเสนอชื่อ {{ $a->student->fullName() }}?">@csrf @method('DELETE')</form>
@endforeach

@if ($canManage)
    @foreach ($awards->where('status', 'approved')->whereNull('paid_at') as $a)
        @if ($s->mode === 'cash')
            <div class="modal fade" id="pay{{ $a->id }}" tabindex="-1">
                <div class="modal-dialog"><form method="POST" action="{{ route('scholarships.pay', $a) }}" class="modal-content">
                    @csrf
                    <div class="modal-header"><h5 class="modal-title">จ่ายทุน {{ baht($a->amount) }} บาท</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
                    <div class="modal-body">
                        <div class="small text-muted mb-2">{{ $a->student->fullName() }} · ระบบออกเลขที่ใบสำคัญรับเงินให้ บันทึกแล้วแก้หรือเพิกถอนไม่ได้</div>
                        <label class="form-label">ผู้รับเงิน (ชื่อผู้ปกครองหรือนักเรียน)</label><input name="received_by" class="form-control" maxlength="255" required>
                    </div>
                    <div class="modal-footer"><button class="btn btn-primary">บันทึกการจ่าย</button></div>
                </form></div>
            </div>
        @endif
        <div class="modal fade" id="revoke{{ $a->id }}" tabindex="-1">
            <div class="modal-dialog"><form method="POST" action="{{ route('scholarships.revoke', $a) }}" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">เพิกถอนทุนของ {{ $a->student->fullName() }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
                <div class="modal-body">
                    <div class="small text-muted mb-2">{{ $s->mode === 'discount' ? 'ส่วนลดประจำตัวที่สร้างจากทุนนี้จะถูกปิด ใบแจ้งหนี้ที่ออกไปแล้วไม่เปลี่ยน' : 'ทุนจะกลับไปว่างให้คนอื่นได้' }}</div>
                    <label class="form-label">เหตุผล</label><input name="note" class="form-control" maxlength="255" required>
                </div>
                <div class="modal-footer"><button class="btn btn-danger">เพิกถอนทุน</button></div>
            </form></div>
        </div>
    @endforeach

    <div class="modal fade" id="editScholarship" tabindex="-1">
        <div class="modal-dialog modal-lg"><form method="POST" action="{{ route('scholarships.update', $s) }}" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">แก้ไขทุน</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body row g-3">@include('scholarships._fields', ['s' => $s])</div>
            <div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div>
        </form></div>
    </div>
@endif

@if ($candidates->isNotEmpty())
    <div class="modal fade" id="nominate" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('scholarships.nominate', $s) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">เสนอชื่อรับทุน</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body row g-3">
                <div class="col-12">
                    <label class="form-label">นักเรียน</label>
                    <select name="student_id" class="form-select" required>
                        <option value="">— เลือกหรือค้นหา —</option>
                        @foreach ($candidates as $c)<option value="{{ $c->id }}" @selected((int) old('student_id') === $c->id)>{{ $c->classroom?->name() }} เลขที่ {{ $c->number }} · {{ $c->fullName() }} ({{ $c->student_code }})</option>@endforeach
                    </select>
                </div>
                <div class="col-12"><label class="form-label">เหตุผล/คุณสมบัติ</label><textarea name="reason" rows="4" class="form-control" maxlength="2000" required placeholder="เช่น ผลการเรียนดีต่อเนื่อง ช่วยงานห้องเรียนสม่ำเสมอ ครอบครัวมีรายได้น้อย">{{ old('reason') }}</textarea></div>
                <div class="col-12 small text-muted">ผู้พิจารณาจะเห็นเกรดเฉลี่ย คะแนนความประพฤติ และเวลาเรียนจากระบบประกอบให้เอง ผู้ปกครองยังไม่เห็นจนกว่าทุนจะอนุมัติ</div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">เสนอชื่อ</button></div>
        </form></div>
    </div>
@endif
@endsection

@push('scripts')
@include('scholarships._script')
<script>
(function () {
    const all = document.getElementById('checkAll'), boxes = [...document.querySelectorAll('.award')], btn = document.getElementById('decideBtn');
    if (!btn) return;
    const sync = () => { btn.disabled = !boxes.some((b) => b.checked); all.checked = boxes.length > 0 && boxes.every((b) => b.checked); };
    all.addEventListener('change', () => { boxes.forEach((b) => { b.checked = all.checked; }); sync(); });
    boxes.forEach((b) => b.addEventListener('change', sync));
})();
</script>
@endpush
