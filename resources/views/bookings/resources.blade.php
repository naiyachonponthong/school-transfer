@extends('layouts.app')
@section('title', 'รายการที่จองได้')

@section('content')
<div class="page-head"><div><h1>ห้อง / รถ / อุปกรณ์ที่จองได้</h1><div class="sub">ติ๊ก "ต้องอนุมัติ" = การจองของครูต้องรองานอาคารสถานที่อนุมัติก่อน (เช่น รถตู้ ห้องประชุมใหญ่)</div></div>
    <div class="actions"><a href="{{ route('bookings.index') }}" class="btn btn-light border">กลับ</a></div></div>
<div class="card">
    <div class="list-group list-group-flush">
        @foreach ($resources->push(new \App\Models\BookableResource(['type' => 'room', 'is_active' => true])) as $r)
            <form method="POST" action="{{ $r->exists ? route('bookings.resources.update', $r) : route('bookings.resources.store') }}" class="list-group-item row g-2 align-items-center mx-0 {{ $r->exists ? '' : 'bg-light' }}">
                @csrf @if ($r->exists) @method('PUT') @endif
                <div class="col-md-3"><input name="name" value="{{ $r->name }}" class="form-control form-control-sm" placeholder="{{ $r->exists ? '' : 'เพิ่มใหม่: ชื่อ เช่น ห้องประชุม 1' }}" required></div>
                <div class="col-md-2"><select name="type" class="form-select form-select-sm">@foreach (\App\Models\BookableResource::TYPES as $k => [$label])<option value="{{ $k }}" @selected($r->type === $k)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-1"><input type="number" name="capacity" value="{{ $r->capacity }}" class="form-control form-control-sm" placeholder="คน"></div>
                <div class="col-md-3"><input name="description" value="{{ $r->description }}" class="form-control form-control-sm" placeholder="รายละเอียด"></div>
                <div class="col-md-2 small">
                    <label class="me-2"><input type="checkbox" name="requires_approval" value="1" @checked($r->requires_approval)> ต้องอนุมัติ</label>
                    <input type="hidden" name="is_active" value="0"><label><input type="checkbox" name="is_active" value="1" @checked($r->is_active)> เปิดให้จอง</label>
                </div>
                <div class="col-md-1"><button class="btn btn-sm {{ $r->exists ? 'btn-light border' : 'btn-primary' }} w-100">{{ $r->exists ? 'บันทึก' : 'เพิ่ม' }}</button></div>
            </form>
        @endforeach
    </div>
</div>
@endsection
