@extends('layouts.app')
@section('title', 'จองห้อง / รถ / อุปกรณ์')

@section('content')
<div class="page-head"><div><h1>จองห้อง / รถ / อุปกรณ์</h1><div class="sub">ระบบตรวจเวลาซ้อนให้อัตโนมัติ · รายการที่ต้องอนุมัติจะแจ้งผลทาง LINE</div></div></div>
<form method="POST" action="{{ route('bookings.store') }}" class="card" style="max-width:720px">
    @csrf
    <div class="card-body row g-3">
        <div class="col-12"><label class="form-label">จองอะไร <span class="text-danger">*</span></label>
            <select name="resource_id" class="form-select form-select-lg" required id="resource">
                <option value="">เลือก</option>
                @foreach ($resources->groupBy('type') as $type => $list)
                    <optgroup label="{{ \App\Models\BookableResource::TYPES[$type][0] ?? $type }}">
                        @foreach ($list as $r)<option value="{{ $r->id }}" data-type="{{ $r->type }}" @selected((int) old('resource_id', $selected) === $r->id)>{{ $r->name }}{{ $r->capacity ? ' ('.$r->capacity.' คน)' : '' }}{{ $r->requires_approval ? ' · ต้องอนุมัติ' : '' }}</option>@endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>
        <div class="col-12"><label class="form-label">วัตถุประสงค์ <span class="text-danger">*</span></label><input name="title" value="{{ old('title') }}" class="form-control" required placeholder="เช่น ประชุมกลุ่มสาระ / พานักเรียนแข่งขัน"></div>
        <div class="col-md-4"><label class="form-label">วันที่</label><input type="date" name="date" value="{{ old('date', $date) }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">ตั้งแต่</label><input type="time" name="start_time" value="{{ old('start_time', '09:00') }}" class="form-control" required></div>
        <div class="col-md-4"><label class="form-label">ถึง</label><input type="time" name="end_time" value="{{ old('end_time', '12:00') }}" class="form-control" required></div>
        <div class="col-md-4" data-vehicle hidden><label class="form-label">ถึงวันที่ (ค้างคืน)</label><input type="date" name="end_date" value="{{ old('end_date') }}" class="form-control"></div>
        <div class="col-md-8" data-vehicle hidden><label class="form-label">ปลายทาง</label><input name="destination" value="{{ old('destination') }}" class="form-control"></div>
        <div class="col-md-4"><label class="form-label">จำนวนคน</label><input type="number" min="1" name="attendees" value="{{ old('attendees') }}" class="form-control"></div>
        <div class="col-md-8"><label class="form-label">หมายเหตุ</label><input name="note" value="{{ old('note') }}" class="form-control" placeholder="เช่น ต้องการไมค์ 2 ตัว"></div>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
        <button class="btn btn-primary btn-lg"><i class="bi bi-calendar2-check"></i> จอง</button>
        <a href="{{ route('bookings.index') }}" class="btn btn-light btn-lg border">ยกเลิก</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const sel = document.getElementById('resource');
    const sync = () => document.querySelectorAll('[data-vehicle]').forEach((el) => { el.hidden = sel.selectedOptions[0]?.dataset.type !== 'vehicle'; });
    sel.addEventListener('change', sync); sync();
})();
</script>
@endpush
