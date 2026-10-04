@extends('layouts.app')
@section('title', 'ประวัติ '.$user->name)

@section('content')
<div class="page-head">
    <div><h1>{{ $user->name }}</h1><div class="sub">{{ $user->position ?: 'บุคลากร' }}{{ $profile->yearsOfService() !== null ? ' · อายุงาน '.$profile->yearsOfService().' ปี' : '' }} · อบรมสะสม {{ rtrim(rtrim(number_format($trainings->sum('hours'), 1), '0'), '.') }} ชั่วโมง</div></div>
    @if ($canManage)<div class="actions"><a href="{{ route('staff.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> ทะเบียนบุคลากร</a></div>@endif
</div>

@if ($profile->licenseExpiring())
    <div class="alert alert-{{ $profile->licenseDaysLeft() < 0 ? 'danger' : 'warning' }} py-2 small"><i class="bi bi-exclamation-triangle"></i>
        ใบอนุญาตประกอบวิชาชีพ{{ $profile->licenseDaysLeft() < 0 ? 'หมดอายุแล้วเมื่อ' : 'จะหมดอายุวันที่' }} {{ thai_date($profile->license_expires_on) }}{{ $profile->licenseDaysLeft() >= 0 ? ' (อีก '.$profile->licenseDaysLeft().' วัน)' : '' }}
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-6">
        <form method="POST" action="{{ route('staff.update', $user) }}" class="card">
            @csrf @method('PUT')
            <div class="card-header"><i class="bi bi-person-vcard"></i> ประวัติ</div>
            <div class="card-body row g-3">
                <div class="col-md-6"><label class="form-label">เลขประจำตัวประชาชน</label><input name="citizen_id" value="{{ old('citizen_id', $profile->citizen_id) }}" class="form-control @error('citizen_id') is-invalid @enderror" inputmode="numeric" maxlength="13">@error('citizen_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-md-6"><label class="form-label">วันเกิด</label><input type="date" name="birthdate" value="{{ old('birthdate', $profile->birthdate?->toDateString()) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">วิทยฐานะ</label><input name="rank" value="{{ old('rank', $profile->rank) }}" class="form-control" placeholder="เช่น ชำนาญการพิเศษ"></div>
                <div class="col-md-6"><label class="form-label">วันบรรจุ/เริ่มงาน</label><input type="date" name="hired_on" value="{{ old('hired_on', $profile->hired_on?->toDateString()) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">วุฒิการศึกษา</label><input name="education" value="{{ old('education', $profile->education) }}" class="form-control" placeholder="เช่น ค.บ."></div>
                <div class="col-md-6"><label class="form-label">วิชาเอก</label><input name="major" value="{{ old('major', $profile->major) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">เลขที่ใบอนุญาตประกอบวิชาชีพ</label><input name="license_no" value="{{ old('license_no', $profile->license_no) }}" class="form-control"></div>
                <div class="col-md-6"><label class="form-label">ใบอนุญาตหมดอายุ</label><input type="date" name="license_expires_on" value="{{ old('license_expires_on', $profile->license_expires_on?->toDateString()) }}" class="form-control"></div>
                <div class="col-12"><label class="form-label">ที่อยู่</label><textarea name="address" rows="2" class="form-control">{{ old('address', $profile->address) }}</textarea></div>
                <div class="col-12"><label class="form-label">ผู้ติดต่อกรณีฉุกเฉิน</label><input name="emergency_contact" value="{{ old('emergency_contact', $profile->emergency_contact) }}" class="form-control" placeholder="ชื่อ ความสัมพันธ์ เบอร์โทร"></div>
            </div>
            <div class="card-footer bg-transparent text-end"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> บันทึก</button></div>
        </form>
    </div>

    <div class="col-lg-6">
        <form method="POST" action="{{ route('staff.org', $user) }}" class="card mb-3">
            @csrf @method('PUT')
            <div class="card-header"><i class="bi bi-diagram-3"></i> สังกัดและการติดต่อ</div>
            <div class="card-body row g-3">
                <div class="col-md-5">
                    <label class="form-label">รหัสบุคลากร</label>
                    @if ($canManage)
                        <input name="staff_code" value="{{ old('staff_code', $user->staff_code) }}" class="form-control @error('staff_code') is-invalid @enderror" maxlength="20" placeholder="เช่น 90001">
                        @error('staff_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">ใช้เป็นรหัสบุคคลในเครื่องสแกนที่ประตูได้</div>
                    @else
                        <div class="form-control-plaintext">{{ $user->staff_code ?: '-' }}</div>
                    @endif
                </div>
                <div class="col-md-7">
                    <label class="form-label">เบอร์โทร</label>
                    <div class="form-control-plaintext py-1">{{ $user->phone ?: '-' }}</div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="hide_phone" value="1" id="hidePhone" @checked($user->hide_phone)>
                        <label class="form-check-label small" for="hidePhone">ไม่แสดงเบอร์โทรในทะเบียนติดต่อ</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">หน่วยงานที่สังกัด</label>
                    @if ($canManage && $departments->isNotEmpty())
                        <div class="border rounded-3 p-2" style="max-height:200px;overflow:auto">
                            @foreach ($departments as $row)
                                <div class="d-flex align-items-center gap-2 small" style="padding-left:{{ $row['depth'] * 16 }}px">
                                    <div class="form-check flex-grow-1 mb-0">
                                        <input class="form-check-input" type="checkbox" name="departments[]" value="{{ $row['dept']->id }}" id="dept{{ $row['dept']->id }}" @checked($user->departments->contains('id', $row['dept']->id))>
                                        <label class="form-check-label" for="dept{{ $row['dept']->id }}">{{ $row['dept']->name }}</label>
                                    </div>
                                    <label class="text-muted text-nowrap"><input type="radio" name="primary_department" value="{{ $row['dept']->id }}" @checked($user->departments->firstWhere('pivot.is_primary', true)?->id === $row['dept']->id)> หน่วยหลัก</label>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="small">{{ $user->departments->map(fn ($d) => $d->name.($d->pivot->is_primary && $user->departments->count() > 1 ? ' (หลัก)' : ''))->implode(' · ') ?: 'ยังไม่ได้สังกัดหน่วยงาน' }}</div>
                        @if ($canManage)<div class="form-text">ยังไม่มีหน่วยงาน สร้างได้ที่ <a href="{{ route('org.index') }}">โครงสร้างองค์กร</a></div>@endif
                    @endif
                </div>
            </div>
            <div class="card-footer bg-transparent text-end"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> บันทึก</button></div>
        </form>
        <div class="card">
            <div class="card-header"><i class="bi bi-mortarboard"></i> ประวัติการอบรม/พัฒนา</div>
            @forelse ($trainings as $t)
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom small">
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $t->title }}</div>
                        <div class="text-muted">{{ thai_date($t->date) }}{{ $t->organizer ? ' · '.$t->organizer : '' }} · {{ rtrim(rtrim(number_format($t->hours, 1), '0'), '.') }} ชม.</div>
                    </div>
                    @if ($t->file)<a href="{{ route('files.show', ['training', $t->id]) }}" target="_blank" class="btn btn-sm btn-light border" title="เกียรติบัตร"><i class="bi bi-paperclip"></i></a>@endif
                    <form method="POST" action="{{ route('staff.trainings.destroy', $t) }}" data-confirm="ลบรายการนี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>
                </div>
            @empty
                <div class="empty py-4"><i class="bi bi-mortarboard"></i>ยังไม่มีประวัติอบรม</div>
            @endforelse
            <form method="POST" action="{{ route('staff.trainings.store', $user) }}" enctype="multipart/form-data" class="card-body row g-2">
                @csrf
                <div class="col-12"><input name="title" class="form-control form-control-sm" placeholder="หัวข้อการอบรม" required></div>
                <div class="col-6"><input name="organizer" class="form-control form-control-sm" placeholder="หน่วยงานที่จัด"></div>
                <div class="col-3"><input type="date" name="date" max="{{ today()->toDateString() }}" class="form-control form-control-sm" aria-label="วันที่อบรม" required></div>
                <div class="col-3"><input type="number" step="0.5" min="0" name="hours" class="form-control form-control-sm" placeholder="ชม." required></div>
                <div class="col-9"><input type="file" name="file" accept=".pdf,image/*" class="form-control form-control-sm" aria-label="เกียรติบัตร"></div>
                <div class="col-3 d-grid"><button class="btn btn-sm btn-primary"><i class="bi bi-plus"></i> เพิ่ม</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
