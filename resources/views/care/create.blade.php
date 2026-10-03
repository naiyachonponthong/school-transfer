@extends('layouts.app')
@section('title', 'เปิดกรณีดูแลช่วยเหลือ')

@section('content')
<div class="page-head">
    <div><h1>เปิดกรณีดูแลช่วยเหลือ</h1><div class="sub">{{ $student->fullName() }} · {{ $student->classroom?->name() }}</div></div>
    <div class="actions"><a href="{{ route('care.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a></div>
</div>

<form method="POST" action="{{ route('care.store') }}" class="card">
    @csrf
    <input type="hidden" name="student_id" value="{{ $student->id }}">
    <div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label">ด้าน</label><select name="category" class="form-select">@foreach (\App\Models\CareCase::CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">ระดับ</label><select name="level" class="form-select">@foreach (\App\Models\CareCase::LEVELS as $k => [$v])<option value="{{ $k }}" @selected(old('level') === $k)>{{ $v }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">ผู้รับผิดชอบ</label><select name="owner_id" class="form-select">@foreach ($staff as $u)<option value="{{ $u->id }}" @selected((int) old('owner_id', auth()->id()) === $u->id)>{{ $u->name }}</option>@endforeach</select></div>
        <div class="col-12"><label class="form-label">เรื่อง</label><input name="title" value="{{ old('title', $suggested) }}" class="form-control @error('title') is-invalid @enderror" required maxlength="255">@error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
        <div class="col-12"><label class="form-label">รายละเอียด / สิ่งที่พบ</label><textarea name="detail" rows="5" class="form-control">{{ old('detail') }}</textarea></div>
    </div>
    <div class="card-footer bg-transparent text-end"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> เปิดกรณี</button></div>
</form>
@endsection
