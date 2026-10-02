@extends('layouts.app')
@section('title', 'แจ้งซ่อม')

@section('content')
<div class="page-head"><div><h1>แจ้งซ่อม</h1><div class="sub">ถ่ายรูปจุดที่เสีย บอกอาการสั้น ๆ งานอาคารสถานที่จะได้รับแจ้งทันที</div></div></div>
<form method="POST" action="{{ route('repairs.store') }}" enctype="multipart/form-data" class="card" style="max-width:720px">
    @csrf
    <div class="card-body row g-3">
        @if ($asset)
            <input type="hidden" name="asset_id" value="{{ $asset->id }}">
            <div class="col-12"><div class="alert alert-light border mb-0 small"><i class="bi bi-box-seam"></i> <b>{{ $asset->code }}</b> {{ $asset->name }} · {{ $asset->location ?: '-' }}</div></div>
        @endif
        <div class="col-12"><label class="form-label">อาการ / สิ่งที่เสีย <span class="text-danger">*</span></label><input name="title" value="{{ old('title') }}" class="form-control form-control-lg" required autofocus placeholder="เช่น แอร์ไม่เย็น มีน้ำหยด"></div>
        <div class="col-md-7"><label class="form-label">สถานที่ @unless($asset)<span class="text-danger">*</span>@endunless</label><input name="location" value="{{ old('location', $asset?->location) }}" class="form-control" list="locations" @unless($asset) required @endunless placeholder="เช่น อาคาร 2 ห้อง 204"></div>
        <datalist id="locations">@foreach ($locations as $l)<option>{{ $l }}</option>@endforeach</datalist>
        <div class="col-md-5"><label class="form-label">ความเร่งด่วน</label>
            <div class="btn-group w-100">@foreach (\App\Models\RepairRequest::PRIORITIES as $k => $label)<input type="radio" class="btn-check" name="priority" id="p-{{ $k }}" value="{{ $k }}" @checked(old('priority', 'normal') === $k)><label class="btn btn-outline-{{ $k === 'urgent' ? 'danger' : 'secondary' }}" for="p-{{ $k }}">{{ $label }}</label>@endforeach</div>
        </div>
        <div class="col-12"><label class="form-label">รายละเอียดเพิ่มเติม</label><textarea name="detail" rows="3" class="form-control" placeholder="เริ่มเสียเมื่อไร ใช้งานได้บางส่วนไหม">{{ old('detail') }}</textarea></div>
        <div class="col-12"><label class="form-label">รูปถ่าย</label><input type="file" name="photo" accept="image/*" capture="environment" class="form-control"></div>
    </div>
    <div class="card-footer bg-transparent d-flex gap-2">
        <button class="btn btn-primary btn-lg"><i class="bi bi-send"></i> ส่งแจ้งซ่อม</button>
        <a href="{{ route('repairs.index') }}" class="btn btn-light btn-lg border">ยกเลิก</a>
    </div>
</form>
@endsection
