@extends('layouts.app')
@section('title', 'แจ้งซ่อม')

@section('content')
<div class="page-head">
    <div><h1>แจ้งซ่อม</h1><div class="sub">ถ่ายรูปจุดที่เสีย บอกอาการสั้น ๆ งานอาคารสถานที่จะได้รับแจ้งทันที</div></div>
    <div class="actions"><a href="{{ route('repairs.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> รายการแจ้งซ่อม</a></div>
</div>
<form method="POST" action="{{ route('repairs.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            @if ($asset)
                <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                <div class="card mb-3"><div class="card-body d-flex gap-3 align-items-center">
                    <span class="media-thumb" style="width:64px;height:64px">@if($asset->photo)<img src="{{ asset('storage/'.$asset->photo) }}" alt="">@else<i class="bi bi-box-seam"></i>@endif</span>
                    <div><div class="fw-semibold">{{ $asset->name }}</div><div class="small text-muted">{{ $asset->code }} · {{ $asset->location ?: '-' }}{{ $asset->brand ? ' · '.$asset->brand : '' }}</div></div>
                </div></div>
            @endif
            <div class="card"><div class="card-body row g-3">
                <div class="col-12"><label class="form-label">อาการ / สิ่งที่เสีย <span class="text-danger">*</span></label><input name="title" value="{{ old('title') }}" class="form-control form-control-lg" required autofocus placeholder="เช่น แอร์ไม่เย็น มีน้ำหยด"></div>
                <div class="col-md-7"><label class="form-label">สถานที่ @unless($asset)<span class="text-danger">*</span>@endunless</label><input name="location" value="{{ old('location', $asset?->location) }}" class="form-control" list="locations" @unless($asset) required @endunless placeholder="เช่น อาคาร 2 ห้อง 204"></div>
                <datalist id="locations">@foreach ($locations as $l)<option>{{ $l }}</option>@endforeach</datalist>
                <div class="col-md-5"><label class="form-label">ความเร่งด่วน</label>
                    <div class="btn-group w-100">@foreach (\App\Models\RepairRequest::PRIORITIES as $k => $label)<input type="radio" class="btn-check" name="priority" id="p-{{ $k }}" value="{{ $k }}" @checked(old('priority', 'normal') === $k)><label class="btn btn-outline-{{ $k === 'urgent' ? 'danger' : 'secondary' }}" for="p-{{ $k }}">{{ $label }}</label>@endforeach</div>
                </div>
                <div class="col-12"><label class="form-label">รายละเอียดเพิ่มเติม</label><textarea name="detail" rows="4" class="form-control" placeholder="เริ่มเสียเมื่อไร ใช้งานได้บางส่วนไหม มีอันตรายหรือไม่">{{ old('detail') }}</textarea></div>
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card side-sticky">
                <div class="card-header"><i class="bi bi-camera"></i> รูปถ่ายจุดที่เสีย</div>
                <div class="card-body"><x-photo-drop hint="แตะเพื่อถ่ายรูป" /></div>
                <div class="card-footer bg-transparent"><button class="btn btn-primary btn-lg w-100"><i class="bi bi-send"></i> ส่งแจ้งซ่อม</button></div>
            </div>
        </div>
    </div>
</form>
@endsection
