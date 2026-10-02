@extends('layouts.app')
@section('title', 'เขียนใบเบิกวัสดุ')

@section('content')
@php
    $catalog = $supplies->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'unit' => $s->unit, 'stock' => $s->stock, 'price' => $s->unit_price, 'photo' => $s->photoUrl()])->keyBy('id');
    $oldItems = collect(old('items', []))->filter(fn ($i) => ! empty($i['supply_id']))->map(fn ($i) => ['id' => (int) $i['supply_id'], 'qty' => (int) ($i['quantity'] ?? 1)])->values();
@endphp
<div class="page-head">
    <div><h1>เขียนใบเบิกวัสดุ</h1><div class="sub">เลือกวัสดุจากรายการ แล้วกดส่งใบเบิกทางขวา · งานพัสดุได้รับแจ้งทาง LINE และแจ้งกลับเมื่อจ่ายของ</div></div>
    <div class="actions"><a href="{{ route('requisitions.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> ใบเบิกของฉัน</a></div>
</div>

@if ($supplies->isEmpty())
    <div class="card"><div class="empty"><i class="bi bi-boxes"></i>งานพัสดุยังไม่ได้เพิ่มรายการวัสดุในระบบ
        @if (auth()->user()->canManageFacilities())<div class="mt-2"><a href="{{ route('supplies.create') }}" class="btn btn-primary btn-sm">เพิ่มวัสดุ</a></div>@else<div class="small mt-1">ติดต่องานพัสดุให้เพิ่มรายการก่อน</div>@endif
    </div></div>
@else
<form method="POST" action="{{ route('requisitions.store') }}" id="reqForm">
    @csrf
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3"><div class="card-body d-flex flex-wrap gap-2 align-items-center">
                <div class="input-group" style="max-width:360px"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="search" id="find" class="form-control" placeholder="ค้นหาวัสดุ"></div>
                <div class="d-flex flex-wrap gap-1" id="cats">
                    <button type="button" class="btn btn-sm btn-dark" data-cat="">ทั้งหมด</button>
                    @foreach ($supplies->pluck('category')->filter()->unique()->sort() as $c)<button type="button" class="btn btn-sm btn-light border" data-cat="{{ $c }}">{{ $c }}</button>@endforeach
                </div>
            </div></div>
            <div class="pick-grid" id="catalog">
                @foreach ($supplies as $s)
                    <div class="pick-card {{ $s->stock <= 0 ? 'disabled' : '' }}" data-id="{{ $s->id }}" data-cat="{{ $s->category }}" data-name="{{ mb_strtolower($s->name.' '.$s->code) }}">
                        @if ($s->stock <= 0)<span class="badge text-bg-secondary pc-badge">หมด</span>@elseif($s->isLow())<span class="badge text-bg-warning pc-badge">เหลือน้อย</span>@endif
                        <div class="pc-img">@if($s->photoUrl())<img src="{{ $s->photoUrl() }}" alt="" loading="lazy">@else<i class="bi bi-box"></i>@endif</div>
                        <div class="pc-body">
                            <div class="pc-title">{{ $s->name }}</div>
                            <div class="pc-meta">{{ $s->category ?: '' }}{{ $s->code ? ' · '.$s->code : '' }}</div>
                            <div class="pc-meta">คงเหลือ <b class="text-body">{{ number_format($s->stock) }}</b> {{ $s->unit }}</div>
                            <button type="button" class="btn btn-sm btn-soft mt-auto" data-add="{{ $s->id }}" @disabled($s->stock <= 0)><i class="bi bi-plus-lg"></i> เพิ่มในใบเบิก</button>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="empty d-none" id="noMatch"><i class="bi bi-search"></i>ไม่พบวัสดุที่ค้นหา</div>
        </div>

        <div class="col-lg-4">
            <div class="card side-sticky">
                <div class="card-header d-flex align-items-center"><i class="bi bi-bag-check me-1"></i> ใบเบิกของฉัน <span class="badge text-bg-primary ms-auto" id="count">0</span></div>
                <div class="card-body">
                    <label class="form-label">กลุ่มสาระ / งาน</label>
                    <input name="department" value="{{ old('department') }}" class="form-control mb-2" list="depts" placeholder="เลือกหรือพิมพ์">
                    <datalist id="depts">@foreach ($departments as $d)<option>{{ $d }}</option>@endforeach</datalist>
                    <label class="form-label">ใช้สำหรับ</label>
                    <input name="purpose" value="{{ old('purpose') }}" class="form-control mb-3" placeholder="เช่น จัดทำข้อสอบกลางภาค">
                    <div id="cart" class="d-flex flex-column gap-2"></div>
                    <div class="text-center text-muted small py-4" id="cartEmpty"><i class="bi bi-cart fs-3 d-block mb-1"></i>ยังไม่ได้เลือกวัสดุ<br>กด "เพิ่มในใบเบิก" ที่รายการด้านซ้าย</div>
                    @error('items')<div class="text-danger small">{{ $message }}</div>@enderror
                    <div class="d-flex justify-content-between small text-muted mt-3 border-top pt-2" id="valueRow"><span>มูลค่าโดยประมาณ</span><span id="value">0.00 บาท</span></div>
                </div>
                <div class="card-footer bg-transparent"><button class="btn btn-primary btn-lg w-100" id="submit" disabled><i class="bi bi-send"></i> ส่งใบเบิก</button></div>
            </div>
        </div>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('reqForm');
    if (!form) return;
    const catalog = @json($catalog);
    const cart = new Map(@json($oldItems).map((i) => [i.id, i.qty]));
    const box = document.getElementById('cart');
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    const render = () => {
        box.innerHTML = '';
        let i = 0, value = 0;
        cart.forEach((qty, id) => {
            const s = catalog[id];
            if (!s) return;
            qty = Math.max(1, Math.min(qty, s.stock)); cart.set(id, qty);
            value += qty * Number(s.price || 0);
            box.insertAdjacentHTML('beforeend', `<div class="d-flex gap-2 align-items-center">
                <span class="media-thumb" style="width:42px;height:42px">${s.photo ? `<img src="${esc(s.photo)}" alt="">` : '<i class="bi bi-box"></i>'}</span>
                <div class="flex-grow-1 small lh-sm"><div class="fw-semibold">${esc(s.name)}</div><div class="text-muted">คงเหลือ ${s.stock} ${esc(s.unit)}</div></div>
                <span class="qty-stepper"><button type="button" data-step="-1" data-id="${id}">−</button><input type="number" min="1" max="${s.stock}" value="${qty}" data-qty="${id}"><button type="button" data-step="1" data-id="${id}">+</button></span>
                <button type="button" class="btn btn-sm btn-link text-danger p-0" data-del="${id}" title="นำออก"><i class="bi bi-x-lg"></i></button>
                <input type="hidden" name="items[${i}][supply_id]" value="${id}"><input type="hidden" name="items[${i}][quantity]" value="${qty}" data-hidden="${id}">
            </div>`);
            i++;
        });
        document.getElementById('count').textContent = cart.size;
        document.getElementById('cartEmpty').classList.toggle('d-none', cart.size > 0);
        document.getElementById('submit').disabled = cart.size === 0;
        document.getElementById('value').textContent = value.toLocaleString('th-TH', { minimumFractionDigits: 2 }) + ' บาท';
        document.querySelectorAll('.pick-card').forEach((c) => c.classList.toggle('selected', cart.has(Number(c.dataset.id))));
    };

    document.addEventListener('click', (e) => {
        const add = e.target.closest('[data-add]');
        if (add) { const id = Number(add.dataset.add); cart.set(id, (cart.get(id) || 0) + 1); render(); return; }
        const step = e.target.closest('[data-step]');
        if (step) { const id = Number(step.dataset.id); cart.set(id, (cart.get(id) || 1) + Number(step.dataset.step)); if (cart.get(id) < 1) cart.delete(id); render(); return; }
        const del = e.target.closest('[data-del]');
        if (del) { cart.delete(Number(del.dataset.del)); render(); return; }
        const cat = e.target.closest('[data-cat]');
        if (cat && cat.tagName === 'BUTTON') {
            document.querySelectorAll('#cats button').forEach((b) => b.className = 'btn btn-sm ' + (b === cat ? 'btn-dark' : 'btn-light border'));
            filter(cat.dataset.cat);
        }
    });
    box.addEventListener('change', (e) => { const id = Number(e.target.dataset.qty); if (id) { cart.set(id, Number(e.target.value) || 1); render(); } });

    let currentCat = '';
    const filter = (cat = currentCat) => {
        currentCat = cat;
        const q = document.getElementById('find').value.trim().toLowerCase();
        let shown = 0;
        document.querySelectorAll('.pick-card').forEach((c) => {
            const ok = (!cat || c.dataset.cat === cat) && (!q || c.dataset.name.includes(q));
            c.classList.toggle('d-none', !ok); shown += ok;
        });
        document.getElementById('noMatch').classList.toggle('d-none', shown > 0);
    };
    document.getElementById('find').addEventListener('input', () => filter());
    render();
})();
</script>
@endpush
