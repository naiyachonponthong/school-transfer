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
                    <input name="code" class="form-control" placeholder="รหัสนักเรียน เช่น 69001" aria-label="รหัสนักเรียนสำหรับทดสอบ" required {{ $d->is_active ? '' : 'disabled' }}>
                    <button class="btn btn-light border" {{ $d->is_active ? '' : 'disabled' }}><i class="bi bi-play-fill"></i> ทดสอบสแกน</button>
                </form>
                <div class="form-text">ทดสอบจะบันทึกการมาเรียนจริงของนักเรียนคนนั้น เหมือนเครื่องส่งมา</div>
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

<div class="card">
    <div class="card-header"><i class="bi bi-list-ul"></i> การสแกนล่าสุดจากทุกเครื่อง</div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>เวลา</th><th>เครื่อง</th><th>นักเรียน</th><th>รหัสที่ส่งมา</th><th>ผล</th></tr></thead>
            <tbody>
            @forelse ($events as $e)
                @php([$label, $color] = \App\Models\GateEvent::RESULTS[$e->result] ?? [$e->result, 'secondary'])
                <tr>
                    <td class="small text-nowrap">{{ thai_datetime($e->occurred_at) }}</td>
                    <td class="small">{{ $e->device?->name }}</td>
                    <td>{{ $e->student?->fullName() ?? '-' }}@if ($e->student?->classroom)<span class="small text-muted"> · {{ $e->student->classroom->name() }}</span>@endif</td>
                    <td class="small font-monospace">{{ $e->code }}</td>
                    <td><span class="badge bg-{{ $color }}">{{ $label }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty"><i class="bi bi-list-ul"></i>ยังไม่มีการสแกนจากเครื่อง</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">"ไม่พบนักเรียน" = เครื่องส่งรหัสที่ไม่ตรงกับรหัสนักเรียนในระบบ ให้แก้รหัสบุคคลในเครื่องให้ตรงกับรหัสนักเรียน · เก็บประวัติ {{ \App\Models\GateEvent::KEEP_DAYS }} วัน</div>
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
