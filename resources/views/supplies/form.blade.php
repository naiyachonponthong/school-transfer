@extends('layouts.app')
@section('title', $supply->exists ? 'แก้ไข '.$supply->name : 'เพิ่มวัสดุ')

@section('content')
<div class="page-head">
    <div><h1>{{ $supply->exists ? 'แก้ไขวัสดุ' : 'เพิ่มวัสดุ' }}</h1><div class="sub">ช่องที่มี <span class="text-danger">*</span> จำเป็น · รูปช่วยให้ครูเลือกเบิกถูกชิ้น</div></div>
    <div class="actions"><a href="{{ $supply->exists ? route('supplies.show', $supply) : route('supplies.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a></div>
</div>

<form method="POST" enctype="multipart/form-data" action="{{ $supply->exists ? route('supplies.update', $supply) : route('supplies.store') }}">
    @csrf @if ($supply->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-info-circle"></i> ข้อมูลวัสดุ</div>
                <div class="card-body row g-3">
                    <div class="col-md-8"><label class="form-label">ชื่อวัสดุ <span class="text-danger">*</span></label><input name="name" value="{{ old('name', $supply->name) }}" class="form-control form-control-lg" required autofocus placeholder="เช่น กระดาษ A4 80 แกรม"></div>
                    <div class="col-md-4"><label class="form-label">รหัสวัสดุ</label><input name="code" value="{{ old('code', $supply->code) }}" class="form-control" placeholder="เช่น ST-001"></div>
                    <div class="col-md-6"><label class="form-label">หมวด</label><input name="category" value="{{ old('category', $supply->category) }}" class="form-control" list="cats" placeholder="เลือกหรือพิมพ์ใหม่"></div>
                    <datalist id="cats">@foreach ($categories as $c)<option>{{ $c }}</option>@endforeach</datalist>
                    <div class="col-md-3"><label class="form-label">หน่วยนับ <span class="text-danger">*</span></label><input name="unit" value="{{ old('unit', $supply->unit) }}" class="form-control" required list="units"></div>
                    <datalist id="units"><option>ชิ้น</option><option>รีม</option><option>กล่อง</option><option>ด้าม</option><option>แท่ง</option><option>ม้วน</option><option>ขวด</option><option>แพ็ค</option><option>อัน</option><option>ตลับ</option><option>ถุง</option></datalist>
                    <div class="col-md-3"><label class="form-label">ราคาต่อหน่วย (บาท)</label><input type="number" step="0.01" min="0" name="unit_price" value="{{ old('unit_price', $supply->unit_price ?: '') }}" class="form-control" placeholder="0.00"></div>
                    <div class="col-12"><label class="form-label">รายละเอียด / สเปก</label><textarea name="description" rows="3" class="form-control" placeholder="เช่น ยี่ห้อ ขนาด สี รุ่นที่ใช้กับเครื่องพิมพ์">{{ old('description', $supply->description) }}</textarea></div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><i class="bi bi-box-seam"></i> การจัดเก็บ</div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><label class="form-label">ที่เก็บ</label><input name="storage_location" value="{{ old('storage_location', $supply->storage_location) }}" class="form-control" placeholder="เช่น ห้องพัสดุ ตู้ 2 ชั้น 3"></div>
                    <div class="col-md-3"><label class="form-label">แจ้งเตือนเมื่อเหลือ ≤</label><input type="number" min="0" name="min_stock" value="{{ old('min_stock', $supply->min_stock ?: '') }}" class="form-control" placeholder="0 = ไม่เตือน"></div>
                    @if ($supply->exists)
                        <div class="col-md-3"><label class="form-label">คงเหลือ</label><div class="form-control bg-light">{{ number_format($supply->stock) }} {{ $supply->unit }}</div><div class="form-text">เปลี่ยนด้วย "รับเข้า/ปรับยอด"</div></div>
                        <div class="col-12"><input type="hidden" name="is_active" value="0"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="active" @checked(old('is_active', $supply->is_active))><label class="form-check-label" for="active">ยังใช้งาน (ให้ครูเบิกได้)</label></div></div>
                    @else
                        <div class="col-md-3"><label class="form-label">ยอดยกมา</label><input type="number" min="0" name="initial_stock" value="{{ old('initial_stock', 0) }}" class="form-control"></div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card side-sticky">
                <div class="card-header"><i class="bi bi-image"></i> รูปวัสดุ</div>
                <div class="card-body"><x-photo-drop :current="$supply->photoUrl()" /></div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึก</button>
        @unless ($supply->exists)<button name="another" value="1" class="btn btn-outline-primary btn-lg">บันทึกแล้วเพิ่มต่อ</button>@endunless
    </div>
</form>
@endsection
