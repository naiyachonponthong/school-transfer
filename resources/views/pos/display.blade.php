<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>หน้าจอลูกค้า · {{ $shop->name }}</title>
    @include('partials.assets')
    <style>
        /* หน้าจอที่หันหาลูกค้า: ตัวใหญ่ อ่านได้จากระยะยืน ไม่มีเมนูของระบบ */
        html, body { height: 100%; }
        body { margin: 0; background: var(--sb-primary-50, #fdf2f8); display: flex; flex-direction: column; }
        .cd-head { padding: 1rem 1.5rem; display: flex; align-items: center; gap: .75rem; font-size: 1.25rem; font-weight: 700; }
        .cd-head .dot { width: 10px; height: 10px; border-radius: 50%; background: #16a34a; }
        .cd-head .dot.off { background: #dc2626; }
        .cd-main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 1rem 1.5rem 2rem; }
        .cd-card { background: #fff; border-radius: 28px; box-shadow: 0 10px 40px rgba(0, 0, 0, .08); padding: clamp(1.25rem, 3vw, 2.5rem); width: min(100%, 880px); }
        .cd-idle { text-align: center; }
        .cd-idle i { font-size: clamp(4rem, 12vw, 8rem); color: var(--sb-primary-700, #be185d); }
        .cd-idle div { font-size: clamp(1.5rem, 4.5vw, 2.6rem); font-weight: 700; margin-top: .5rem; }
        .cd-items { max-height: 38vh; overflow: auto; font-size: clamp(1.05rem, 2.4vw, 1.5rem); }
        .cd-items .row-item { display: flex; gap: 1rem; padding: .35rem 0; border-bottom: 1px dashed #e5e7eb; }
        .cd-total { display: flex; align-items: baseline; margin-top: 1rem; font-weight: 800; }
        .cd-total .n { margin-left: auto; font-size: clamp(2.6rem, 8vw, 5rem); line-height: 1; }
        .cd-who { display: flex; align-items: center; gap: 1rem; margin-top: 1.25rem; padding-top: 1.25rem; border-top: 2px solid #f1f5f9; font-size: clamp(1.05rem, 2.4vw, 1.5rem); }
        .cd-who img, .cd-who .ini { width: clamp(64px, 10vw, 104px); height: clamp(64px, 10vw, 104px); border-radius: 20px; object-fit: cover; flex-shrink: 0; }
        .cd-who .ini { display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 2rem; }
        .cd-warn { margin-top: 1rem; padding: .75rem 1rem; border-radius: 16px; background: #fee2e2; color: #991b1b; font-weight: 700; font-size: clamp(1.1rem, 2.6vw, 1.6rem); text-align: center; }
        .cd-paid { text-align: center; }
        .cd-paid i { font-size: clamp(4rem, 12vw, 8rem); color: #16a34a; }
        .cd-paid .t { font-size: clamp(1.8rem, 5vw, 3rem); font-weight: 800; }
        .cd-qr { text-align: center; }
        .cd-qr .box { width: min(60vw, 46vh, 420px); margin: 0 auto; background: #fff; padding: 10px; border: 1px solid #e5e7eb; border-radius: 20px; }
        .cd-tap { position: fixed; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(15, 23, 42, .55); color: #fff; font-size: 1.4rem; font-weight: 700; cursor: pointer; }
    </style>
</head>
<body>
    <div class="cd-head"><span class="dot" id="dot" title="สถานะการเชื่อมต่อ"></span>{{ $shop->name }}<span class="ms-auto small fw-normal text-muted" id="clock"></span></div>
    <div class="cd-main"><div class="cd-card" id="card" role="status" aria-live="polite"></div></div>
    <div class="cd-tap" id="tap">แตะที่นี่เพื่อแสดงเต็มจอ</div>

    <script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js"></script>
    <script>
    (function () {
        const stateUrl = @json(route('pos.display.state', $shop));
        const card = document.getElementById('card'), dot = document.getElementById('dot');
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const money = (n) => Number(n).toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const has = (v) => v !== null && v !== undefined;

        const who = (c, extra) => !c ? '' : `<div class="cd-who">${c.photo ? `<img src="${esc(c.photo)}" alt="">` : `<div class="ini tint-primary">${esc(c.initials || '')}</div>`}
            <div><div class="fw-bold">${esc(c.name)}</div><div class="text-muted">${esc(c.sub || '')}</div>${extra || ''}</div></div>`;
        const items = (list) => `<div class="cd-items">${(list || []).map((i) => `<div class="row-item"><div class="flex-grow-1">${esc(i.name)}${i.qty > 1 ? ` <span class="text-muted">× ${i.qty}</span>` : ''}</div><div class="fw-semibold">${money(i.price * i.qty)}</div></div>`).join('')}</div>`;
        const totalRow = (t) => `<div class="cd-total"><span class="fs-3">รวม</span><span class="n">${money(t)}</span><span class="ms-2 fs-4">บาท</span></div>`;

        const views = {
            idle: () => `<div class="cd-idle"><i class="bi bi-credit-card-2-front"></i><div>ยินดีต้อนรับ</div><div class="text-muted fw-normal fs-3">เลือกสินค้าแล้วแตะบัตรที่เครื่อง</div></div>`,
            cart: (s) => items(s.items) + totalRow(s.total || 0)
                + who(s.customer, s.customer && has(s.customer.balance) ? `<div>คงเหลือ <b>${money(s.customer.balance)}</b> บาท${has(s.balance_after) && s.balance_after >= 0 ? ` · หลังซื้อเหลือ <b>${money(s.balance_after)}</b>` : ''}</div>` : '')
                + (s.message ? `<div class="cd-warn">${esc(s.message)}</div>` : (s.customer ? '' : `<div class="text-center text-muted fs-4 mt-3"><i class="bi bi-credit-card-2-front"></i> กรุณาแตะบัตร</div>`)),
            qr: (s) => `<div class="cd-qr"><div class="fs-2 fw-bold mb-2">สแกนจ่าย ${money(s.total || 0)} บาท</div><div class="box" id="qrBox"></div><div class="text-muted fs-4 mt-2">เปิดกระเป๋าเงินในระบบ กด "สแกนจ่าย" แล้วสแกน QR นี้</div></div>`,
            paid: (s) => `<div class="cd-paid"><i class="bi bi-check-circle-fill"></i><div class="t">ชำระแล้ว ${money(s.total || 0)} บาท</div>${has(s.balance_after) ? `<div class="fs-2">คงเหลือ <b>${money(s.balance_after)}</b> บาท</div>` : ''}${s.customer ? `<div class="fs-3 text-muted mt-1">${esc(s.customer.name)}</div>` : ''}<div class="fs-3 mt-2">ขอบคุณครับ/ค่ะ</div></div>`,
            error: (s) => `<div class="cd-idle"><i class="bi bi-x-circle-fill" style="color:#dc2626"></i><div>${esc(s.message || 'ทำรายการไม่สำเร็จ')}</div></div>`,
        };

        let last = '';
        function paint(s) {
            // ไม่ได้รับข้อมูลใหม่จากหน้าจอขายเกิน 10 นาที = กลับไปหน้ารอ (กันข้อมูลลูกค้าคนก่อนค้างบนจอ)
            if (s.at && Date.now() / 1000 - s.at > 600) s = { status: 'idle' };
            const json = JSON.stringify(s);
            if (json === last) return;
            last = json;
            card.innerHTML = (views[s.status] || views.idle)(s);
            if (s.status === 'qr' && s.qr && window.qrcode) {
                const qr = qrcode(0, 'M'); qr.addData(s.qr); qr.make();
                document.getElementById('qrBox').innerHTML = qr.createSvgTag({ cellSize: 6, margin: 1, scalable: true });
            }
        }

        async function tick() {
            try {
                const res = await fetch(stateUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (res.status === 401 || res.status === 419) { location.reload(); return; }
                paint(await res.json()); dot.classList.remove('off');
            } catch (e) { dot.classList.add('off'); }
        }
        paint({ status: 'idle' });
        setInterval(tick, 1000); tick();
        setInterval(() => { document.getElementById('clock').textContent = new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' }); }, 1000);

        // เต็มจอและกันจอดับ ต้องเริ่มจากการแตะของผู้ใช้หนึ่งครั้ง
        document.getElementById('tap').addEventListener('click', async function () {
            this.remove();
            try { if (document.documentElement.requestFullscreen) await document.documentElement.requestFullscreen(); } catch (e) {}
            try { if (navigator.wakeLock) await navigator.wakeLock.request('screen'); } catch (e) {}
        });
    })();
    </script>
</body>
</html>
