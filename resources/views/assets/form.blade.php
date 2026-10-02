@extends('layouts.app')
@section('title', $asset->exists ? 'แก้ไข '.$asset->code : 'เพิ่มครุภัณฑ์')

@section('content')
<div class="page-head"><div><h1>{{ $asset->exists ? 'แก้ไข: '.$asset->code : 'เพิ่มครุภัณฑ์' }}</h1><div class="sub">ช่องที่มี <span class="text-danger">*</span> จำเป็น</div></div></div>

<form method="POST" enctype="multipart/form-data" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}">
    @csrf @if ($asset->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card"><div class="card-body row g-3">
                <div class="col-md-5"><label class="form-label">เลขครุภัณฑ์ <span class="text-danger">*</span></label><input name="code" value="{{ old('code', $asset->code) }}" class="form-control" required autofocus placeholder="เช่น 7440-001-0001"></div>
                <div class="col-md-7"><label class="form-label">ชื่อครุภัณฑ์ <span class="text-danger">*</span></label><input name="name" value="{{ old('name', $asset->name) }}" class="form-control" required></div>
                <div class="col-md-6"><label class="form-label">ประเภท</label>
                    <select name="category" class="form-select" id="category"><option value="">-</option>@foreach (\App\Models\Asset::CATEGORIES as $c => $life)<option value="{{ $c }}" data-life="{{ $life }}" @selected(old('category', $asset->category) === $c)>{{ $c }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">ยี่ห้อ / รุ่น</label><input name="brand" value="{{ old('brand', $asset->brand) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">หมายเลขเครื่อง (Serial)</label><input name="serial_no" value="{{ old('serial_no', $asset->serial_no) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">แหล่งงบ / วิธีได้มา</label><input name="budget_source" value="{{ old('budget_source', $asset->budget_source) }}" class="form-control" list="budgets"></div>
                <datalist id="budgets"><option>เงินงบประมาณ</option><option>เงินอุดหนุน</option><option>เงินรายได้สถานศึกษา</option><option>รับบริจาค</option></datalist>
                <div class="col-md-4"><label class="form-label">วันที่ได้มา</label><input type="date" name="acquired_on" value="{{ old('acquired_on', $asset->acquired_on?->toDateString()) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">ราคา (บาท)</label><input type="number" step="0.01" min="0" name="price" value="{{ old('price', $asset->price) }}" class="form-control"></div>
                <div class="col-md-4"><label class="form-label">อายุการใช้งาน (ปี)</label><input type="number" min="1" name="useful_life" id="life" value="{{ old('useful_life', $asset->useful_life) }}" class="form-control" placeholder="ตามประเภท"></div>
                <div class="col-md-6"><label class="form-label">สถานที่ / ห้อง</label><input name="location" value="{{ old('location', $asset->location) }}" class="form-control" list="locations" placeholder="เช่น ห้องคอมพิวเตอร์ 1"></div>
                <datalist id="locations">@foreach ($locations as $l)<option>{{ $l }}</option>@endforeach</datalist>
                <div class="col-md-6"><label class="form-label">ผู้รับผิดชอบ</label><select name="responsible_id" class="form-select"><option value="">-</option>@foreach ($staff as $u)<option value="{{ $u->id }}" @selected((int) old('responsible_id', $asset->responsible_id) === $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">สถานะ</label><select name="status" class="form-select">@foreach (\App\Models\Asset::STATUSES as $k => [$label])<option value="{{ $k }}" @selected(old('status', $asset->status) === $k)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label">วันที่จำหน่าย</label><input type="date" name="disposed_on" value="{{ old('disposed_on', $asset->disposed_on?->toDateString()) }}" class="form-control"></div>
                <div class="col-12"><label class="form-label">หมายเหตุ</label><textarea name="note" rows="2" class="form-control">{{ old('note', $asset->note) }}</textarea></div>
                <div class="col-12 small text-muted">ค่าเสื่อมราคาคิดแบบเส้นตรงจากวันที่ได้มา คงมูลค่าซาก 1 บาท · อายุการใช้งานตามประเภทเป็นค่าเริ่มต้น ตรวจกับหลักเกณฑ์ของต้นสังกัด</div>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card"><div class="card-header"><i class="bi bi-image"></i> รูป</div><div class="card-body text-center">
                @if ($asset->photo)<img src="{{ asset('storage/'.$asset->photo) }}" alt="" class="img-fluid rounded mb-2" style="max-height:180px">@endif
                <input type="file" name="photo" accept="image/*" capture="environment" class="form-control">
            </div></div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึก</button>
        @unless ($asset->exists)<button name="another" value="1" class="btn btn-outline-primary btn-lg">บันทึกแล้วเพิ่มต่อ (ห้องเดิม)</button>@endunless
        <a href="{{ $asset->exists ? route('assets.show', $asset) : route('assets.index') }}" class="btn btn-light btn-lg border">ยกเลิก</a>
    </div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('category').addEventListener('change', (e) => {
    const life = e.target.selectedOptions[0]?.dataset.life;
    document.getElementById('life').placeholder = life ? `ตามประเภท (${life} ปี)` : 'ตามประเภท';
});
</script>
@endpush
