@extends('layouts.app')
@section('title', 'เยี่ยมบ้าน '.$student->fullName())

@section('content')
<div class="page-head">
    <div><h1>บันทึกเยี่ยมบ้าน</h1><div class="sub">{{ $student->fullName() }} · {{ $student->classroom?->name() }} · {{ $term->label() }}</div></div>
    <div class="actions"><a href="{{ route('care.visits', ['classroom' => $student->classroom_id]) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a></div>
</div>

<form method="POST" action="{{ route('care.visits.save', $student) }}" enctype="multipart/form-data" class="card" style="max-width:820px">
    @csrf
    <div class="card-body row g-3">
        @if ($student->address)<div class="col-12 small text-muted"><i class="bi bi-geo-alt"></i> {{ $student->address }} · ผู้ปกครอง: {{ $student->guardians->map(fn ($g) => $g->name.' '.$g->phone)->implode(', ') ?: '-' }}</div>@endif
        <div class="col-md-4"><label class="form-label">วันที่เยี่ยม</label><input type="date" name="visited_on" value="{{ old('visited_on', $visit->visited_on?->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control @error('visited_on') is-invalid @enderror" required></div>
        <div class="col-md-8"><label class="form-label">ผู้ปกครองที่พบ</label><input name="guardian_met" value="{{ old('guardian_met', $visit->guardian_met) }}" class="form-control" placeholder="เช่น มารดา, ยาย"></div>
        <div class="col-md-6"><label class="form-label">ที่อยู่อาศัย</label><select name="housing" class="form-select"><option value="">-</option>@foreach (\App\Models\HomeVisit::HOUSING as $k => $v)<option value="{{ $k }}" @selected(old('housing', $visit->housing) === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label">สถานภาพครอบครัว</label><select name="family_status" class="form-select"><option value="">-</option>@foreach (\App\Models\HomeVisit::FAMILY as $k => $v)<option value="{{ $k }}" @selected(old('family_status', $visit->family_status) === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-12">
            <label class="form-label">ด้านที่พบความเสี่ยง</label>
            <div class="d-flex flex-wrap gap-3">
                @foreach (\App\Models\HomeVisit::RISKS as $k => $v)
                    <label class="small"><input type="checkbox" class="form-check-input" name="risks[]" value="{{ $k }}" @checked(in_array($k, old('risks', $visit->risks ?? [])))> {{ $v }}</label>
                @endforeach
            </div>
        </div>
        <div class="col-12"><label class="form-label">บันทึกเพิ่มเติม</label><textarea name="note" rows="4" class="form-control">{{ old('note', $visit->note) }}</textarea></div>
        <div class="col-md-6">
            <label class="form-label">รูปถ่าย</label>
            <input type="file" name="photo" accept="image/*" class="form-control @error('photo') is-invalid @enderror">
            @if ($visit->photo)<a href="{{ route('files.show', ['home-visit', $visit->id]) }}" target="_blank" class="small"><i class="bi bi-image"></i> ดูรูปที่บันทึกไว้</a>@endif
        </div>
        <div class="col-md-6">
            <label class="form-label">พิกัดบ้าน</label>
            <div class="input-group">
                <input name="lat" id="lat" value="{{ old('lat', $visit->lat) }}" class="form-control" placeholder="ละติจูด" inputmode="decimal">
                <input name="lng" id="lng" value="{{ old('lng', $visit->lng) }}" class="form-control" placeholder="ลองจิจูด" inputmode="decimal">
                <button type="button" class="btn btn-light border" id="locate" title="ใช้ตำแหน่งปัจจุบัน"><i class="bi bi-crosshair"></i></button>
            </div>
        </div>
    </div>
    <div class="card-footer bg-transparent text-end"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> บันทึก</button></div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('locate').addEventListener('click', function () {
    if (!navigator.geolocation) return alert('อุปกรณ์นี้ไม่รองรับการระบุตำแหน่ง');
    navigator.geolocation.getCurrentPosition(function (p) {
        document.getElementById('lat').value = p.coords.latitude.toFixed(7);
        document.getElementById('lng').value = p.coords.longitude.toFixed(7);
    }, function () { alert('ไม่ได้รับอนุญาตให้ใช้ตำแหน่ง'); });
});
</script>
@endpush
