/* ระบบบริหารโรงเรียน — สคริปต์รวม (ไม่ต้อง build) */
(function () {
    'use strict';

    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));
    const csrf = () => $('meta[name="csrf-token"]')?.content;

    /* ---------- เมนูมือถือ ---------- */
    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-sb-toggle]')) document.body.classList.toggle('sb-open');
        if (e.target.closest('.sb-backdrop')) document.body.classList.remove('sb-open');
    });

    /* ---------- แจ้งเตือนแบบป๊อปอัป (partials/flash) ---------- */
    const closeToast = (t) => { t.classList.add('hide'); t.classList.remove('show'); setTimeout(() => t.remove(), 300); };
    $$('.sb-toast').forEach((t, i) => {
        setTimeout(() => t.classList.add('show'), 60 + i * 90);
        const life = parseInt(t.dataset.life, 10);
        if (!life) return; // ข้อผิดพลาด: ค้างไว้จนกดปิด
        t.style.setProperty('--life', life + 'ms');
        // แถบเวลาหยุดเมื่อชี้เมาส์ค้าง (CSS) จึงปิดเมื่อแถบวิ่งจบ ไม่ใช้ตัวจับเวลาแยก
        t.querySelector('.bar')?.addEventListener('animationend', () => closeToast(t));
    });
    document.addEventListener('click', (e) => {
        const x = e.target.closest('[data-toast-close]');
        if (x) closeToast(x.closest('.sb-toast'));
    });

    /* ---------- ยืนยันก่อนลบ ---------- */
    document.addEventListener('submit', (e) => {
        // ข้อความยืนยันอยู่ที่ปุ่มที่กด (ฟอร์มเดียวหลายปุ่ม) หรือที่ตัวฟอร์ม
        const msg = e.submitter?.dataset.confirm || e.target.dataset.confirm;
        if (msg && !confirm(msg)) e.preventDefault();
    });

    /* ---------- ลิงก์ทั้งแถวในตาราง ---------- */
    document.addEventListener('click', (e) => {
        const row = e.target.closest('tr[data-href]');
        if (row && !e.target.closest('a,button,input,select,label,form')) location.href = row.dataset.href;
    });

    /* ---------- ส่งฟอร์มเมื่อเปลี่ยนตัวกรอง ---------- */
    $$('[data-autosubmit]').forEach((el) => el.addEventListener('change', () => el.form.submit()));

    /* ---------- ค้นหาด่วน Ctrl+K ---------- */
    const modalEl = $('#searchModal');
    if (modalEl && window.bootstrap) {
        const modal = new bootstrap.Modal(modalEl);
        const input = $('.search-input', modalEl);
        const box = $('#searchResults');
        let timer, active = -1;

        const open = () => { modal.show(); setTimeout(() => input.focus(), 150); };
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); open(); }
            if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) { e.preventDefault(); open(); }
        });
        $$('[data-search-open]').forEach((b) => b.addEventListener('click', open));

        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const render = (items) => {
            active = items.length ? 0 : -1;
            box.innerHTML = items.length
                ? items.map((it, i) => `<a href="${esc(it.url)}" class="${i === 0 ? 'active' : ''}"><span class="sb-avatar sm">${esc(it.title).charAt(0)}</span><div class="flex-grow-1"><div class="fw-semibold">${esc(it.title)}</div><div class="small text-muted">${esc(it.sub)}</div></div><span class="t">${esc(it.type)}</span></a>`).join('')
                : (input.value.trim() ? '<div class="empty py-4"><i class="bi bi-search"></i>ไม่พบข้อมูล</div>' : '');
        };
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                const q = input.value.trim();
                if (!q) return render([]);
                const res = await fetch(modalEl.dataset.url + '?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                render(await res.json());
            }, 180);
        });
        input.addEventListener('keydown', (e) => {
            const links = $$('a', box);
            if (!links.length) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                links[active]?.classList.remove('active');
                active = (active + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length;
                links[active].classList.add('active');
                links[active].scrollIntoView({ block: 'nearest' });
            }
            if (e.key === 'Enter' && links[active]) location.href = links[active].href;
        });
    }

    /* ---------- เช็คชื่อ ---------- */
    const attForm = $('#attendanceForm');
    if (attForm) {
        const rows = $$('.att-row', attForm);
        const keys = { 1: 'present', 2: 'late', 3: 'absent', 4: 'leave', 5: 'sick' };
        let focusIdx = 0;

        const update = () => {
            const counts = { present: 0, late: 0, absent: 0, leave: 0, sick: 0, none: 0 };
            rows.forEach((r) => {
                const c = $('input[type=radio]:checked', r);
                r.dataset.status = c ? c.value : '';
                counts[c ? c.value : 'none']++;
            });
            Object.entries(counts).forEach(([k, v]) => { const el = $(`[data-count="${k}"]`); if (el) el.textContent = v; });
            const pct = $('[data-count="pct"]');
            if (pct) {
                const marked = rows.length - counts.none;
                pct.textContent = marked ? Math.round((counts.present + counts.late) / marked * 100) + '%' : '-';
            }
            $('#unsaved')?.classList.remove('d-none');
        };
        const setFocus = (i) => {
            rows[focusIdx]?.classList.remove('focus');
            focusIdx = Math.max(0, Math.min(rows.length - 1, i));
            rows[focusIdx]?.classList.add('focus');
            rows[focusIdx]?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        };

        attForm.addEventListener('change', (e) => { if (e.target.type === 'radio') update(); });
        rows.forEach((r, i) => r.addEventListener('click', () => setFocus(i)));

        $$('[data-mark-all]').forEach((b) => b.addEventListener('click', () => {
            const st = b.dataset.markAll;
            rows.forEach((r) => {
                const onlyEmpty = b.hasAttribute('data-only-empty');
                if (onlyEmpty && $('input[type=radio]:checked', r)) return;
                const input = $(`input[value="${st}"]`, r);
                if (input) input.checked = true;
            });
            update();
        }));

        document.addEventListener('keydown', (e) => {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName) && document.activeElement.type !== 'radio') return;
            if (keys[e.key]) {
                const input = $(`input[value="${keys[e.key]}"]`, rows[focusIdx]);
                if (input) { input.checked = true; update(); setFocus(focusIdx + 1); }
                e.preventDefault();
            } else if (e.key === 'ArrowDown' || e.key === 'j') { setFocus(focusIdx + 1); e.preventDefault(); }
            else if (e.key === 'ArrowUp' || e.key === 'k') { setFocus(focusIdx - 1); e.preventDefault(); }
        });
        update();
        $('#unsaved')?.classList.add('d-none');
        setFocus(0);
    }

    /* ---------- ตารางคะแนน (บันทึกอัตโนมัติ) ---------- */
    const gb = $('#gradebook');
    if (gb) {
        const inputs = $$('input.score', gb);
        const cols = Number(gb.dataset.cols);
        const maxTotal = Number(gb.dataset.max);
        const state = $('#saveState');
        const scale = [[80, '4'], [75, '3.5'], [70, '3'], [65, '2.5'], [60, '2'], [55, '1.5'], [50, '1'], [0, '0']];
        const activity = gb.dataset.activity === '1', passPct = Number(gb.dataset.pass || 50);
        // ผ = ผ่าน (เขียว) · ร มส มผ = ต้องติดตาม (แดง)
        const gradeColor = (g) => g === 'ผ' ? 'success' : isNaN(g) ? 'danger' : (g >= 3.5 ? 'success' : g >= 2.5 ? 'primary' : g >= 1 ? 'warning' : 'danger');
        const badge = (g) => `<span class="grade-badge bg-${gradeColor(g)}-subtle text-${gradeColor(g)}-emphasis">${g}</span>`;
        let dirty = new Map(), timer;

        const recalc = (tr) => {
            const cells = $$('input.score', tr);
            let total = 0, filled = 0;
            cells.forEach((i) => { if (i.value.trim() !== '' && !i.classList.contains('invalid')) { total += Number(i.value); filled++; } });
            $('.total', tr).textContent = filled ? (Math.round(total * 100) / 100) : '-';
            const g = $('.grade', tr);
            let grade = null;
            if (filled === cells.length && maxTotal > 0) {
                const pct = total / maxTotal * 100;
                grade = activity ? (pct >= passPct ? 'ผ' : 'มผ') : scale.find(([m]) => pct >= m)[1];
            }
            // ผลพิเศษ (ร/มส/มผ) แทนผลจากคะแนน · ผลแก้ตัวแสดงต่อท้ายผลเดิมที่ขีดฆ่า
            if (tr.dataset.special) grade = tr.dataset.special;
            const rem = tr.dataset.remedial;
            if (rem) g.innerHTML = `<span class="small text-muted text-decoration-line-through me-1">${grade ?? '-'}</span>${badge(rem)}`;
            else g.innerHTML = grade === null ? '<span class="text-muted small">-</span>' : badge(grade);
        };

        const validate = (i) => {
            const v = i.value.trim();
            const bad = v !== '' && (isNaN(v) || Number(v) < 0 || Number(v) > Number(i.dataset.max));
            i.classList.toggle('invalid', bad);
            i.title = bad ? `คะแนนต้องอยู่ระหว่าง 0 - ${i.dataset.max}` : '';
            return !bad;
        };

        const save = async () => {
            if (!dirty.size || gb.dataset.readonly) return;
            const body = new FormData();
            dirty.forEach((v, k) => body.append(k, v));
            const sent = new Map(dirty);
            dirty = new Map();
            state.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังบันทึก...';
            try {
                const res = await fetch(gb.dataset.url, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } });
                if (!res.ok) throw new Error(res.status);
                state.innerHTML = '<i class="bi bi-check-circle-fill text-success"></i> บันทึกแล้ว ' + new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
                sent.forEach((_, name) => { const el = gb.querySelector(`[name="${CSS.escape(name)}"]`); el?.classList.add('saved'); setTimeout(() => el?.classList.remove('saved'), 900); });
            } catch (err) {
                sent.forEach((v, k) => dirty.set(k, v));
                state.innerHTML = '<i class="bi bi-exclamation-triangle-fill text-danger"></i> บันทึกไม่สำเร็จ จะลองใหม่อัตโนมัติ';
                clearTimeout(timer); timer = setTimeout(save, 4000);
            }
        };

        inputs.forEach((i, idx) => {
            i.addEventListener('input', () => {
                const ok = validate(i);
                recalc(i.closest('tr'));
                if (ok) { dirty.set(i.name, i.value.trim()); state.textContent = 'มีการแก้ไข...'; clearTimeout(timer); timer = setTimeout(save, 900); }
            });
            i.addEventListener('focus', () => i.select());
            i.addEventListener('keydown', (e) => {
                let t = null;
                if (e.key === 'Enter' || e.key === 'ArrowDown') t = idx + cols;
                else if (e.key === 'ArrowUp') t = idx - cols;
                else if (e.key === 'ArrowRight' && i.selectionStart === i.value.length) t = idx + 1;
                else if (e.key === 'ArrowLeft' && i.selectionStart === 0) t = idx - 1;
                if (t !== null && inputs[t]) { e.preventDefault(); inputs[t].focus(); }
            });
            // วางคะแนนจาก Excel ทั้งคอลัมน์ได้
            i.addEventListener('paste', (e) => {
                const text = (e.clipboardData || window.clipboardData).getData('text');
                const lines = text.split(/\r?\n/).filter((l) => l !== '');
                if (lines.length < 2 && !text.includes('\t')) return;
                e.preventDefault();
                lines.forEach((line, r) => line.split('\t').forEach((val, c) => {
                    const target = inputs[idx + r * cols + c];
                    if (target && (idx % cols) + c < cols) { target.value = val.trim(); target.dispatchEvent(new Event('input')); }
                }));
            });
        });
        window.addEventListener('beforeunload', (e) => { if (dirty.size) { save(); e.preventDefault(); e.returnValue = ''; } });
        $$('tr', gb).forEach((tr) => { if ($('.total', tr)) recalc(tr); });

        // ฟอร์มผลพิเศษ/แก้ตัว: เติมค่าของนักเรียนที่กด
        const outcome = $('#outcome');
        outcome?.addEventListener('show.bs.modal', (e) => {
            const b = e.relatedTarget, f = $('#outcomeForm');
            f.action = b.dataset.outcomeUrl;
            $('[data-field="name"]', f).textContent = b.dataset.outcomeName;
            f.special.value = b.dataset.outcomeSpecial;
            f.remedial_grade.value = b.dataset.outcomeRemedial;
            f.remedied_on.value = b.dataset.outcomeDate;
            f.note.value = b.dataset.outcomeNote;
        });
    }

    /* ---------- เลือกนักเรียนหลายคน (บันทึกพฤติกรรม) ---------- */
    $$('[data-check-all]').forEach((cb) => cb.addEventListener('change', () => {
        $$(cb.dataset.checkAll).forEach((c) => { c.checked = cb.checked; });
        cb.dispatchEvent(new CustomEvent('checks-changed', { bubbles: true }));
    }));
    document.addEventListener('change', (e) => {
        const counter = $('[data-selected-count]');
        if (counter && e.target.matches('.pick-student, [data-check-all]')) {
            counter.textContent = $$('.pick-student:checked').length;
        }
    });

    /* ---------- เพิ่มแถวรายการ (ใบแจ้งหนี้) ---------- */
    $$('[data-add-row]').forEach((btn) => btn.addEventListener('click', () => {
        const tpl = $(btn.dataset.addRow);
        const list = $(btn.dataset.target);
        list.insertAdjacentHTML('beforeend', tpl.innerHTML.replaceAll('__i__', 'n' + Date.now()));
    }));
    document.addEventListener('click', (e) => {
        const rm = e.target.closest('[data-remove-row]');
        if (rm) { rm.closest('.item-row').remove(); document.dispatchEvent(new Event('items-changed')); }
    });
    const sumEl = $('[data-items-sum]');
    if (sumEl) {
        const calc = () => { sumEl.textContent = $$('[data-amount]').reduce((s, i) => s + (Number(i.value) || 0), 0).toLocaleString('th-TH', { minimumFractionDigits: 2 }); };
        document.addEventListener('input', (e) => { if (e.target.matches('[data-amount]')) calc(); });
        document.addEventListener('items-changed', calc);
        calc();
    }

    /* ---------- แสดง/ซ่อนตามค่า select ---------- */
    $$('[data-show-when]').forEach((el) => {
        const [name, value] = el.dataset.showWhen.split('=');
        const scope = el.closest('form') || document;
        const srcs = $$(`[name="${name}"]`, scope);
        if (!srcs.length) return;
        const current = () => {
            const radio = srcs.find((s) => s.type === 'radio');
            return radio ? (srcs.find((s) => s.checked)?.value ?? '') : srcs[0].value;
        };
        const apply = () => el.classList.toggle('d-none', current() !== value);
        srcs.forEach((s) => s.addEventListener('change', apply));
        el.form?.addEventListener('reset', () => setTimeout(apply));
        document.addEventListener('show-when-refresh', apply);
        apply();
    });

    /* ---------- ฟีดข่าว: รีแอ็กชัน / ดูเพิ่ม / แนบรูป ---------- */
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-react]');
        if (btn) {
            btn.disabled = true;
            try {
                const res = await fetch(btn.dataset.url, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ emoji: btn.dataset.react }),
                });
                const { reactions } = await res.json();
                $$('[data-react]', btn.closest('.fp-actions')).forEach((b) => {
                    const r = reactions[b.dataset.react] || { count: 0, mine: false };
                    b.classList.toggle('mine', r.mine);
                    b.setAttribute('aria-pressed', String(r.mine));
                    $('.n', b).textContent = r.count || '';
                });
            } finally { btn.disabled = false; }
            return;
        }
        const more = e.target.closest('[data-feed-more] button');
        if (more) {
            more.disabled = true;
            more.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            const res = await fetch(more.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            more.closest('[data-feed-more]').outerHTML = await res.text();
            return;
        }
        const ct = e.target.closest('[data-compose-type]');
        if (ct) {
            const radio = document.querySelector(`#composeModal input[name="type"][value="${ct.dataset.composeType}"]`);
            if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change')); }
        }
    });
    document.addEventListener('change', (e) => {
        const input = e.target.closest('input[type=file][data-preview]');
        if (!input || !input.files[0]) return;
        const img = $(input.dataset.preview);
        img.src = URL.createObjectURL(input.files[0]);
        img.classList.remove('d-none');
    });

    /* ---------- ตัวอย่างสีธีม (หน้าตั้งค่า) ---------- */
    const themeInput = $('[data-theme-input]');
    if (themeInput) {
        const text = $('[data-theme-text]');
        const setActive = (v) => $$('.swatch').forEach((s) => s.classList.toggle('active', s.dataset.color.toLowerCase() === v.toLowerCase()));
        const sync = (v) => { themeInput.value = v; text.value = v.toUpperCase(); setActive(v); $('[data-theme-preview]')?.style.setProperty('--prev', v); };
        $$('.swatch').forEach((s) => s.addEventListener('click', () => sync(s.dataset.color)));
        themeInput.addEventListener('input', () => sync(themeInput.value));
        text.addEventListener('input', () => { if (/^#[0-9a-f]{6}$/i.test(text.value)) { themeInput.value = text.value; setActive(text.value); $('[data-theme-preview]')?.style.setProperty('--prev', text.value); } });
        setActive(themeInput.value);
    }

    /* ---------- วาด QR (บัตรนักเรียน / พร้อมเพย์) ---------- */
    if (window.qrcode) {
        $$('[data-qr]').forEach((el) => {
            const qr = qrcode(0, 'M');
            qr.addData(el.dataset.qr);
            qr.make();
            el.innerHTML = qr.createSvgTag({ cellSize: Number(el.dataset.cell || 3), margin: 1, scalable: true });
        });
    }

    /* ---------- ฟอร์มลงเวลาแนบพิกัด GPS ---------- */
    document.addEventListener('submit', (e) => {
        const form = e.target.closest('form[data-geo]');
        if (!form || form.dataset.geoDone || !navigator.geolocation) return;
        e.preventDefault();
        const btn = form.querySelector('button');
        if (btn) { btn.disabled = true; btn.dataset.html = btn.innerHTML; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังหาตำแหน่ง...'; }
        const done = (pos) => {
            if (pos) {
                ['lat', 'lng'].forEach((k) => {
                    let i = form.querySelector(`input[name=${k}]`);
                    if (!i) { i = document.createElement('input'); i.type = 'hidden'; i.name = k; form.appendChild(i); }
                    i.value = k === 'lat' ? pos.coords.latitude : pos.coords.longitude;
                });
            }
            form.dataset.geoDone = '1';
            form.submit();
        };
        navigator.geolocation.getCurrentPosition(done, () => done(null), { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 });
    }, true);

    /* ---------- เวลาตอนนี้ (หน้าลงเวลา) ---------- */
    const clock = $('[data-clock]');
    if (clock) {
        const tick = () => { clock.textContent = new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' }); };
        tick(); setInterval(tick, 1000);
    }

    /* ---------- พรีวิวรูปก่อนอัปโหลด (กล่อง .photo-drop) ---------- */
    document.addEventListener('change', (e) => {
        const input = e.target.closest('.photo-drop input[type=file]');
        if (!input || !input.files?.[0]) return;
        const box = input.closest('.photo-drop');
        let img = $('img', box);
        if (!img) { img = document.createElement('img'); img.alt = ''; box.prepend(img); }
        img.src = URL.createObjectURL(input.files[0]);
        $('.hint', box)?.classList.add('d-none');
    });
})();

// ตาราง: .table-cards ใส่ป้ายหัวคอลัมน์ให้ทุกช่อง (มือถือแสดงเป็นการ์ด) ·
// ตารางที่ไม่มีกรอบเลื่อน ห่อด้วย .table-responsive เพื่อไม่ให้ทั้งหน้ากว้างเกินจอ
document.querySelectorAll('table.table').forEach((t) => {
    if (t.classList.contains('table-cards')) {
        const heads = [...t.querySelectorAll(':scope > thead > tr:last-child > th')].map((th) => th.textContent.trim());
        t.querySelectorAll(':scope > tbody > tr, :scope > tfoot > tr').forEach((tr) => {
            let col = 0;
            [...tr.children].forEach((td) => {
                if (td.colSpan === 1 && heads[col] && !td.hasAttribute('data-label')) td.dataset.label = heads[col];
                col += td.colSpan || 1;
            });
        });
    }
    if (!t.closest('.table-responsive')) {
        const wrap = document.createElement('div');
        wrap.className = 'table-responsive';
        t.replaceWith(wrap);
        wrap.appendChild(t);
    }
});
