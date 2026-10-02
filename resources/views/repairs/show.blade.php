@extends('layouts.app')
@section('title', $repair->ticket_no.' '.$repair->title)

@section('content')
<div class="page-head">
    <div>
        <h1>@if($repair->priority === 'urgent')<span class="badge text-bg-danger align-middle">ด่วน</span> @endif{{ $repair->title }}</h1>
        <div class="sub">{{ $repair->ticket_no }} · แจ้งโดย {{ $repair->reporter?->name ?? '-' }} · {{ thai_datetime($repair->created_at) }}</div>
    </div>
    <div class="actions"><span class="badge text-bg-{{ $repair->statusColor() }} fs-6">{{ $repair->statusLabel() }}</span></div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-4 fw-normal text-muted">สถานที่</dt><dd class="col-8">{{ $repair->location ?: '-' }}</dd>
                <dt class="col-4 fw-normal text-muted">ครุภัณฑ์</dt><dd class="col-8">@if($repair->asset)<a href="{{ route('assets.show', $repair->asset) }}">{{ $repair->asset->code }} {{ $repair->asset->name }}</a>@else - @endif</dd>
                <dt class="col-4 fw-normal text-muted">รายละเอียด</dt><dd class="col-8">{!! nl2br(e($repair->detail ?: '-')) !!}</dd>
                <dt class="col-4 fw-normal text-muted">ผู้รับผิดชอบ</dt><dd class="col-8">{{ $repair->assignee?->name ?? '-' }}</dd>
                @if ($repair->cost !== null)<dt class="col-4 fw-normal text-muted">ค่าใช้จ่าย</dt><dd class="col-8">{{ number_format($repair->cost, 2) }} บาท</dd>@endif
                @if ($repair->result_note)<dt class="col-4 fw-normal text-muted">ผลการซ่อม</dt><dd class="col-8">{!! nl2br(e($repair->result_note)) !!}</dd>@endif
                @if ($repair->finished_at)<dt class="col-4 fw-normal text-muted">ปิดงาน</dt><dd class="col-8 mb-0">{{ thai_datetime($repair->finished_at) }}</dd>@endif
            </dl>
            @if ($repair->photo)<a href="{{ $repair->photoUrl() }}" target="_blank"><img src="{{ $repair->photoUrl() }}" alt="" class="img-fluid rounded mt-3" style="max-height:320px"></a>@endif
        </div></div>

        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history"></i> ความคืบหน้า</div>
            <div class="list-group list-group-flush">
                @foreach ($repair->updates as $u)
                    <div class="list-group-item small d-flex gap-2">
                        <span class="badge text-bg-{{ \App\Models\RepairRequest::STATUSES[$u->status][1] ?? 'secondary' }} align-self-start">{{ \App\Models\RepairRequest::STATUSES[$u->status][0] ?? $u->status }}</span>
                        <span class="flex-grow-1">{{ $u->note }}<div class="text-muted">{{ $u->user?->name ?? '-' }} · {{ thai_datetime($u->created_at) }}</div></span>
                    </div>
                @endforeach
            </div>
        </div>
        @if ($repair->status === 'pending' && ($repair->reporter_id === auth()->id() || $manager))
            <form method="POST" action="{{ route('repairs.cancel', $repair) }}" class="mt-3" data-confirm="ยกเลิกการแจ้งซ่อมนี้?">@csrf<button class="btn btn-sm btn-link text-danger px-0">ยกเลิกการแจ้งซ่อม</button></form>
        @endif
    </div>

    @if ($manager)
        <div class="col-lg-5">
            <form method="POST" action="{{ route('repairs.update', $repair) }}" class="card">
                @csrf @method('PUT')
                <div class="card-header"><i class="bi bi-gear"></i> จัดการงานซ่อม</div>
                <div class="card-body">
                    <label class="form-label">สถานะ</label>
                    <select name="status" class="form-select mb-3">@foreach (\App\Models\RepairRequest::STATUSES as $k => [$label])<option value="{{ $k }}" @selected($repair->status === $k)>{{ $label }}</option>@endforeach</select>
                    <label class="form-label">ผู้รับผิดชอบ / ช่าง</label>
                    <select name="assignee_id" class="form-select mb-3"><option value="">-</option>@foreach ($staff as $u)<option value="{{ $u->id }}" @selected($repair->assignee_id === $u->id)>{{ $u->name }}</option>@endforeach</select>
                    <label class="form-label">ค่าใช้จ่าย (บาท)</label>
                    <input type="number" step="0.01" min="0" name="cost" value="{{ $repair->cost }}" class="form-control mb-3">
                    <label class="form-label">ผลการซ่อม</label>
                    <textarea name="result_note" rows="2" class="form-control mb-3" placeholder="เช่น เปลี่ยนคาปาซิเตอร์ ล้างแอร์">{{ $repair->result_note }}</textarea>
                    <label class="form-label">ข้อความถึงผู้แจ้ง (บันทึกในความคืบหน้า)</label>
                    <input name="note" class="form-control" placeholder="เช่น ช่างจะเข้าไปดูพรุ่งนี้ช่วงบ่าย">
                    <div class="form-text">เปลี่ยนสถานะแล้วผู้แจ้งจะได้รับแจ้งเตือนทาง LINE{{ $repair->asset ? ' · สถานะครุภัณฑ์ปรับตามงานซ่อมอัตโนมัติ' : '' }}</div>
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-primary w-100">บันทึก</button></div>
            </form>
        </div>
    @endif
</div>
@endsection
