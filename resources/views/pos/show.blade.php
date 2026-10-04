@extends('layouts.app')
@section('title', 'ขาย · '.$shop->name)

@section('content')
<div class="page-head">
    <div><h1>{{ $shop->name }}</h1><div class="sub">วันนี้ขายแล้ว <b id="todayCount">{{ $todayCount }}</b> รายการ · <b id="todayTotal">{{ baht($todayTotal) }}</b> บาท</div></div>
    <div class="actions">
        <button type="button" class="btn btn-light border" id="camBtn"><i class="bi bi-camera"></i> สแกนด้วยกล้อง</button>
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
                            <div class="col-6 col-md-4"><button type="button" class="btn btn-light border w-100 h-100 text-start pos-item" data-id="{{ $p->id }}" data-name="{{ $p->name }}" data-price="{{ (float) $p->price }}">
                                <div class="fw-semibold">{{ $p->name }}</div><div class="text-primary">{{ baht($p->price) }} ฿</div>
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
                        <td>{{ $s->wallet->student->fullName() }}<div class="small text-muted">{{ $s->itemsLabel() }}</div></td>
                        <td class="text-end fw-semibold text-nowrap">{{ baht($s->total) }}</td>
                        <td class="text-end">
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
        <div class="card" style="position:sticky;top:84px">
            <div class="card-header"><i class="bi bi-basket"></i> รายการที่จะขาย</div>
            <div class="card-body">
                <div id="cart" class="mb-2"><div class="small text-muted">กดสินค้าทางซ้าย หรือใส่จำนวนเงิน</div></div>
                <div class="d-flex align-items-center border-top pt-2 mb-3"><span class="fw-semibold">รวม</span><span class="ms-auto fs-3 fw-bold" id="total">0.00</span><span class="ms-1">บาท</span></div>

                <label class="form-label small" for="scan">สแกนบัตรนักเรียน (หรือพิมพ์รหัสนักเรียนแล้วกด Enter)</label>
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
                        <button type="button" class="btn-close ms-auto" id="whoClear" aria-label="เปลี่ยนนักเรียน"></button>
                    </div>
                </div>
                <div id="msg" class="small mb-2" role="status" aria-live="polite"></div>
                <button type="button" class="btn btn-primary btn-lg w-100" id="pay" disabled><i class="bi bi-check2-circle"></i> ตัดเงิน</button>
            </div>
        </div>
    </div>
</div>

@foreach ($sales->whereNull('voided_at') as $s)
    <div class="modal fade" id="void{{ $s->id }}" tabindex="-1">
        <div class="modal-dialog"><form method="POST" action="{{ route('pos.void', $s) }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">ยกเลิกรายการ {{ baht($s->total) }} บาท</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body">
                <div class="small text-muted mb-2">{{ $s->wallet->student->fullName() }} · {{ $s->itemsLabel() }} · เงินจะคืนเข้ากระเป๋านักเรียน</div>
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
<script>
(function () {
    const lookupUrl = @json(route('pos.lookup', $shop)), chargeUrl = @json(route('pos.charge', $shop));
    const token = document.querySelector('meta[name="csrf-token"]').content;
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const money = (n) => Number(n).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let cart = [], student = null, busy = false, key = newKey();

    function newKey() { return (crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random().toString(36).slice(2)).replace(/-/g, ''); }
    const total = () => cart.reduce((s, i) => s + i.price * i.qty, 0);

    const beep = (ok) => {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const o = ctx.createOscillator(); const g = ctx.createGain();
            o.frequency.value = ok ? 1046 : 220; o.connect(g); g.connect(ctx.destination);
            g.gain.setValueAtTime(.2, ctx.currentTime); o.start(); o.stop(ctx.currentTime + (ok ? .12 : .35));
        } catch (e) {}
    };

    function render() {
        $('cart').innerHTML = cart.length ? cart.map((i, n) => `<div class="d-flex align-items-center gap-2 py-1">
            <div class="flex-grow-1">${esc(i.name)}<div class="small text-muted">${money(i.price)} × ${i.qty}</div></div>
            <div class="btn-group btn-group-sm"><button type="button" class="btn btn-light border" data-dec="${n}" aria-label="ลด">−</button><button type="button" class="btn btn-light border" data-inc="${n}" aria-label="เพิ่ม">+</button></div>
            <div class="fw-semibold text-end" style="min-width:70px">${money(i.price * i.qty)}</div></div>`).join('')
            : '<div class="small text-muted">กดสินค้าทางซ้าย หรือใส่จำนวนเงิน</div>';
        $('total').textContent = money(total());
        const t = total();
        let warn = '';
        if (student) {
            if (student.frozen) warn = 'กระเป๋านี้ถูกระงับการใช้จ่าย';
            else if (t > student.balance) warn = 'ยอดเงินไม่พอ';
            else if (student.limit_left !== null && t > student.limit_left) warn = 'เกินวงเงินต่อวัน (ใช้ได้อีก ' + money(student.limit_left) + ' บาท)';
        }
        $('whoWarn').textContent = warn;
        $('pay').disabled = busy || !student || t <= 0 || warn !== '';
    }

    function add(item) {
        const found = item.product_id ? cart.find((i) => i.product_id === item.product_id) : null;
        if (found) found.qty++; else cart.push({ ...item, qty: 1 });
        render();
    }

    document.querySelectorAll('.pos-item').forEach((b) => b.addEventListener('click', () => add({ product_id: Number(b.dataset.id), name: b.dataset.name, price: Number(b.dataset.price) })));
    $('customAdd').addEventListener('click', () => {
        const v = Math.round(Number($('customAmount').value) * 100) / 100;
        if (v > 0) { add({ product_id: null, name: 'รายการอื่น', price: v }); $('customAmount').value = ''; }
        $('scan').focus();
    });
    $('customAmount').addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); $('customAdd').click(); } });
    $('cart').addEventListener('click', (e) => {
        const inc = e.target.dataset.inc, dec = e.target.dataset.dec;
        if (inc !== undefined) cart[inc].qty++;
        if (dec !== undefined && --cart[dec].qty <= 0) cart.splice(dec, 1);
        if (inc !== undefined || dec !== undefined) render();
    });

    function say(text, ok) { $('msg').className = 'small mb-2 ' + (ok ? 'text-success' : 'text-danger'); $('msg').textContent = text; }

    async function lookup(code) {
        code = code.trim();
        if (!code || busy) return;
        busy = true; say('', true);
        try {
            const res = await fetch(lookupUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ code }) });
            const data = await res.json();
            if (!data.ok) { beep(false); say(data.message || 'ไม่พบนักเรียน', false); student = null; $('who').classList.add('d-none'); }
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

    $('pay').addEventListener('click', async () => {
        if (busy || !student) return;
        busy = true; render(); say('กำลังตัดเงิน…', true);
        const body = { student_id: student.id, client_key: key, items: cart.map((i) => ({ product_id: i.product_id, price: i.product_id ? null : i.price, qty: i.qty })) };
        try {
            const res = await fetch(chargeUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': token, 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(body) });
            const data = await res.json();
            if (data.ok) {
                beep(true);
                say(`ตัดเงิน ${money(data.total)} บาท จาก ${student.name} แล้ว · คงเหลือ ${money(data.balance)} บาท`, true);
                $('todayCount').textContent = Number($('todayCount').textContent) + 1;
                $('todayTotal').textContent = money(Number($('todayTotal').textContent.replace(/,/g, '')) + data.total);
                cart = []; student = null; key = newKey(); $('who').classList.add('d-none');
            } else { beep(false); say(data.message || 'ตัดเงินไม่สำเร็จ', false); }
        } catch (e) {
            // เน็ตสะดุด: ไม่เปลี่ยนรหัสรายการ กดซ้ำได้โดยไม่ตัดเงินสองครั้ง
            beep(false); say('เชื่อมต่อไม่ได้ กด "ตัดเงิน" อีกครั้งได้ ระบบจะไม่ตัดซ้ำ', false);
        }
        busy = false; render(); $('scan').focus();
    });

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

    render();
})();
</script>
@endpush
