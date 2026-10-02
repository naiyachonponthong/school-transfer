@extends('layouts.app')
@section('title', $resource->exists ? 'แก้ไข '.$resource->name : 'เพิ่มรายการที่จองได้')

@section('content')
@php($amenities = $resource->amenityList())
@php($extra = array_diff($amenities, \App\Models\BookableResource::AMENITY_SUGGESTIONS))
<div class="page-head">
    <div><h1>{{ $resource->exists ? 'แก้ไข: '.$resource->name : 'เพิ่มรายการที่จองได้' }}</h1><div class="sub">รายละเอียดและรูปช่วยให้ครูเลือกห้อง/รถได้ถูก</div></div>
    <div class="actions"><a href="{{ route('bookings.resources') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a></div>
</div>

<form method="POST" enctype="multipart/form-data" action="{{ $resource->exists ? route('bookings.resources.update', $resource) : route('bookings.resources.store') }}">
    @csrf @if ($resource->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-info-circle"></i> ข้อมูลทั่วไป</div>
                <div class="card-body row g-3">
                    <div class="col-md-8"><label class="form-label">ชื่อ <span class="text-danger">*</span></label><input name="name" value="{{ old('name', $resource->name) }}" class="form-control form-control-lg" required autofocus placeholder="เช่น ห้องประชุม 1 / รถตู้โรงเรียน"></div>
                    <div class="col-md-4"><label class="form-label">ประเภท</label>
                        <select name="type" class="form-select form-select-lg" id="rtype">@foreach (\App\Models\BookableResource::TYPES as $k => [$label])<option value="{{ $k }}" @selected(old('type', $resource->type) === $k)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label" data-cap-label>ความจุ (คน)</label><input type="number" min="1" name="capacity" value="{{ old('capacity', $resource->capacity) }}" class="form-control"></div>
                    <div class="col-md-8"><label class="form-label">สถานที่ตั้ง / ที่จอด</label><input name="location" value="{{ old('location', $resource->location) }}" class="form-control" placeholder="เช่น อาคาร 2 ชั้น 3 / โรงจอดรถหลังอาคาร 1"></div>
                    <div class="col-md-4" data-vehicle><label class="form-label">ทะเบียนรถ</label><input name="plate_no" value="{{ old('plate_no', $resource->plate_no) }}" class="form-control" placeholder="เช่น นข 1234 ขอนแก่น"></div>
                    <div class="col-md-8"><label class="form-label" data-contact-label>ผู้ดูแล / เบอร์ติดต่อ</label><input name="contact" value="{{ old('contact', $resource->contact) }}" class="form-control" placeholder="เช่น ครูประยุทธ 08x-xxx-xxxx"></div>
                    <div class="col-12"><label class="form-label">รายละเอียด</label><input name="description" value="{{ old('description', $resource->description) }}" class="form-control" placeholder="เช่น จัดโต๊ะแบบ U ได้ 20 ที่นั่ง"></div>
                </div>
            </div>
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-stars"></i> สิ่งอำนวยความสะดวก</div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach (\App\Models\BookableResource::AMENITY_SUGGESTIONS as $i => $a)
                            <input type="checkbox" class="btn-check" name="amenities[]" value="{{ $a }}" id="am{{ $i }}" @checked(in_array($a, old('amenities', $amenities), true))>
                            <label class="btn btn-sm btn-outline-primary rounded-pill" for="am{{ $i }}">{{ $a }}</label>
                        @endforeach
                    </div>
                    <input name="amenities_other" value="{{ old('amenities_other', implode(', ', $extra)) }}" class="form-control" placeholder="อื่น ๆ คั่นด้วยจุลภาค เช่น ลำโพงบลูทูธ, ที่ชาร์จ USB">
                </div>
            </div>
            <div class="card">
                <div class="card-header"><i class="bi bi-shield-check"></i> การจอง</div>
                <div class="card-body">
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="requires_approval" value="1" id="appr" @checked(old('requires_approval', $resource->requires_approval))><label class="form-check-label" for="appr">ต้องให้งานอาคารสถานที่อนุมัติก่อน</label></div>
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act" @checked(old('is_active', $resource->is_active))><label class="form-check-label" for="act">เปิดให้จอง</label></div>
                    <label class="form-label">ข้อปฏิบัติในการใช้ (แสดงตอนจอง)</label>
                    <textarea name="rules" rows="3" class="form-control" placeholder="เช่น ปิดแอร์และไฟหลังใช้ · คืนกุญแจที่ห้องธุรการ · จองรถล่วงหน้าอย่างน้อย 3 วัน">{{ old('rules', $resource->rules) }}</textarea>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card side-sticky">
                <div class="card-header"><i class="bi bi-image"></i> รูป</div>
                <div class="card-body"><x-photo-drop :current="$resource->photoUrl()" hint="แตะเพื่อถ่ายรูปห้อง/รถ" /></div>
            </div>
        </div>
    </div>
    <button class="btn btn-primary btn-lg mt-3"><i class="bi bi-save"></i> บันทึก</button>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const t = document.getElementById('rtype');
    const sync = () => {
        const v = t.value === 'vehicle';
        document.querySelectorAll('[data-vehicle]').forEach((el) => { el.hidden = !v; });
        document.querySelector('[data-cap-label]').textContent = v ? 'จำนวนที่นั่ง' : (t.value === 'equipment' ? 'จำนวน (ชุด)' : 'ความจุ (คน)');
        document.querySelector('[data-contact-label]').textContent = v ? 'คนขับ / เบอร์ติดต่อ' : 'ผู้ดูแล / เบอร์ติดต่อ';
    };
    t.addEventListener('change', sync); sync();
})();
</script>
@endpush
