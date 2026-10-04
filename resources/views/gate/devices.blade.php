@extends('layouts.app')
@section('title', 'เครื่องสแกนที่ประตู')

@section('content')
@php
    $online = $devices->filter->isOnline()->count();
    $fields = function (?\App\Models\GateDevice $d = null) {
        return view('gate._device-fields', ['device' => $d])->render();
    };
@endphp
<div class="page-head">
    <div><h1>เครื่องสแกนที่ประตู</h1><div class="sub">เครื่องสแกนใบหน้า/บัตรที่ส่งผลเข้าระบบเอง · {{ $devices->count() }} เครื่อง · ออนไลน์ {{ $online }} · วันนี้ระบุตัวไม่ได้ {{ $unknownToday }} ครั้ง</div></div>
    <div class="actions">
        <a href="{{ route('manual.show', 'gate') }}#devices" class="btn btn-light border"><i class="bi bi-question-circle"></i> วิธีตั้งค่าเครื่อง</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDevice"><i class="bi bi-plus-lg"></i> เพิ่มเครื่อง</button>
    </div>
</div>

<div class="row g-3 mb-3">
    @forelse ($devices as $d)
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header">
                <i class="bi bi-person-bounding-box"></i> {{ $d->name }}
                <span class="ms-auto badge bg-{{ ! $d->is_active ? 'secondary' : ($d->isOnline() ? 'success' : 'warning') }}">{{ ! $d->is_active ? 'ปิดใช้งาน' : ($d->isOnline() ? 'ออนไลน์' : ($d->last_seen_at ? 'ขาดการติดต่อ' : 'ยังไม่เคยส่งข้อมูล')) }}</span>
            </div>
            <div class="card-body">
                <div class="row g-2 small mb-3">
                    <div class="col-6"><span class="text-muted">จุดติดตั้ง</span><br>{{ $d->location ?: '-' }}</div>
                    <div class="col-6"><span class="text-muted">ทิศทาง</span><br>{{ $d->modeLabel() }}</div>
                    <div class="col-6"><span class="text-muted">สแกนวันนี้</span><br><b>{{ $d->today_count }}</b> ครั้ง</div>
                    <div class="col-6"><span class="text-muted">ได้ข้อมูลล่าสุด</span><br>{{ $d->last_seen_at ? thai_datetime($d->last_seen_at) : '-' }}</div>
                </div>
                <label class="form-label small" for="hook{{ $d->id }}">ที่อยู่รับข้อมูล (นำไปตั้งในเครื่อง)</label>
                <div class="input-group input-group-sm mb-3">
                    <input id="hook{{ $d->id }}" class="form-control font-monospace" value="{{ $d->hookUrl() }}" readonly onfocus="this.select()">
                    <button type="button" class="btn btn-light border" onclick="navigator.clipboard.writeText(document.getElementById('hook{{ $d->id }}').value);this.innerHTML='<i class=\'bi bi-check2\'></i> คัดลอกแล้ว'"><i class="bi bi-copy"></i> คัดลอก</button>
                </div>
                <form method="POST" action="{{ route('gate.devices.simulate', $d) }}" class="input-group input-group-sm">
                    @csrf
                    <input name="code" class="form-control" placeholder="รหัสนักเรียน หรือชื่อผู้ใช้ของครู" aria-label="รหัสสำหรับทดสอบ" required {{ $d->is_active ? '' : 'disabled' }}>
                    <button class="btn btn-light border" {{ $d->is_active ? '' : 'disabled' }}><i class="bi bi-play-fill"></i> ทดสอบสแกน</button>
                </form>
                <div class="form-text">ทดสอบจะบันทึกการมาเรียน/เวลาทำงานจริงของคนนั้น เหมือนเครื่องส่งมา</div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#editDevice{{ $d->id }}"><i class="bi bi-pencil"></i> แก้ไข</button>
                <form method="POST" action="{{ route('gate.devices.rotate', $d) }}" data-confirm="ออกที่อยู่รับข้อมูลใหม่? ที่อยู่เดิมจะใช้ไม่ได้ทันที ต้องตั้งในเครื่องใหม่">@csrf
                    <button class="btn btn-sm btn-light border"><i class="bi bi-arrow-repeat"></i> ออกที่อยู่ใหม่</button>
                </form>
                <form method="POST" action="{{ route('gate.devices.destroy', $d) }}" class="ms-auto" data-confirm="ลบเครื่อง {{ $d->name }}? ประวัติการสแกนของเครื่องนี้จะถูกลบด้วย (ข้อมูลการมาเรียนยังอยู่)">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> ลบ</button>
                </form>
            </div>
        </div></div>
    @empty
        <div class="col-12"><div class="card"><div class="card-body"><div class="empty"><i class="bi bi-person-bounding-box"></i>ยังไม่มีเครื่องสแกน กด "เพิ่มเครื่อง" เพื่อออกที่อยู่รับข้อมูลสำหรับเครื่องแต่ละตัว<br><span class="small">ยังไม่มีเครื่องจริงก็เพิ่มไว้แล้วกด "ทดสอบสแกน" ดูผลได้</span></div></div></div></div>
    @endforelse
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-person-badge"></i> รูปใบหน้าสำหรับลงทะเบียนในเครื่อง
        @if ($faces['form'])<span class="ms-auto badge bg-success-subtle text-success-emphasis">พร้อมส่งออก {{ $faces['ready']->count() }} จาก {{ $faces['total'] }} คน</span>@endif
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">ใบหน้าเป็นข้อมูลชีวภาพ ต้องได้รับความยินยอมจากผู้ปกครองก่อน ระบบจึงส่งออกรูปเฉพาะนักเรียนที่ผู้ปกครองกด <b>อนุญาต</b> ในหนังสือที่เลือกไว้ด้านล่าง · นักเรียนที่ไม่ยินยอมยังใช้บัตร QR ได้ตามเดิม</p>
        <form method="POST" action="{{ route('gate.devices.consent') }}" class="row g-2 align-items-end mb-3">
            @csrf
            <div class="col-md-8">
                <label class="form-label small" for="faceConsent">หนังสือขออนุญาตที่ใช้เป็นความยินยอมสแกนใบหน้า</label>
                <select name="consent_form_id" id="faceConsent" class="form-select">
                    <option value="">— ยังไม่เลือก —</option>
                    @foreach ($consentForms as $f)
                        <option value="{{ $f->id }}" @selected($faces['form']?->id === $f->id)>{{ $f->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-light border w-100">บันทึก</button></div>
            <div class="col-md-2"><a href="{{ route('consents.index') }}" class="btn btn-light border w-100"><i class="bi bi-plus-lg"></i> สร้างหนังสือ</a></div>
        </form>
        @if ($faces['form'])
            <div class="d-flex flex-wrap align-items-center gap-3">
                <a href="{{ route('gate.devices.faces') }}" class="btn btn-primary {{ $faces['ready']->isEmpty() ? 'disabled' : '' }}"><i class="bi bi-download"></i> ดาวน์โหลดรูป + รายชื่อ (.zip)</a>
                <div class="small text-muted">ยินยอมแล้ว {{ $faces['ready']->count() + $faces['noPhoto']->count() }} คน · มีรูปพร้อมส่งออก {{ $faces['ready']->count() }} · ยังไม่ยินยอม/ยังไม่ตอบ {{ $faces['total'] - $faces['ready']->count() - $faces['noPhoto']->count() }}</div>
            </div>
            @if ($faces['noPhoto']->isNotEmpty())
                <div class="small mt-3"><span class="fw-semibold text-warning-emphasis"><i class="bi bi-exclamation-triangle"></i> ยินยอมแล้วแต่ยังไม่มีรูปในระบบ {{ $faces['noPhoto']->count() }} คน</span> (เพิ่มรูปที่หน้าข้อมูลนักเรียน หรือถ่ายที่เครื่องโดยตรง)
                    <div class="text-muted">{{ $faces['noPhoto']->take(60)->map(fn ($s) => $s->student_code.' '.$s->first_name.' ('.$s->classroom?->name().')')->implode(' · ') }}{{ $faces['noPhoto']->count() > 60 ? ' …' : '' }}</div>
                </div>
            @endif
        @else
            <div class="small text-muted"><i class="bi bi-info-circle"></i> เลือกหนังสือยินยอมก่อน จึงจะดาวน์โหลดรูปได้</div>
        @endif
        <hr class="my-4">
        <div class="fw-semibold mb-1"><i class="bi bi-person-workspace"></i> ครูและบุคลากร</div>
        <p class="small text-muted mb-2">ครูสแกนที่เครื่องเดียวกันได้ ระบบลงเป็น<b>เวลาทำงานครู</b> · รหัสบุคคลในเครื่องของครู = <b>ชื่อผู้ใช้</b> (แสดงในวงเล็บ) · ติ๊กเฉพาะคนที่ให้ความยินยอมใช้ใบหน้าเป็นเอกสารกับโรงเรียนแล้ว รูปที่ส่งออกคือรูปโปรไฟล์ของบัญชี</p>
        @if ($staff['clashes']->isNotEmpty())
            <div class="small text-danger mb-2"><i class="bi bi-exclamation-octagon"></i> ชื่อผู้ใช้ซ้ำกับรหัสนักเรียน เครื่องจะนับเป็นนักเรียน ต้องเปลี่ยนชื่อผู้ใช้ก่อน: {{ $staff['clashes']->map(fn ($u) => $u->name.' ('.$u->username.')')->implode(' · ') }}</div>
        @endif
        <form method="POST" action="{{ route('gate.devices.staff-consent') }}">
            @csrf
            <div class="row g-1 mb-2">
                @foreach ($staff['all'] as $u)
                    <div class="col-sm-6 col-lg-4">
                        <div class="form-check small">
                            <input class="form-check-input" type="checkbox" name="staff[]" value="{{ $u->id }}" id="staffFace{{ $u->id }}" @checked($u->face_consent_at)>
                            <label class="form-check-label" for="staffFace{{ $u->id }}">{{ $u->name }} <span class="text-muted">({{ $u->username }})</span>@if (! $u->avatar) <span class="text-warning-emphasis" title="ยังไม่มีรูปโปรไฟล์"><i class="bi bi-image"></i> ไม่มีรูป</span>@endif</label>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="d-flex flex-wrap align-items-center gap-3">
                <button class="btn btn-light border">บันทึกความยินยอมของบุคลากร</button>
                @if ($staff['ready']->isNotEmpty() && ! $faces['form'])<a href="{{ route('gate.devices.faces') }}" class="btn btn-primary"><i class="bi bi-download"></i> ดาวน์โหลดรูป + รายชื่อ (.zip)</a>@endif
                <div class="small text-muted">ยินยอมแล้ว {{ $staff['ready']->count() + $staff['noPhoto']->count() }} จาก {{ $staff['all']->count() }} คน · มีรูปพร้อมส่งออก {{ $staff['ready']->count() }} (รวมอยู่ในไฟล์ zip เดียวกัน โฟลเดอร์ staff)</div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-list-ul"></i> การสแกนล่าสุดจากทุกเครื่อง</div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>เวลา</th><th>เครื่อง</th><th>ผู้สแกน</th><th>รหัสที่ส่งมา</th><th>ผล</th></tr></thead>
            <tbody>
            @forelse ($events as $e)
                @php([$label, $color] = \App\Models\GateEvent::RESULTS[$e->result] ?? [$e->result, 'secondary'])
                <tr>
                    <td class="small text-nowrap">{{ thai_datetime($e->occurred_at) }}</td>
                    <td class="small">{{ $e->device?->name }}</td>
                    <td>{{ $e->student?->fullName() ?? $e->user?->name ?? '-' }}@if ($e->student?->classroom)<span class="small text-muted"> · {{ $e->student->classroom->name() }}</span>@endif @if ($e->user)<span class="badge bg-primary-subtle text-primary-emphasis">ครู/บุคลากร</span>@endif</td>
                    <td class="small font-monospace">{{ $e->code }}</td>
                    <td><span class="badge bg-{{ $color }}">{{ $label }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty"><i class="bi bi-list-ul"></i>ยังไม่มีการสแกนจากเครื่อง</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">"ไม่พบรหัสนี้" = เครื่องส่งรหัสที่ไม่ตรงกับรหัสนักเรียนหรือชื่อผู้ใช้ของครู ให้แก้รหัสบุคคลในเครื่องให้ตรง · เก็บประวัติ {{ \App\Models\GateEvent::KEEP_DAYS }} วัน</div>
</div>

<div class="modal fade" id="addDevice" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('gate.devices.store') }}" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">เพิ่มเครื่องสแกน</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
        <div class="modal-body row g-3">{!! $fields() !!}</div>
        <div class="modal-footer"><button class="btn btn-primary">เพิ่มเครื่อง</button></div>
    </form></div>
</div>
@foreach ($devices as $d)
    <div class="modal fade" id="editDevice{{ $d->id }}" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('gate.devices.update', $d) }}" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header"><h5 class="modal-title">แก้ไข {{ $d->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body row g-3">{!! $fields($d) !!}</div>
            <div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div>
        </form></div>
    </div>
@endforeach
@endsection
