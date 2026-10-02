@extends('layouts.app')
@section('title', 'ส่งใบลา')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="page-head"><div><h1>ส่งใบลา</h1><div class="sub">ครูประจำชั้นจะเห็นทันที ไม่ต้องเขียนใบลากระดาษ</div></div></div>
        <form method="POST" action="{{ route('parent.leave.store') }}" enctype="multipart/form-data" class="card">
            @csrf
            <div class="card-body">
                <label class="form-label">บุตรหลาน</label>
                <div class="d-flex flex-column gap-2 mb-3">
                    @foreach ($children as $c)
                        <label class="border rounded-3 p-2 d-flex align-items-center gap-2" style="cursor:pointer">
                            <input type="radio" name="student_id" value="{{ $c->id }}" class="form-check-input m-0" @checked(old('student_id', $selected ?: ($children->count() === 1 ? $c->id : null)) == $c->id)>
                            <span class="sb-avatar sm">{{ $c->initials() }}</span>
                            <span>{{ $c->fullName() }} <span class="small text-muted">{{ $c->classroom?->name() }}</span></span>
                        </label>
                    @endforeach
                </div>

                <label class="form-label">ประเภทการลา</label>
                <div class="btn-group w-100 mb-3">
                    @foreach (\App\Models\LeaveRequest::TYPES as $k => $v)
                        <input type="radio" class="btn-check" name="type" value="{{ $k }}" id="t{{ $k }}" @checked(old('type', 'sick') === $k)>
                        <label class="btn btn-outline-primary btn-lg" for="t{{ $k }}"><i class="bi {{ $k === 'sick' ? 'bi-thermometer-half' : 'bi-briefcase' }}"></i> {{ $v }}</label>
                    @endforeach
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6"><label class="form-label">ตั้งแต่วันที่</label><input type="date" name="start_date" value="{{ old('start_date', today()->toDateString()) }}" class="form-control" required></div>
                    <div class="col-6"><label class="form-label">ถึงวันที่</label><input type="date" name="end_date" value="{{ old('end_date', today()->toDateString()) }}" class="form-control" required></div>
                </div>

                <label class="form-label">เหตุผล</label>
                <textarea name="reason" rows="3" class="form-control mb-2" required placeholder="เช่น มีไข้ ไปพบแพทย์">{{ old('reason') }}</textarea>
                <div class="d-flex flex-wrap gap-1 mb-3">
                    @foreach (['มีไข้ ไม่สบาย', 'ไปพบแพทย์', 'ธุระทางบ้าน', 'เดินทางต่างจังหวัด'] as $q)
                        <button type="button" class="btn btn-sm btn-light border" onclick="this.form.reason.value='{{ $q }}'">{{ $q }}</button>
                    @endforeach
                </div>

                <label class="form-label">แนบรูป/ใบรับรองแพทย์ (ไม่บังคับ)</label>
                <input type="file" name="attachment" accept="image/*,.pdf" class="form-control">
            </div>
            <div class="card-footer bg-transparent"><button class="btn btn-primary btn-lg w-100"><i class="bi bi-send"></i> ส่งใบลา</button></div>
        </form>
    </div>
</div>
@endsection
