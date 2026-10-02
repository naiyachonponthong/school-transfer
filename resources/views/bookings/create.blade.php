@extends('layouts.app')
@section('title', 'จองห้อง / รถ / อุปกรณ์')

@section('content')
@php
    $data = $resources->mapWithKeys(fn ($r) => [$r->id => [
        'name' => $r->name, 'type' => $r->type, 'typeLabel' => $r->typeLabel(), 'icon' => $r->icon(), 'capacity' => $r->capacity, 'location' => $r->location,
        'amenities' => $r->amenityList(), 'plate' => $r->plate_no, 'contact' => $r->contact, 'rules' => $r->rules, 'photo' => $r->photoUrl(),
        'approval' => $r->requires_approval, 'description' => $r->description,
    ]]);
    $chosen = (int) old('resource_id', $selected);
@endphp
<div class="page-head">
    <div><h1>จองห้อง / รถ / อุปกรณ์</h1><div class="sub">1. เลือกรายการ · 2. ใส่วันเวลา (ดูช่วงที่ถูกจองแล้วด้านขวา) · ระบบกันจองซ้อนให้อัตโนมัติ</div></div>
    <div class="actions"><a href="{{ route('bookings.index') }}" class="btn btn-light border"><i class="bi bi-calendar2-week"></i> ตารางจอง</a></div>
</div>

@if ($resources->isEmpty())
    <div class="card"><div class="empty"><i class="bi bi-door-closed"></i>ยังไม่มีห้อง/รถ/อุปกรณ์ที่เปิดให้จอง</div></div>
@else
<form method="POST" action="{{ route('bookings.store') }}" id="bookForm">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="d-flex align-items-center gap-2 mb-2">
                <h2 class="h6 fw-bold mb-0">1. เลือกห้อง / รถ / อุปกรณ์</h2>
                <div class="ms-auto d-flex gap-1" id="typeFilter">
                    <button type="button" class="btn btn-sm btn-dark" data-type="">ทั้งหมด</button>
                    @foreach ($resources->pluck('type')->unique() as $t)<button type="button" class="btn btn-sm btn-light border" data-type="{{ $t }}">{{ \App\Models\BookableResource::TYPES[$t][0] ?? $t }}</button>@endforeach
                </div>
            </div>
            @error('resource_id')<div class="alert alert-danger py-2 small">{{ $message }}</div>@enderror
            <div class="pick-grid mb-4">
                @foreach ($resources as $r)
                    <div class="pick-card" data-rtype="{{ $r->type }}">
                        <input type="radio" name="resource_id" value="{{ $r->id }}" id="res{{ $r->id }}" class="d-none" @checked($chosen === $r->id) required>
                        <label for="res{{ $r->id }}" class="stretched"></label>
                        @if ($r->requires_approval)<span class="badge text-bg-warning pc-badge">ต้องอนุมัติ</span>@endif
                        <div class="pc-img">@if($r->photoUrl())<img src="{{ $r->photoUrl() }}" alt="" loading="lazy">@else<i class="bi {{ $r->icon() }}"></i>@endif</div>
                        <div class="pc-body">
                            <div class="pc-title">{{ $r->name }}</div>
                            <div class="pc-meta">{{ $r->typeLabel() }}{{ $r->capacity ? ' · '.$r->capacity.($r->type === 'vehicle' ? ' ที่นั่ง' : ' คน') : '' }}{{ $r->location ? ' · '.$r->location : '' }}</div>
                            @if ($r->amenityList())<div class="tag-list">@foreach (array_slice($r->amenityList(), 0, 4) as $a)<span>{{ $a }}</span>@endforeach</div>@endif
                        </div>
                    </div>
                @endforeach
            </div>

            <h2 class="h6 fw-bold mb-2">2. รายละเอียดการจอง</h2>
            <div class="card"><div class="card-body row g-3">
                <div class="col-12"><label class="form-label">วัตถุประสงค์ <span class="text-danger">*</span></label><input name="title" value="{{ old('title') }}" class="form-control form-control-lg" required placeholder="เช่น ประชุมกลุ่มสาระ / พานักเรียนแข่งขัน"></div>
                <div class="col-md-4"><label class="form-label">วันที่</label><input type="date" name="date" id="date" value="{{ old('date', $date) }}" class="form-control" required min="{{ today()->toDateString() }}"></div>
                <div class="col-6 col-md-4"><label class="form-label">ตั้งแต่</label><input type="time" name="start_time" id="from" value="{{ old('start_time', '09:00') }}" class="form-control" required step="900">@error('start_time')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-6 col-md-4"><label class="form-label">ถึง</label><input type="time" name="end_time" id="to" value="{{ old('end_time', '12:00') }}" class="form-control" required step="900">@error('end_time')<div class="text-danger small">{{ $message }}</div>@enderror</div>
                <div class="col-md-4" data-vehicle hidden><label class="form-label">ถึงวันที่ (ค้างคืน)</label><input type="date" name="end_date" value="{{ old('end_date') }}" class="form-control"></div>
                <div class="col-md-8" data-vehicle hidden><label class="form-label">ปลายทาง</label><input name="destination" value="{{ old('destination') }}" class="form-control" placeholder="เช่น มหาวิทยาลัยขอนแก่น"></div>
                <div class="col-md-4"><label class="form-label">จำนวนคน</label><input type="number" min="1" name="attendees" value="{{ old('attendees') }}" class="form-control" id="attendees"><div class="form-text text-warning d-none" id="capWarn"></div></div>
                <div class="col-md-4"><label class="form-label">เบอร์ติดต่อ</label><input name="contact_phone" value="{{ old('contact_phone', auth()->user()->phone) }}" class="form-control" inputmode="tel"></div>
                <div class="col-md-4"><label class="form-label">หมายเหตุ</label><input name="note" value="{{ old('note') }}" class="form-control" placeholder="เช่น ขอไมค์ 2 ตัว จัดโต๊ะแบบ U"></div>
            </div></div>
        </div>

        <div class="col-lg-4">
            <div class="card side-sticky">
                <div id="panelEmpty" class="empty"><i class="bi bi-hand-index"></i>เลือกห้อง/รถ/อุปกรณ์ทางซ้าย</div>
                <div id="panel" class="d-none">
                    <div class="panel-photo" id="pPhoto"></div>
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-2"><div class="fw-bold fs-5 flex-grow-1" id="pName"></div><span class="badge text-bg-warning d-none" id="pApproval">ต้องอนุมัติ</span></div>
                        <div class="small text-muted mb-2" id="pMeta"></div>
                        <div class="tag-list mb-2" id="pAmenities"></div>
                        <div class="small" id="pDesc"></div>
                        <div class="small alert alert-light border py-2 mt-2 mb-0 d-none" id="pRules"></div>
                        <hr>
                        <div class="fw-semibold small mb-1">ช่วงเวลาที่ถูกจองแล้ว <span class="text-muted fw-normal" id="pDate"></span></div>
                        <div class="day-slots" id="slots"></div>
                        <div class="slot-scale"><span>06:00</span><span>09:00</span><span>12:00</span><span>15:00</span><span>18:00</span><span>21:00</span></div>
                        <div class="small mt-2" id="slotList"></div>
                        <div class="small fw-semibold mt-2" id="clash"></div>
                    </div>
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-primary btn-lg w-100"><i class="bi bi-calendar2-check"></i> <span id="submitLabel">จอง</span></button></div>
            </div>
        </div>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('bookForm');
    if (!form) return;
    const res = @json($data);
    const dayUrl = @json(route('bookings.day'));
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const $ = (id) => document.getElementById(id);
    const START = 6 * 60, END = 21 * 60;
    const mins = (t) => { const [h, m] = String(t).split(':').map(Number); return h * 60 + (m || 0); };
    const pct = (m) => Math.max(0, Math.min(100, (m - START) / (END - START) * 100));
    let booked = [];

    const current = () => res[form.resource_id.value];

    const drawSlots = () => {
        const r = current();
        if (!r) return;
        const bar = $('slots');
        bar.innerHTML = booked.map((b) => `<div class="ds ${b.status === 'pending' ? 'pending' : ''} ${b.mine ? 'mine' : ''}" style="left:${pct(mins(b.from))}%;width:${pct(mins(b.to)) - pct(mins(b.from))}%" title="${esc(b.from)}–${esc(b.to)} ${esc(b.title)}">${esc(b.from)}</div>`).join('');
        const f = mins($('from').value), t = mins($('to').value);
        if (f && t > f) bar.insertAdjacentHTML('beforeend', `<div class="ds pick" style="left:${pct(f)}%;width:${pct(t) - pct(f)}%">เลือก</div>`);
        $('slotList').innerHTML = booked.length
            ? booked.map((b) => `<div><span class="badge text-bg-${b.status === 'pending' ? 'warning' : 'danger'}">${esc(b.from)}–${esc(b.to)}</span> ${esc(b.title)} <span class="text-muted">· ${esc(b.by || '')}</span></div>`).join('')
            : '<span class="text-success"><i class="bi bi-check-circle"></i> ว่างทั้งวัน</span>';
        const clash = booked.find((b) => mins(b.from) < t && mins(b.to) > f);
        $('clash').innerHTML = clash ? `<span class="text-danger"><i class="bi bi-exclamation-triangle-fill"></i> ช่วงที่เลือกชนกับ ${esc(clash.from)}–${esc(clash.to)} (${esc(clash.title)})</span>` : (f && t > f ? '<span class="text-success"><i class="bi bi-check-circle-fill"></i> ช่วงที่เลือกว่าง</span>' : '');
    };

    const load = async () => {
        const r = current();
        if (!r || !$('date').value) return;
        $('pDate').textContent = new Date($('date').value).toLocaleDateString('th-TH', { day: 'numeric', month: 'short', year: 'numeric' });
        try {
            const res2 = await fetch(`${dayUrl}?resource=${form.resource_id.value}&date=${$('date').value}`, { headers: { Accept: 'application/json' } });
            booked = res2.ok ? await res2.json() : [];
        } catch (e) { booked = []; }
        drawSlots();
    };

    const show = () => {
        const r = current();
        $('panel').classList.toggle('d-none', !r); $('panelEmpty').classList.toggle('d-none', !!r);
        if (!r) return;
        $('pPhoto').innerHTML = r.photo ? `<img src="${esc(r.photo)}" alt="">` : `<i class="bi ${esc(r.icon)}"></i>`;
        $('pName').textContent = r.name;
        $('pApproval').classList.toggle('d-none', !r.approval);
        $('pMeta').innerHTML = [r.typeLabel, r.capacity ? `${r.capacity} ${r.type === 'vehicle' ? 'ที่นั่ง' : 'คน'}` : '', r.location ? `<i class="bi bi-geo-alt"></i> ${esc(r.location)}` : '', r.plate ? `ทะเบียน ${esc(r.plate)}` : '', r.contact ? `<i class="bi bi-person"></i> ${esc(r.contact)}` : ''].filter(Boolean).join(' · ');
        $('pAmenities').innerHTML = r.amenities.map((a) => `<span>${esc(a)}</span>`).join('');
        $('pDesc').textContent = r.description || '';
        $('pRules').classList.toggle('d-none', !r.rules); $('pRules').innerHTML = r.rules ? `<b>ข้อปฏิบัติ:</b> ${esc(r.rules)}` : '';
        document.querySelectorAll('[data-vehicle]').forEach((el) => { el.hidden = r.type !== 'vehicle'; });
        $('submitLabel').textContent = r.approval ? 'ส่งคำขอจอง (รออนุมัติ)' : 'จอง';
        checkCap(); load();
    };
    const checkCap = () => {
        const r = current(), n = Number($('attendees').value);
        const over = r && r.capacity && n > r.capacity;
        $('capWarn').classList.toggle('d-none', !over);
        if (over) $('capWarn').textContent = `เกินความจุ (${r.capacity})`;
    };

    form.addEventListener('change', (e) => {
        if (e.target.name === 'resource_id') show();
        if (e.target.id === 'date') load();
        if (['from', 'to'].includes(e.target.id)) drawSlots();
        if (e.target.id === 'attendees') checkCap();
    });
    $('attendees').addEventListener('input', checkCap);
    document.getElementById('typeFilter').addEventListener('click', (e) => {
        const b = e.target.closest('[data-type]'); if (!b) return;
        document.querySelectorAll('#typeFilter button').forEach((x) => x.className = 'btn btn-sm ' + (x === b ? 'btn-dark' : 'btn-light border'));
        document.querySelectorAll('[data-rtype]').forEach((c) => c.classList.toggle('d-none', !!b.dataset.type && c.dataset.rtype !== b.dataset.type));
    });
    show();
})();
</script>
@endpush
