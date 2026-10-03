/* กระดาษคำตอบ (SVG 210×297 มม.) — ย้ายมาจาก ScanGrade js_sheet.html ตำแหน่งทุกจุดมาจาก sheet-layout.js ตัวเดียวกับตัวอ่าน
 * SGSheet.svg({ n, groups, school, logo, subject, subject_short, exam_date, classroom, footer, student:{name, code, seat_no, seat_group, classroom} }) */
(function () {
  'use strict';
  var esc = function (s) { return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var CHOICES = ['ก', 'ข', 'ค', 'ง'];

  function t(x, y, s, str, o) {
    o = o || {};
    return '<text x="' + x + '" y="' + y + '" font-size="' + s + '"' + (o.anchor ? ' text-anchor="' + o.anchor + '"' : '') +
      (o.weight ? ' font-weight="' + o.weight + '"' : '') + (o.fill ? ' fill="' + o.fill + '"' : '') + '>' + esc(str) + '</text>';
  }
  function bubble(x, y, r, label, filled, ls) {
    return '<circle cx="' + x + '" cy="' + y + '" r="' + r + '" fill="' + (filled ? '#000' : '#fff') + '" stroke="#555" stroke-width="0.22"/>' +
      (label !== '' && !filled ? '<text x="' + x + '" y="' + (y + ls * 0.36) + '" font-size="' + ls + '" text-anchor="middle" fill="#9a9a9a">' + esc(label) + '</text>' : '');
  }
  function dots(x1, x2, y) { return '<line x1="' + x1 + '" y1="' + y + '" x2="' + x2 + '" y2="' + y + '" stroke="#444" stroke-width="0.3" stroke-dasharray="0.35 0.9" stroke-linecap="round"/>'; }

  function svg(o) {
    var L = window.SheetLayout.layout(o.n, o.groups), h = [], st = o.student || null;
    h.push('<svg xmlns="http://www.w3.org/2000/svg" class="omr-sheet" width="210mm" height="297mm" viewBox="0 0 210 297" font-family="Sarabun, \'TH Sarabun New\', sans-serif">');
    h.push('<rect width="210" height="297" fill="#fff"/>');
    L.fiducials.forEach(function (f) { h.push('<rect x="' + f.x + '" y="' + f.y + '" width="' + f.s + '" height="' + f.s + '" fill="#000"/>'); });

    // หัวกระดาษ
    h.push('<rect x="20" y="11" width="170" height="11.5" fill="#d9d9d9"/>');
    h.push(t(105, 19.3, 6.2, 'กระดาษคำตอบ', { anchor: 'middle', weight: 600 }));
    var hasLogo = !!o.logo, lineEnd = hasLogo ? 168 : 190;
    h.push(t(20, 32, 4, 'รายวิชา')); h.push(dots(33.5, 98, 32.6));
    if (o.subject) h.push(t(35, 31.2, 3.6, o.subject, { weight: 500 }));
    h.push(t(100, 32, 4, 'โรงเรียน')); h.push(dots(114.5, lineEnd, 32.6));
    if (o.school) h.push(t(116, 31.2, 3.6, o.school, { weight: 500 }));
    if (hasLogo) h.push('<image href="' + esc(o.logo) + '" x="171" y="24.5" width="19" height="19" preserveAspectRatio="xMidYMid meet"/>');
    h.push(t(20, 40.5, 3.6, 'คำชี้แจง : ให้นักเรียนระบายคำตอบลงในช่อง ◯ ให้ถูกต้อง ด้วยดินสอ 2B ให้เต็มวง'));

    var by = 45.5, rh = 10;
    h.push('<rect x="20" y="' + by + '" width="170" height="' + (rh * 2) + '" rx="4" fill="#fff" stroke="#000" stroke-width="0.7"/>');
    h.push('<path d="M20 ' + (by + 4) + ' a4 4 0 0 1 4 -4 H38 V' + (by + rh * 2) + ' H24 a4 4 0 0 1 -4 -4 Z" fill="#cfcfcf"/>');
    h.push('<rect x="134" y="' + by + '" width="14" height="' + rh + '" fill="#e2e2e2"/>');
    h.push('<rect x="108" y="' + (by + rh) + '" width="14" height="' + rh + '" fill="#e2e2e2"/>');
    h.push('<rect x="20" y="' + by + '" width="170" height="' + (rh * 2) + '" rx="4" fill="none" stroke="#000" stroke-width="0.7"/>');
    h.push('<line x1="20" y1="' + (by + rh) + '" x2="190" y2="' + (by + rh) + '" stroke="#000" stroke-width="0.5"/>');
    [[38, 0, 2], [134, 0, 1], [148, 0, 1], [108, 1, 1], [122, 1, 1]].forEach(function (v) {
      h.push('<line x1="' + v[0] + '" y1="' + (by + v[1] * rh) + '" x2="' + v[0] + '" y2="' + (by + (v[1] + v[2]) * rh) + '" stroke="#000" stroke-width="0.5"/>');
    });
    h.push(t(29, by + 6.6, 3.8, 'ชื่อ-สกุล', { anchor: 'middle', weight: 500 }));
    h.push(t(141, by + 6.6, 3.8, 'ชั้น', { anchor: 'middle', weight: 500 }));
    h.push(t(29, by + rh + 6.6, 3.8, 'วันสอบ', { anchor: 'middle', weight: 500 }));
    h.push(t(115, by + rh + 6.6, 3.8, 'วิชา', { anchor: 'middle', weight: 500 }));
    if (st && st.name) h.push(t(41, by + 6.8, 4.2, st.name, { weight: 500 }));
    var cls = (st && st.classroom) || o.classroom;
    if (cls) h.push(t(151, by + 6.8, 4, cls, { weight: 500 }));
    if (o.exam_date) h.push(t(41, by + rh + 6.8, 4, o.exam_date, { weight: 500 }));
    if (o.subject_short) h.push(t(125, by + rh + 6.8, 3.8, o.subject_short, { weight: 500 }));

    // เลขประจำตัว 5 หลัก
    var code = st && st.code ? ('00000' + String(st.code).replace(/\D/g, '')).slice(-5) : '';
    h.push(t(L.code.label.x, L.code.label.y, 3.6, 'เลขประจำตัว', { weight: 600 }));
    L.code.cols.forEach(function (col, ci) {
      h.push('<rect x="' + (col.x - 2.9) + '" y="' + L.code.boxY + '" width="5.8" height="' + L.code.boxH + '" fill="#fff" stroke="#000" stroke-width="0.3"/>');
      if (code) h.push(t(col.x, L.code.boxY + 5, 4.2, code.charAt(ci), { anchor: 'middle', weight: 600 }));
      col.rows.forEach(function (b) { h.push(bubble(b.x, b.y, L.bubbleR, b.v, code && code.charAt(ci) === b.v, 2.6)); });
    });
    h.push('<rect x="20.5" y="' + (L.code.boxY - 0.8) + '" width="34.5" height="' + (L.code.cols[0].rows[9].y + 3.4 - L.code.boxY + 0.8) + '" rx="1.5" fill="none" stroke="#888" stroke-width="0.25"/>');

    // เลขที่
    var sNo = st && st.seat_no ? ('0' + st.seat_no).slice(-2) : '', sG = st ? st.seat_group || '' : '';
    h.push(t(L.seat.label.x, L.seat.label.y, 3.6, 'เลขที่', { weight: 600 }));
    [[25, sNo.charAt(0)], [31.2, sNo.charAt(1)], [40.6, sG]].forEach(function (v) {
      h.push('<rect x="' + (v[0] - 2.9) + '" y="' + L.seat.boxY + '" width="5.8" height="' + L.seat.boxH + '" fill="#fff" stroke="#000" stroke-width="0.3"/>');
      if (st && v[1]) h.push(t(v[0], L.seat.boxY + 5, 4.2, v[1], { anchor: 'middle', weight: 600 }));
    });
    L.seat.tens.forEach(function (b) { h.push(bubble(b.x, b.y, L.bubbleR, b.v, sNo && sNo.charAt(0) === b.v, 2.6)); });
    L.seat.units.forEach(function (b) { h.push(bubble(b.x, b.y, L.bubbleR, b.v, sNo && sNo.charAt(1) === b.v, 2.6)); });
    L.seat.group.forEach(function (b) { h.push(bubble(b.x, b.y, L.bubbleR, b.v, sG === b.v, 2.6)); });
    var seatBottom = L.seat.units[9].y + 3.4;
    h.push('<rect x="20.5" y="' + (L.seat.boxY - 0.8) + '" width="24.2" height="' + (seatBottom - L.seat.boxY + 0.8) + '" rx="1.5" fill="none" stroke="#888" stroke-width="0.25"/>');
    h.push(t(47, L.seat.boxY + 4.8, 2.6, 'หลักสิบ · หน่วย', { fill: '#777' }));
    h.push(t(47, L.seat.boxY + 8.3, 2.6, '· กลุ่ม', { fill: '#777' }));

    // ตัวอย่างการระบาย
    var ey = seatBottom + 8, wx = [37, 44, 51, 58];
    h.push('<rect x="20.5" y="' + ey + '" width="50" height="30" rx="1.5" fill="#f4f4f4"/>');
    h.push(t(23, ey + 5.5, 3.2, 'ตัวอย่างการระบาย', { weight: 600 }));
    h.push(t(23, ey + 12.5, 3.2, 'ถูก'));
    h.push('<circle cx="37" cy="' + (ey + 11.4) + '" r="2.1" fill="#000"/>');
    h.push(t(23, ey + 20, 3.2, 'ผิด'));
    h.push('<circle cx="' + wx[0] + '" cy="' + (ey + 18.9) + '" r="2.1" fill="#fff" stroke="#555" stroke-width="0.22"/><path d="M' + (wx[0] - 1.2) + ' ' + (ey + 18.9) + ' l0.9 1 l1.6 -2" stroke="#000" stroke-width="0.5" fill="none"/>');
    h.push('<circle cx="' + wx[1] + '" cy="' + (ey + 18.9) + '" r="2.1" fill="#fff" stroke="#555" stroke-width="0.22"/><path d="M' + (wx[1] - 1.1) + ' ' + (ey + 17.8) + ' l2.2 2.2 M' + (wx[1] + 1.1) + ' ' + (ey + 17.8) + ' l-2.2 2.2" stroke="#000" stroke-width="0.5"/>');
    h.push('<circle cx="' + wx[2] + '" cy="' + (ey + 18.9) + '" r="2.1" fill="#fff" stroke="#555" stroke-width="0.22"/><path d="M' + wx[2] + ' ' + (ey + 16.8) + ' a2.1 2.1 0 0 1 0 4.2 Z" fill="#000"/>');
    h.push('<circle cx="' + wx[3] + '" cy="' + (ey + 18.9) + '" r="2.1" fill="#fff" stroke="#555" stroke-width="0.22"/><circle cx="' + wx[3] + '" cy="' + (ey + 18.9) + '" r="0.7" fill="#000"/>');
    h.push(t(23, ey + 26.5, 2.6, 'ต้องการเปลี่ยนคำตอบ ลบให้สะอาด', { fill: '#555' }));

    // คำตอบ
    L.answer.cols.forEach(function (c, ci) {
      if (!L.answer.items.some(function (it) { return it.col === ci; })) return;
      h.push('<rect x="' + c.mark.x + '" y="' + c.mark.y + '" width="' + c.mark.s + '" height="' + c.mark.s + '" fill="#000"/>');
      CHOICES.forEach(function (ch, k) { h.push(t(c.choiceX[k], L.answer.headerY + 1, 4, ch, { anchor: 'middle', weight: 600 })); });
    });
    var ls = Math.min(2.8, L.answer.r * 1.25);
    L.answer.items.forEach(function (it) {
      if (it.row % 5 === 0 && it.row) {
        var yy = it.y - L.answer.pitch / 2 - 0.8;
        h.push('<line x1="' + (it.labelX - 5.5) + '" y1="' + yy + '" x2="' + (it.xs[3] + 3) + '" y2="' + yy + '" stroke="#ccc" stroke-width="0.2"/>');
      }
      h.push(t(it.labelX, it.y + 1.3, 3.6, String(it.no), { anchor: 'end', weight: 600 }));
      it.xs.forEach(function (x, k) { h.push(bubble(x, it.y, L.answer.r, CHOICES[k], false, ls)); });
    });

    // timing marks + รหัสแม่แบบ (บอกจำนวนข้อ)
    L.timing.forEach(function (m) { h.push('<rect x="' + m.x + '" y="' + m.y + '" width="' + m.w + '" height="' + m.h + '" fill="#000"/>'); });
    L.bits.forEach(function (b) { h.push('<rect x="' + b.x + '" y="' + b.y + '" width="' + b.s + '" height="' + b.s + '" fill="' + (b.on ? '#000' : '#fff') + '" stroke="#000" stroke-width="0.25"/>'); });
    h.push(t(90, 285, 2.6, 'ScanGrade · แบบ ' + L.n + ' ข้อ' + (o.footer ? ' · ' + o.footer : ''), { fill: '#666' }));
    h.push('</svg>');
    return h.join('');
  }

  /** พิมพ์หลายแผ่น: ใส่ลง #printArea แล้วซ่อนส่วนอื่นเฉพาะตอนพิมพ์ (รอฟอนต์/รูปโหลดครบก่อน) */
  function print(pages) {
    var pa = document.getElementById('printArea');
    if (!pa) { pa = document.createElement('div'); pa.id = 'printArea'; document.body.appendChild(pa); }
    pa.innerHTML = pages.map(function (p) { return '<div class="omr-page">' + p + '</div>'; }).join('');
    document.body.classList.add('printing-omr');
    if (!document.getElementById('omrPageStyle')) {
      var st = document.createElement('style'); st.id = 'omrPageStyle'; st.textContent = '@page { size: A4; margin: 0; }';
      document.head.appendChild(st);
    }
    var go = function () { setTimeout(function () { window.print(); }, 150); };
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(go); else go();
  }
  window.addEventListener('afterprint', function () {
    document.body.classList.remove('printing-omr');
    var st = document.getElementById('omrPageStyle'); if (st) st.remove();
    var pa = document.getElementById('printArea'); if (pa) pa.innerHTML = '';
  });

  window.SGSheet = { svg: svg, print: print, CHOICES: CHOICES };
})();
