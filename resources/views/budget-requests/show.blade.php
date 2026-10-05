@extends('layouts.app')
@section('title', 'ใบ'.$r->typeLabel().' '.$r->req_no)

@section('content')
@php
    $project = $r->activity->project;
    $done = $r->approvals->keyBy('step');
@endphp
<div class="d-flex flex-wrap gap-2 mb-3 no-print">
    <a href="{{ route('budget-requests.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> คำขอใช้งบ</a>
    <span class="badge text-bg-{{ $r->statusColor() }} align-self-center fs-6">{{ $r->statusLabel() }}{{ $r->currentStepLabel() ? ' · รอ'.$r->currentStepLabel() : '' }}</span>
    <button onclick="print()" class="btn btn-light border ms-auto"><i class="bi bi-printer"></i> พิมพ์บันทึกข้อความ</button>
    @if ($canCancel)<form method="POST" action="{{ route('budget-requests.cancel', $r) }}" data-confirm="ยกเลิกใบ{{ $r->typeLabel() }}นี้?">@csrf<button class="btn btn-light border text-danger">ยกเลิกใบนี้</button></form>@endif
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card doc-page"><div class="card-body p-4">
            <div class="d-flex justify-content-between small"><span>{{ school('school_name') }}</span><span>เลขที่ {{ $r->req_no }}</span></div>
            <div class="text-center mb-3"><h2 class="h6 fw-bold mb-0">บันทึกข้อความ{{ $r->typeLabel() }}</h2></div>
            <div class="small mb-3" style="line-height:1.9">
                <div><b>เรื่อง</b> {{ $r->title }} · <b>วันที่</b> {{ \App\Support\Thai::fullDate($r->created_at) }}</div>
                <div><b>โครงการ</b> {{ $project->code }} {{ $project->name }}{{ $project->department ? ' ('.$project->department->name.')' : '' }}</div>
                <div><b>กิจกรรม</b> {{ $r->activity->name }}@if ($r->method) · <b>วิธีจัดหา</b> {{ \App\Models\BudgetRequest::METHODS[$r->method] ?? $r->method }}@endif</div>
                @if ($r->vendor)<div><b>{{ $r->type === 'buy_hire' ? 'ผู้ขาย/ผู้รับจ้าง' : 'ผู้รับเงิน' }}</b> {{ $r->vendor }}</div>@endif
                @if ($r->needed_on)<div><b>ต้องการใช้ภายใน</b> {{ thai_date($r->needed_on) }}</div>@endif
                @if ($r->reason)<div><b>เหตุผลความจำเป็น</b> <span style="white-space:pre-line">{{ $r->reason }}</span></div>@endif
            </div>
            <table class="table table-bordered table-sm small">
                <thead class="table-light text-center"><tr><th style="width:40px">ที่</th><th>รายการ</th><th style="width:90px">ประเภท</th><th style="width:110px">จำนวน</th><th style="width:110px">ราคา/หน่วย</th><th style="width:120px">รวม (บาท)</th></tr></thead>
                <tbody>
                @foreach ($r->items as $i => $item)
                    <tr><td class="text-center">{{ $i + 1 }}</td><td>{{ $item->description }}</td><td class="text-center">{{ \App\Models\BudgetRequest::ITEM_TYPES[$item->item_type] ?? '' }}</td>
                        <td class="text-center">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} {{ $item->unit }}</td><td class="text-end">{{ baht($item->unit_price) }}</td><td class="text-end">{{ baht($item->amount) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-bold"><td colspan="5" class="text-end">รวมทั้งสิ้น</td><td class="text-end">{{ baht($r->total) }}</td></tr>
                    <tr><td colspan="6" class="text-center">( {{ \App\Support\Thai::bahtText((float) $r->total) }} )</td></tr>
                    @if ($r->cuts->isNotEmpty())<tr><td colspan="6">ตัดงบจาก: {{ $r->cuts->map(fn ($c) => $c->source->name.' '.baht($c->amount).' บาท')->implode(' · ') }}</td></tr>@endif
                </tfoot>
            </table>
            {{-- ช่องลงนาม: ขั้นที่อนุมัติแล้วแสดงลายเซ็นที่เซ็นไว้ ณ ตอนนั้น --}}
            <div class="row g-3 mt-3">
                <div class="col-6 text-center small">
                    <div style="height:56px"></div>
                    <div>ลงชื่อ ...........................................</div><div class="mt-1">( {{ $r->requester?->name ?? '...........................................' }} )</div><div class="text-muted">ผู้ขอ</div>
                </div>
                @foreach ($r->steps as $i => $key)
                    @php($a = $done[$i] ?? null)
                    <div class="col-6 text-center small">
                        <div style="height:56px">@if ($a && $a->decision === 'approved' && $a->signature)<img src="{{ route('files.show', ['budget-approval-sign', $a->id]) }}" alt="ลายเซ็น {{ $a->user?->name }}" style="max-height:56px;max-width:180px">@endif</div>
                        <div>ลงชื่อ ...........................................</div>
                        <div class="mt-1">( {{ $a && $a->decision === 'approved' ? ($a->user?->name ?? '-') : '...........................................' }} )</div>
                        <div class="text-muted">{{ \App\Models\BudgetRequest::STEPS[$key][0] }}{{ $a && $a->decision === 'approved' ? ' · '.thai_date($a->created_at) : '' }}</div>
                    </div>
                @endforeach
            </div>
        </div></div>
    </div>

    <div class="col-xl-4 no-print">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-diagram-3"></i> การพิจารณา</div>
            <div class="d-flex gap-2 px-3 py-2 border-bottom small"><i class="bi bi-send text-primary"></i><div class="flex-grow-1">ยื่นโดย {{ $r->requester?->name ?? '-' }}<div class="text-muted">{{ thai_datetime($r->created_at) }}</div></div></div>
            @foreach ($r->steps as $i => $key)
                @php($a = $done[$i] ?? null)
                <div class="d-flex gap-2 px-3 py-2 border-bottom small">
                    <i class="bi {{ $a ? ($a->decision === 'approved' ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger') : ($r->status === 'pending' && $r->step === $i ? 'bi-hourglass-split text-warning' : 'bi-circle text-muted') }}"></i>
                    <div class="flex-grow-1">{{ \App\Models\BudgetRequest::STEPS[$key][0] }}
                        @if ($a)<div class="text-muted">{{ $a->decision === 'approved' ? ($key === 'cut' ? 'ตัดงบ' : 'อนุมัติ') : 'ไม่อนุมัติ' }}โดย {{ $a->user?->name ?? '-' }} · {{ thai_datetime($a->created_at) }}{{ $a->note ? ' · '.$a->note : '' }}</div>
                        @elseif ($r->status === 'pending' && $r->step === $i)<div class="text-muted">กำลังรอพิจารณา</div>@endif
                    </div>
                </div>
            @endforeach
            @if ($r->status === 'cancelled')<div class="px-3 py-2 small text-muted">ผู้ขอยกเลิกเมื่อ {{ thai_datetime($r->decided_at) }}</div>@endif
        </div>

        <div class="card mb-3"><div class="card-body small">
            <div class="fw-semibold mb-1">งบของกิจกรรม {{ $r->activity->name }}</div>
            @foreach ($bySource as $row)
                <div class="d-flex justify-content-between"><span>{{ $row['source']->name }}</span><span>เหลือ {{ baht($row['left']) }} จาก {{ baht($row['budget']) }}</span></div>
            @endforeach
            <div class="d-flex justify-content-between fw-bold border-top mt-1 pt-1"><span>คงเหลือหลังหักที่รอพิจารณา</span><span>{{ baht($available) }}</span></div>
            <a href="{{ route('projects.show', $project) }}" class="d-block mt-2">เปิดหน้าโครงการ</a>
        </div></div>

        @if ($canDecide)
            <form method="POST" action="{{ route('budget-requests.decide', $r) }}" class="card" id="decideForm">
                @csrf
                <div class="card-header"><i class="bi bi-pencil-square"></i> {{ $isCutStep ? 'ตัดงบ' : 'พิจารณาในขั้น'.$r->currentStepLabel() }}</div>
                <div class="card-body row g-3">
                    @if ($isCutStep)
                        <div class="col-12 small text-muted">ระบุยอดที่ตัดจากแต่ละประเภทเงิน รวมต้องเท่ากับ {{ baht($r->total) }} บาท</div>
                        @foreach ($bySource as $id => $row)
                            <div class="col-12">
                                <label class="form-label small mb-1" for="cut{{ $id }}">{{ $row['source']->name }} <span class="text-muted">(เหลือ {{ baht($row['left']) }})</span></label>
                                <input type="number" name="cuts[{{ $id }}]" id="cut{{ $id }}" value="{{ old('cuts.'.$id, $bySource->count() === 1 ? (float) $r->total : '') }}" class="form-control cut" min="0" max="{{ max(0, $row['left']) }}" step="0.01" inputmode="decimal" placeholder="0">
                            </div>
                        @endforeach
                        <div class="col-12 small" id="cutNote" role="status" aria-live="polite"></div>
                    @endif
                    <div class="col-12">
                        <label class="form-label" for="decideNote">หมายเหตุ <span class="text-muted small">(ต้องใส่ถ้าไม่อนุมัติ)</span></label>
                        <input name="note" id="decideNote" class="form-control @error('note') is-invalid @enderror" maxlength="255">
                        @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @unless ($hasSignature)<div class="col-12 small text-danger">ยังไม่มีลายเซ็นของคุณในระบบ <a href="{{ route('profile') }}">เซ็นที่หน้าข้อมูลส่วนตัว</a>ก่อนจึงอนุมัติได้</div>@endunless
                </div>
                <div class="card-footer bg-transparent d-flex gap-2">
                    <button name="decision" value="approved" class="btn btn-success flex-grow-1" id="approveBtn" @disabled(! $hasSignature)><i class="bi bi-check-lg"></i> {{ $isCutStep ? 'ตัดงบและลงนาม' : 'อนุมัติและลงนาม' }}</button>
                    <button name="decision" value="rejected" class="btn btn-outline-danger flex-grow-1">ไม่อนุมัติ</button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection

@if ($canDecide && $isCutStep)
@push('scripts')
<script>
// ยอดตัดรวมต้องเท่ายอดคำขอจึงกดตัดงบได้
(function () {
    const cuts = [...document.querySelectorAll('.cut')], note = document.getElementById('cutNote'), btn = document.getElementById('approveBtn');
    const total = {{ (float) $r->total }}, signed = {{ $hasSignature ? 'true' : 'false' }};
    const money = (n) => Number(n).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const sync = () => {
        const sum = cuts.reduce((s, c) => s + (Number(c.value) || 0), 0), ok = Math.abs(sum - total) < 0.005;
        note.className = 'col-12 small ' + (ok ? 'text-success' : 'text-danger');
        note.textContent = ok ? `ยอดตัดรวม ${money(sum)} บาท ตรงกับยอดคำขอ` : `ยอดตัดรวม ${money(sum)} บาท ยังต่างจากยอดคำขอ ${money(total - sum)} บาท`;
        btn.disabled = !ok || !signed;
    };
    cuts.forEach((c) => c.addEventListener('input', sync)); sync();
})();
</script>
@endpush
@endif
