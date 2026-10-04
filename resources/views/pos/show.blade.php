@extends('layouts.app')
@section('title', 'ขาย · '.$shop->name)

@push('head')
{{-- โหมดเต็มจอ: ใส่ class ก่อนวาดหน้า เพื่อไม่ให้เมนูกะพริบขึ้นมาก่อน --}}
<script>try { if (localStorage.getItem('posKiosk') === '1') document.documentElement.classList.add('pos-kiosk'); } catch (e) {}</script>
@endpush

@section('content')
<div class="page-head">
    <div><h1>{{ $shop->name }}</h1><div class="sub">วันนี้ขายแล้ว <b id="todayCount">{{ $todayCount }}</b> รายการ · <b id="todayTotal">{{ baht($todayTotal) }}</b> บาท</div></div>
    <div class="actions">
        <a href="{{ route('pos.display', $shop) }}" target="posDisplay" class="btn btn-light border" title="เปิดบนจอที่หันหาลูกค้า"><i class="bi bi-display"></i> หน้าจอลูกค้า</a>
        <button type="button" class="btn btn-light border" id="camBtn"><i class="bi bi-camera"></i> สแกนด้วยกล้อง</button>
        <button type="button" class="btn btn-light border" id="kioskBtn" aria-pressed="false"><i class="bi bi-arrows-fullscreen"></i> <span>เต็มจอ</span></button>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-grid"></i> สินค้า</div>
            <div class="card-body">
                @forelse ($products->groupBy(fn ($p) => $p->category ?: 'ทั่วไป') as $category => $list)
                    <div class="small text-muted fw-semibold mb-1">{{ $category }}</div>
                    <div class="row g-2 mb-3">
                        @foreach ($list as $p)
                            <div class="col-6 col-md-4"><button type="button" class="btn btn-light border w-100 h-100 text-start p-2 pos-item" data-id="{{ $p->id }}" data-name="{{ $p->name }}" data-price="{{ (float) $p->price }}"
                                data-barcode="{{ $p->barcode }}" data-stock="{{ $p->stock }}" title="{{ $p->description }}" @disabled($p->soldOut())>
                                @if ($p->imageUrl())<img src="{{ $p->imageUrl() }}" alt="" class="rounded-2 mb-1 w-100" style="height:84px;object-fit:cover" loading="lazy">@endif
                                <div class="fw-semibold">{{ $p->name }}</div>
                                <div class="d-flex align-items-center gap-1"><span class="text-primary">{{ baht($p->price) }} ฿{{ $p->unit ? '/'.$p->unit : '' }}</span>
                                    @if ($p->stock !== null)<span class="ms-auto badge {{ $p->soldOut() ? 'bg-danger' : 'bg-light text-body border' }}">{{ $p->soldOut() ? 'หมด' : 'เหลือ '.$p->stock }}</span>@endif
                                </div>
                            </button></div>
                        @endforeach
                    </div>
                @empty
                    <div class="small text-muted mb-3">ร้านนี้ยังไม่มีรายการสินค้า กดจำนวนเงินเองด้านล่างได้</div>
                @endforelse
                <div class="input-group">
                    <span class="input-group-text">จำนวนเงินอื่น</span>
                    <input type="number" id="customAmount" class="form-control" min="1" step="0.25" inputmode="decimal" placeholder="0.00" aria-label="จำนวนเงินอื่น">
                    <button type="button" class="btn btn-light border" id="customAdd"><i class="bi bi-plus-lg"></i> เพิ่ม</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-receipt"></i> ขายล่าสุดวันนี้</div>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <tbody>
                @forelse ($sales as $s)
                    <tr class="{{ $s->voided_at ? 'text-muted text-decoration-line-through' : '' }}">
                        <td class="small text-nowrap">{{ $s->created_at->format('H:i') }}</td>
                        <td>{{ $s->customerName() }}@if ($s->payment === 'qr') <span class="badge bg-info-subtle text-info-emphasis text-decoration-none">QR</span>@endif<div class="small text-muted">{{ $s->itemsLabel() }}</div></td>
                        <td class="text-end fw-semibold text-nowrap">{{ baht($s->total) }}</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('pos.receipt', $s) }}" target="receipt" class="btn btn-sm btn-light border" title="ใบเสร็จ" aria-label="พิมพ์ใบเสร็จ"><i class="bi bi-printer"></i></a>
                            @if (! $s->voided_at)
                                <button class="btn btn-sm btn-light border text-danger" data-bs-toggle="modal" data-bs-target="#void{{ $s->id }}">ยกเลิก</button>
                            @else
                                <span class="badge bg-secondary text-decoration-none">ยกเลิกแล้ว</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td><div class="empty py-4"><i class="bi bi-receipt"></i>วันนี้ยังไม่มีการขาย</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card pos-cart">
            <div class="card-header"><i class="bi bi-basket"></i> รายการที่จะขาย</div>
            <div class="card-body">
                <div id="cart" class="mb-2"><div class="small text-muted">กดสินค้าทางซ้าย หรือใส่จำนวนเงิน</div></div>
                <div class="d-flex align-items-center border-top pt-2 mb-3"><span class="fw-semibold">รวม</span><span class="ms-auto fs-3 fw-bold" id="total">0.00</span><span class="ms-1">บาท</span></div>

                <label class="form-label small" for="scan">สแกนหรือแตะบัตรนักเรียน/ครู · สแกนบาร์โค้ดสินค้า · หรือพิมพ์รหัสแล้วกด Enter</label>
                <input id="scan" class="form-control form-control-lg mb-2" autocomplete="off" placeholder="สแกนบัตร…" autofocus>
                <div id="cam" class="mb-2 d-none" style="max-width:320px"></div>

                <div id="who" class="d-none border rounded-3 p-2 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div id="whoPhoto"></div>
                        <div class="min-w-0">
                            <div class="fw-semibold" id="whoName"></div>
                            <div class="small text-muted" id="whoRoom"></div>
                            <div>คงเหลือ <b class="fs-5" id="whoBalance"></b> บาท</div>
                            <div class="small text-danger" id="whoWarn"></div>
                        </div>
                        <button type="button" class="btn-close ms-auto" id="whoClear" aria-label="เปลี่ยนลูกค้า"></button>
                    </div>
                </div>
                <div id="msg" class="small mb-2" role="status" aria-live="polite"></div>
                <button type="button" class="btn btn-primary btn-lg w-100" id="pay" disabled><i class="bi bi-check2-circle"></i> ตัดเงินจากกระเป๋า</button>
                <button type="button" class="btn btn-light border w-100 mt-2" id="qrBtn" disabled><i class="bi bi-qr-code"></i> ให้ลูกค้าสแกนจ่าย</button>
                <div class="d-flex align-items-center gap-3 mt-2 small">
                    <label class="form-check mb-0"><input type="checkbox" class="form-check-input" id="autoPrint"> พิมพ์ใบเสร็จทุกครั้ง</label>
                    <a href="#" target="receipt" class="ms-auto d-none" id="receiptLink"><i class="bi bi-printer"></i> พิมพ์ใบเสร็จล่าสุด</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="qrModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">ให้ลูกค้าสแกนจ่าย <span id="qrAmount"></span> บาท</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
        <div class="modal-body text-center">
            <div class="mx-auto bg-white p-2 rounded-3 border" style="width:260px" id="qrBox"></div>
            <div class="small mt-2">ให้ลูกค้าเปิด<b>กระเป๋าเงิน</b>ในระบบ กด <b>สแกนจ่าย</b> แล้วสแกน QR นี้</div>
            <div class="small text-muted mt-2" role="status" aria-live="polite"><span class="spinner-border spinner-border-sm"></span> รอลูกค้ายืนยันการจ่าย หน้านี้จะปิดเองเมื่อจ่ายแล้ว</div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">ยกเลิก</button></div>
    </div></div>
</div>

@foreach ($sales->whereNull('voided_at') as $s)
    <div class="modal fade" id="void{{ $s->id }}" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('pos.void', $s) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">ยกเลิกรายการ {{ baht($s->total) }} บาท</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body">
                <div class="small text-muted mb-2">{{ $s->customerName() }} · {{ $s->itemsLabel() }} · {{ $s->wallet_id ? 'เงินจะคืนเข้ากระเป๋า' : 'จ่ายด้วย QR ต้องคืนเงินให้ลูกค้าเอง' }}</div>
                <label class="form-label">เหตุผล</label>
                <input name="reason" class="form-control" maxlength="200" required placeholder="เช่น กดผิดรายการ">
            </div>
            <div class="modal-footer"><button class="btn btn-danger">ยกเลิกรายการนี้</button></div>
        </form></div>
    </div>
@endforeach
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
<script>
(function () {
    const urls = {
        lookup: @json(route('pos.lookup', $shop)), charge: @json(route('pos.charge', $shop)), receipt: @json(route('pos.receipt', '__ID__')),
        payRequest: @json(route('pos.pay.request', $shop)), payStatus: @json(route('pos.pay.status', [$shop, '__TOKEN__'])), display: @json(route('pos.display.push', $shop)),
    };
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = (n) => Number(n).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const post = (url, body) => fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) });
    let cart = [], student = null, busy = false, key = newKey();

    function newKey() { return (crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random().toString(36).slice(2)).replace(/-/g, ''); }
    const total = () => Math.round(cart.reduce((s, i) => s + i.price * i.qty, 0) * 100) / 100;
    const lines = () => cart.map((i) => ({ product_id: i.product_id, price: i.product_id ? null : i.price, qty: i.qty }));

    const beep = (ok) => {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const o = ctx.createOscillator(); const g = ctx.createGain();
            o.frequency.value = ok ? 1046 : 220; o.connect(g); g.connect(ctx.destination);
            g.gain.setValueAtTime(.2, ctx.currentTime); o.start(); o.stop(ctx.currentTime + (ok ? .12 : .35));
        } catch (e) {}
    };

    /* ---------- หน้าจอลูกค้า: ส่งสิ่งที่ลูกค้าควรเห็นทุกครั้งที่มีการเปลี่ยนแปลง ---------- */
    let pushTimer = null, hold = null;
    function show(state) { post(urls.display, state).catch(() => {}); }
    function pushCart() {
        if (hold) return; // กำลังแสดงผลการชำระหรือ QR ค้างไว้
        clearTimeout(pushTimer);
        pushTimer = setTimeout(() => {
            const t = total();
            show(!cart.length && !student ? { status: 'idle' } : {
                status: 'cart', items: cart.map((i) => ({ name: i.name, price: i.price, qty: i.qty })), total: t, message: $('whoWarn').textContent || null,
                customer: student ? { name: student.name, sub: student.classroom, photo: student.photo, initials: student.initials, balance: student.balance } : null,
                balance_after: student ? Math.round((student.balance - t) * 100) / 100 : null,
            });
        }, 150);
    }
    function showResult(state, ms) {
        clearTimeout(pushTimer); clearTimeout(hold);
        show(state);
        hold = setTimeout(() => { hold = null; pushCart(); }, ms);
    }

    function render() {
        $('cart').innerHTML = cart.length ? cart.map((i, n) => `<div class="d-flex align-items-center gap-2 py-1">
            <div class="flex-grow-1">${esc(i.name)}<div class="small text-muted">${money(i.price)} × ${i.qty}</div></div>
            <div class="btn-group btn-group-sm"><button type="button" class="btn btn-light border" data-dec="${n}" aria-label="ลด">−</button><button type="button" class="btn btn-light border" data-inc="${n}" aria-label="เพิ่ม">+</button></div>
            <div class="fw-semibold text-end" style="min-width:70px">${money(i.price * i.qty)}</div></div>`).join('')
            : '<div class="small text-muted">กดสินค้าทางซ้าย หรือใส่จำนวนเงิน</div>';
        const t = total();
        $('total').textContent = money(t);
        let warn = '';
        if (student) {
            if (student.frozen) warn = 'กระเป๋านี้ถูกระงับการใช้จ่าย';
            else if (t > student.balance) warn = 'ยอดเงินไม่พอ';
            else if (student.limit_left !== null && t > student.limit_left) warn = 'เกินวงเงินต่อวัน (ใช้ได้อีก ' + money(student.limit_left) + ' บาท)';
        }
        $('whoWarn').textContent = warn;
        $('pay').disabled = busy || !student || t <= 0 || warn !== '';
        $('qrBtn').disabled = busy || t <= 0;
        pushCart();
    }

    function add(item) {
        const found = item.product_id ? cart.find((i) => i.product_id === item.product_id) : null;
        // สินค้าที่นับสต็อก: ใส่ตะกร้าได้ไม่เกินที่เหลือ
        if (item.stock !== null && (found ? found.qty : 0) >= item.stock) { beep(false); say(item.name + ' เหลือ ' + item.stock + ' ไม่พอ', false); return; }
        if (found) found.qty++; else cart.push({ ...item, qty: 1 });
        render();
    }

    const tiles = Array.from(document.querySelectorAll('.pos-item'));
    const tileItem = (b) => ({ product_id: Number(b.dataset.id), name: b.dataset.name, price: Number(b.dataset.price), stock: b.dataset.stock === '' ? null : Number(b.dataset.stock) });
    tiles.forEach((b) => b.addEventListener('click', () => add(tileItem(b))));

    // พิมพ์ใบเสร็จอัตโนมัติ (จำค่าที่เลือกไว้ในเครื่องนี้)
    try { $('autoPrint').checked = localStorage.getItem('posAutoPrint') === '1'; } catch (e) {}
    $('autoPrint').addEventListener('change', () => { try { localStorage.setItem('posAutoPrint', $('autoPrint').checked ? '1' : '0'); } catch (e) {} });

    $('customAdd').addEventListener('click', () => {
        const v = Math.round(Number($('customAmount').value) * 100) / 100;
        if (v > 0) { add({ product_id: null, name: 'รายการอื่น', price: v, stock: null }); $('customAmount').value = ''; }
        $('scan').focus();
    });
    $('customAmount').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); $('customAdd').click(); } });
    $('cart').addEventListener('click', (e) => {
        const inc = e.target.dataset.inc, dec = e.target.dataset.dec;
        if (inc !== undefined) { if (cart[inc].stock !== null && cart[inc].qty >= cart[inc].stock) { beep(false); say(cart[inc].name + ' เหลือ ' + cart[inc].stock + ' ไม่พอ', false); return; } cart[inc].qty++; }
        if (dec !== undefined && --cart[dec].qty <= 0) cart.splice(dec, 1);
        if (inc !== undefined || dec !== undefined) render();
    });

    function say(text, ok) { $('msg').className = 'small mb-2 ' + (ok ? 'text-success' : 'text-danger'); $('msg').textContent = text; }

    async function lookup(code) {
        code = code.trim();
        if (!code || busy) return;
        // บาร์โค้ดของสินค้าในร้านนี้ = เพิ่มสินค้าลงรายการ
        const tile = tiles.find((b) => b.dataset.barcode && b.dataset.barcode === code);
        if (tile) { $('scan').value = ''; if (tile.disabled) { beep(false); say(tile.dataset.name + ' หมด', false); } else { beep(true); add(tileItem(tile)); } return; }
        busy = true; say('', true);
        try {
            const data = await (await post(urls.lookup, { code })).json();
            if (!data.ok) { beep(false); say(data.message || 'ไม่พบข้อมูล', false); student = null; $('who').classList.add('d-none'); }
            else {
                beep(true); student = data.student;
                $('whoPhoto').innerHTML = student.photo ? `<img src="${esc(student.photo)}" alt="" class="rounded-3" style="width:72px;height:72px;object-fit:cover">`
                    : `<div class="rounded-3 d-flex align-items-center justify-content-center fw-bold tint-primary" style="width:72px;height:72px;font-size:1.6rem">${esc(student.initials)}</div>`;
                $('whoName').textContent = student.name; $('whoRoom').textContent = student.classroom || '';
                $('whoBalance').textContent = money(student.balance);
                $('who').classList.remove('d-none');
            }
        } catch (e) { beep(false); say('เชื่อมต่อไม่ได้ ลองใหม่อีกครั้ง', false); }
        busy = false; $('scan').value = ''; render();
    }
    $('scan').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); lookup($('scan').value); } });
    $('whoClear').addEventListener('click', () => { student = null; $('who').classList.add('d-none'); render(); $('scan').focus(); });

    /** หลังขายสำเร็จ: ใบเสร็จ ยอดขายวันนี้ สต็อกบนหน้าจอ แล้วล้างรายการ */
    function sold(data) {
        const url = urls.receipt.replace('__ID__', data.sale_id);
        $('receiptLink').href = url; $('receiptLink').classList.remove('d-none');
        if ($('autoPrint').checked) window.open(url, 'receipt', 'width=340,height=640');
        $('todayCount').textContent = Number($('todayCount').textContent) + 1;
        $('todayTotal').textContent = money(Number($('todayTotal').textContent.replace(/,/g, '')) + data.total);
        cart.forEach((i) => { const b = tiles.find((t) => Number(t.dataset.id) === i.product_id); if (b && b.dataset.stock !== '') { const left = Number(b.dataset.stock) - i.qty; b.dataset.stock = left; const badge = b.querySelector('.badge'); if (badge) { badge.textContent = left > 0 ? 'เหลือ ' + left : 'หมด'; if (left <= 0) { badge.className = 'ms-auto badge bg-danger'; b.disabled = true; } } } });
        cart = []; student = null; key = newKey(); $('who').classList.add('d-none');
    }

    $('pay').addEventListener('click', async () => {
        if (busy || !student) return;
        busy = true; render(); say('กำลังตัดเงิน…', true);
        const who = student, body = { client_key: key, items: lines() };
        body[who.type === 'staff' ? 'staff_id' : 'student_id'] = who.id;
        try {
            const data = await (await post(urls.charge, body)).json();
            if (data.ok) {
                beep(true);
                say(`ตัดเงิน ${money(data.total)} บาท จาก ${who.name} แล้ว · คงเหลือ ${money(data.balance)} บาท`, true);
                sold(data);
                showResult({ status: 'paid', total: data.total, balance_after: data.balance, customer: { name: who.name, sub: who.classroom, photo: who.photo, initials: who.initials } }, 5000);
            } else { beep(false); say(data.message || 'ตัดเงินไม่สำเร็จ', false); showResult({ status: 'error', message: data.message || 'ตัดเงินไม่สำเร็จ' }, 4000); }
        } catch (e) {
            // เน็ตสะดุด: ไม่เปลี่ยนรหัสรายการ กดซ้ำได้โดยไม่ตัดเงินสองครั้ง
            beep(false); say('เชื่อมต่อไม่ได้ กด "ตัดเงิน" อีกครั้งได้ ระบบจะไม่ตัดซ้ำ', false);
        }
        busy = false; render(); $('scan').focus();
    });

    /* ---------- ลูกค้าสแกนจ่ายเองจากกระเป๋าเงินในระบบ (ไม่ต้องยื่นบัตร) ---------- */
    {
        // Bootstrap โหลดแบบ defer จึงยังไม่มีตอนสคริปต์นี้เริ่มทำงาน สร้างกล่องเมื่อจะใช้
        const modal = () => bootstrap.Modal.getOrCreateInstance($('qrModal'));
        let qrOpen = false, poll = null;
        const stopPoll = () => { clearInterval(poll); poll = null; };

        $('qrBtn').addEventListener('click', async () => {
            const amount = total();
            if (busy || amount <= 0) return;
            $('qrAmount').textContent = money(amount); $('qrBox').innerHTML = '';
            qrOpen = true; clearTimeout(pushTimer); clearTimeout(hold); hold = setTimeout(() => {}, 0);
            modal().show();
            try {
                // QR ของรายการนี้ ใช้ได้ครั้งเดียว แล้วคอยถามว่าลูกค้ายืนยันจ่ายหรือยัง
                const data = await (await post(urls.payRequest, { client_key: key, items: lines() })).json();
                if (!data.ok) throw new Error(data.message || 'สร้าง QR ไม่ได้');
                const qr = qrcode(0, 'M'); qr.addData(data.url); qr.make();
                $('qrBox').innerHTML = qr.createSvgTag({ cellSize: 5, margin: 1, scalable: true });
                show({ status: 'qr', items: cart.map((i) => ({ name: i.name, price: i.price, qty: i.qty })), total: amount, qr: data.url });
                stopPoll();
                poll = setInterval(async () => {
                    try {
                        const s = await (await fetch(urls.payStatus.replace('__TOKEN__', data.token), { headers: { Accept: 'application/json' } })).json();
                        if (s.status !== 'paid') return;
                        stopPoll(); beep(true);
                        say(`${s.customer.name} สแกนจ่าย ${money(s.total)} บาท แล้ว · คงเหลือ ${money(s.balance)} บาท`, true);
                        sold({ sale_id: s.sale_id, total: s.total }); qrOpen = false; modal().hide();
                        showResult({ status: 'paid', total: s.total, balance_after: s.balance, customer: s.customer }, 5000);
                        render();
                    } catch (e) {}
                }, 1500);
            } catch (e) { beep(false); say(e.message || 'เชื่อมต่อไม่ได้ ลองใหม่อีกครั้ง', false); }
        });
        $('qrModal').addEventListener('hidden.bs.modal', () => { stopPoll(); if (qrOpen) { qrOpen = false; clearTimeout(hold); hold = null; pushCart(); } $('scan').focus(); });
    }

    // กล้อง (ไม่บังคับ): สำหรับแท็บเล็ต/มือถือที่ไม่มีเครื่องอ่านบาร์โค้ด
    let cam = null;
    $('camBtn').addEventListener('click', async () => {
        if (cam) { await cam.stop().catch(() => {}); cam = null; $('cam').classList.add('d-none'); return; }
        $('cam').classList.remove('d-none');
        cam = new Html5Qrcode('cam');
        let last = '', lastAt = 0;
        cam.start({ facingMode: 'environment' }, { fps: 10, qrbox: 220 }, (text) => {
            if (text === last && Date.now() - lastAt < 3000) return;
            last = text; lastAt = Date.now(); lookup(text);
        }).catch(() => { say('เปิดกล้องไม่ได้ (ต้องเป็น https และอนุญาตให้ใช้กล้อง)', false); $('cam').classList.add('d-none'); cam = null; });
    });

    // โหมดเต็มจอสำหรับเครื่อง POS/แท็บเล็ต: ซ่อนเมนูของระบบ เหลือแต่หน้าจอขาย (จำค่าไว้ในเครื่องนี้)
    const kiosk = $('kioskBtn');
    const paintKiosk = () => {
        const on = document.documentElement.classList.contains('pos-kiosk');
        kiosk.setAttribute('aria-pressed', on ? 'true' : 'false');
        kiosk.querySelector('span').textContent = on ? 'ออกจากเต็มจอ' : 'เต็มจอ';
        kiosk.querySelector('i').className = on ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
    };
    kiosk.addEventListener('click', () => {
        const on = document.documentElement.classList.toggle('pos-kiosk');
        try { localStorage.setItem('posKiosk', on ? '1' : '0'); } catch (e) {}
        // ขอให้เบราว์เซอร์ซ่อนแถบที่อยู่ด้วย (ต้องมาจากการกดของผู้ใช้ และบางเครื่องไม่รองรับ)
        try {
            if (on && document.documentElement.requestFullscreen) document.documentElement.requestFullscreen().catch(() => {});
            if (!on && document.fullscreenElement) document.exitFullscreen().catch(() => {});
        } catch (e) {}
        paintKiosk(); $('scan').focus();
    });
    paintKiosk();

    render();
})();
</script>
@endpush
