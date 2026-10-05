@extends('layouts.app')
@section('title', 'ใบ'.\App\Models\PurchaseRequest::KINDS[$purchase->kind].' '.$purchase->req_no)

@section('content')
@php
    $r = $purchase;
    $project = $r->budget->project;
    $kind = \App\Models\PurchaseRequest::KINDS[$r->kind];
    $done = $r->approvals->keyBy('step');
@endphp
<div class="d-flex flex-wrap gap-2 mb-3 no-print">
    <a href="{{ route('purchases.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> ขอซื้อ/ขอจ้าง</a>
    <span class="badge text-bg-{{ $r->statusColor() }} align-self-center fs-6">{{ $r->statusLabel() }}{{ $r->currentStepLabel() ? ' · รอ'.$r->currentStepLabel() : '' }}</span>
    <button onclick="print()" class="btn btn-light border ms-auto"><i class="bi bi-printer"></i> พิมพ์บันทึกข้อความ</button>
    @if ($canCancel)<form method="POST" action="{{ route('purchases.cancel', $r) }}" data-confirm="ยกเลิกใบ{{ $kind }}นี้?">@csrf<button class="btn btn-light border text-danger">ยกเลิกใบนี้</button></form>@endif
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card doc-page"><div class="card-body p-4">
            <div class="d-flex justify-content-between small"><span>{{ school('school_name') }}</span><span>เลขที่ {{ $r->req_no }}</span></div>
            <div class="text-center mb-3"><h2 class="h6 fw-bold mb-0">บันทึกข้อความ{{ $kind }}</h2></div>
            <div class="small mb-3" style="line-height:1.9">
                <div><b>เรื่อง</b> {{ $r->title }} · <b>วันที่</b> {{ \App\Support\Thai::fullDate($r->created_at) }}</div>
                <div><b>โครงการ</b> {{ $project->code }} {{ $project->name }}{{ $project->department ? ' ('.$project->department->name.')' : '' }}</div>
                <div><b>ใช้เงินจาก</b> {{ $r->budget->label() }} · <b>วิธีจัดหา</b> {{ \App\Models\PurchaseRequest::METHODS[$r->method] ?? $r->method }}</div>
                @if ($r->vendor)<div><b>ผู้ขาย/ผู้รับจ้างที่เสนอ</b> {{ $r->vendor }}</div>@endif
                @if ($r->needed_on)<div><b>ต้องการใช้ภายใน</b> {{ thai_date($r->needed_on) }}</div>@endif
                @if ($r->reason)<div><b>เหตุผลความจำเป็น</b> <span style="white-space:pre-line">{{ $r->reason }}</span></div>@endif
            </div>
            <table class="table table-bordered table-sm small">
                <thead class="table-light text-center"><tr><th style="width:40px">ที่</th><th>รายการ</th><th style="width:90px">ประเภท</th><th style="width:110px">จำนวน</th><th style="width:110px">ราคา/หน่วย</th><th style="width:120px">รวม (บาท)</th></tr></thead>
                <tbody>
                @foreach ($r->items as $i => $item)
                    <tr><td class="text-center">{{ $i + 1 }}</td><td>{{ $item->description }}</td><td class="text-center">{{ \App\Models\PurchaseRequest::ITEM_TYPES[$item->item_type] ?? '' }}</td>
                        <td class="text-center">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }} {{ $item->unit }}</td><td class="text-end">{{ baht($item->unit_price) }}</td><td class="text-end">{{ baht($item->amount) }}</td></tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-bold"><td colspan="5" class="text-end">รวมทั้งสิ้น</td><td class="text-end">{{ baht($r->total) }}</td></tr>
                    <tr><td colspan="6" class="text-center">( {{ \App\Support\Thai::bahtText((float) $r->total) }} )</td></tr>
                </tfoot>
            </table>
            <div class="row g-3 mt-4">
                <x-sign class="col-6" role="ผู้ขอ" :name="$r->requester?->name" />
                @foreach ($r->steps as $i => $key)
                    <x-sign class="col-6" :role="\App\Models\PurchaseRequest::STEPS[$key][0]" :name="isset($done[$i]) && $done[$i]->decision === 'approved' ? $done[$i]->user?->name : null" />
                @endforeach
            </div>
        </div></div>
    </div>

    <div class="col-xl-4 no-print">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-diagram-3"></i> การอนุมัติ</div>
            <div class="d-flex gap-2 px-3 py-2 border-bottom small"><i class="bi bi-send text-primary"></i><div class="flex-grow-1">ยื่นโดย {{ $r->requester?->name ?? '-' }}<div class="text-muted">{{ thai_datetime($r->created_at) }}</div></div></div>
            @foreach ($r->steps as $i => $key)
                @php($a = $done[$i] ?? null)
                <div class="d-flex gap-2 px-3 py-2 border-bottom small">
                    <i class="bi {{ $a ? ($a->decision === 'approved' ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger') : ($r->status === 'pending' && $r->step === $i ? 'bi-hourglass-split text-warning' : 'bi-circle text-muted') }}"></i>
                    <div class="flex-grow-1">{{ \App\Models\PurchaseRequest::STEPS[$key][0] }}
                        @if ($a)<div class="text-muted">{{ $a->decision === 'approved' ? 'อนุมัติ' : 'ไม่อนุมัติ' }}โดย {{ $a->user?->name ?? '-' }} · {{ thai_datetime($a->created_at) }}{{ $a->note ? ' · '.$a->note : '' }}</div>
                        @elseif ($r->status === 'pending' && $r->step === $i)<div class="text-muted">กำลังรอพิจารณา</div>@endif
                    </div>
                </div>
            @endforeach
            @if ($r->status === 'cancelled')<div class="px-3 py-2 small text-muted">ผู้ขอยกเลิกเมื่อ {{ thai_datetime($r->decided_at) }}</div>@endif
            @if ($r->status === 'approved')<div class="px-3 py-2 small text-success"><i class="bi bi-lock"></i> ผูกพันงบแล้ว {{ baht($r->total) }} บาท</div>@endif
        </div>

        <div class="card mb-3"><div class="card-body small">
            <div class="fw-semibold mb-1">งบของบรรทัดนี้</div>
            <div class="d-flex justify-content-between"><span>งบ</span><span>{{ baht($r->budget->amount) }}</span></div>
            <div class="d-flex justify-content-between"><span>ผูกพันแล้ว</span><span>{{ baht($r->budget->committed()) }}</span></div>
            <div class="d-flex justify-content-between"><span>รออนุมัติ (รวมใบนี้ถ้ายังรอ)</span><span>{{ baht($r->budget->pending()) }}</span></div>
            <div class="d-flex justify-content-between fw-bold border-top mt-1 pt-1"><span>คงเหลือ</span><span>{{ baht($available) }}</span></div>
            <a href="{{ route('projects.show', $project) }}" class="d-block mt-2">เปิดหน้าโครงการ</a>
        </div></div>

        @if ($canDecide)
            <form method="POST" action="{{ route('purchases.decide', $r) }}" class="card">
                @csrf
                <div class="card-header"><i class="bi bi-pencil-square"></i> พิจารณาในขั้น{{ $r->currentStepLabel() }}</div>
                <div class="card-body">
                    <label class="form-label" for="decideNote">หมายเหตุ <span class="text-muted small">(ต้องใส่ถ้าไม่อนุมัติ)</span></label>
                    <input name="note" id="decideNote" class="form-control @error('note') is-invalid @enderror" maxlength="255">
                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="card-footer bg-transparent d-flex gap-2">
                    <button name="decision" value="approved" class="btn btn-success flex-grow-1"><i class="bi bi-check-lg"></i> อนุมัติ</button>
                    <button name="decision" value="rejected" class="btn btn-outline-danger flex-grow-1">ไม่อนุมัติ</button>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
