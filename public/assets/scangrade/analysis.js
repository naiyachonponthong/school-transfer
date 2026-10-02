/* วิเคราะห์ข้อสอบแบบ EVANA — คำนวณด้วย evana.js (ตรวจกับผลจริงของโปรแกรม EVANA แล้ว) ในเบราว์เซอร์ */
(function () {
  'use strict';
  var app = document.getElementById('anApp');
  if (!app) return;
  var SG = window.SG, esc = SG.esc, CH = SG.CH, f2 = Evana.fmt2;
  var papers = JSON.parse(app.dataset.papers), key = JSON.parse(app.dataset.key), cancelled = JSON.parse(app.dataset.cancelled);
  var meta = JSON.parse(app.dataset.meta);
  var tech = document.getElementById('anTech'), room = document.getElementById('anRoom'), body = document.getElementById('anBody');
  var last = null;
  // คุณภาพรายข้อ = สถานะ → สี + รูปทรง + ข้อความเสมอ (ไม่ใช้สีอย่างเดียว)
  var Q = {
    good: { label: 'ใช้ได้', color: '#15803d', badge: 'success', shape: 'circle' },
    revise: { label: 'ควรปรับปรุง', color: '#b45309', badge: 'warning', shape: 'triangle' },
    drop: { label: 'ควรตัดทิ้ง', color: '#b91c1c', badge: 'danger', shape: 'cross' }
  };

  function run() {
    var list = papers.filter(function (p) { return !room.value || String(p.room) === room.value; });
    if (list.length < 4) {
      body.innerHTML = '<div class="card"><div class="empty"><i class="bi bi-graph-up"></i>ต้องมีกระดาษคำตอบที่ตรวจแล้วอย่างน้อย 4 แผ่น (ตอนนี้ ' + list.length + ' แผ่น)</div></div>';
      last = null; return;
    }
    var res = Evana.analyze({ key: key, cancelled: cancelled, nChoices: 4, students: list.map(function (p) { return { id: p.id, answers: p.answers }; }), technique: Number(tech.value) });
    last = res;
    var S = res.summary, q = res.quality;
    var stat = function (v, l, icon) { return '<div class="col-6 col-md-4 col-xl-2"><div class="card h-100"><div class="card-body py-2"><div class="small text-muted"><i class="bi ' + icon + '"></i> ' + l + '</div><div class="fs-5 fw-bold">' + v + '</div></div></div></div>'; };
    var h = '<div class="row g-2 mb-3">' +
      stat(S.nPapers + ' <small class="fs-6 fw-normal text-muted">แผ่น</small>', 'ผู้สอบ · กลุ่มละ ' + res.groupSize + ' คน', 'bi-people') +
      stat(S.mean.toFixed(2) + ' <small class="fs-6 fw-normal text-muted">± ' + S.sd.toFixed(2) + '</small>', 'เฉลี่ย ± S.D. (เต็ม ' + S.nItems + ')', 'bi-bar-chart') +
      stat(S.max + ' / ' + S.min, 'สูงสุด / ต่ำสุด', 'bi-arrows-vertical') +
      stat(S.kr20.toFixed(4), 'ความเชื่อมั่น KR-20', 'bi-shield-check') +
      stat(S.kr21.toFixed(4), 'KR-21', 'bi-shield') +
      stat(S.sem.toFixed(4), 'SEM', 'bi-plus-slash-minus') + '</div>';

    h += '<div class="row g-3 mb-3"><div class="col-lg-5"><div class="card h-100"><div class="card-header"><i class="bi bi-clipboard-check"></i> สรุปคุณภาพข้อสอบ <span class="small text-muted fw-normal ms-1">เกณฑ์ p ' + f2(q.criteria.pMin) + '–' + f2(q.criteria.pMax) + ', r ≥ ' + f2(q.criteria.rMin) + '</span></div><div class="card-body">';
    ['good', 'revise', 'drop'].forEach(function (k) {
      h += '<div class="mb-2"><span class="badge bg-' + Q[k].badge + '">' + Q[k].label + ' ' + q[k].length + ' ข้อ</span> <span class="small">' + (q[k].join(', ') || '-') + '</span></div>';
    });
    h += '</div></div></div><div class="col-lg-7"><div class="card h-100"><div class="card-header"><i class="bi bi-graph-up"></i> ความยาก (p) – อำนาจจำแนก (r) รายข้อ</div><div class="card-body">' + scatter(res) + '</div></div></div></div>';
    h += '<div class="card mb-3"><div class="card-header"><i class="bi bi-bar-chart"></i> การแจกแจงคะแนน</div><div class="card-body">' + histogram(res) + '</div></div>';

    // ตารางรายข้อแบบ EVANA
    var is27 = res.technique === 27;
    h += '<div class="card"><div class="card-header"><i class="bi bi-table"></i> ตารางวิเคราะห์รายข้อ <span class="small text-muted fw-normal ms-1">* = ตัวถูก</span></div><div class="table-responsive"><table class="table table-sm align-middle mb-0 evana-table"><thead><tr>' +
      '<th>ข้อ</th><th>ตัวเลือก</th><th class="text-end">H</th><th class="text-end">L</th>' + (is27 ? '<th class="text-end">PH</th><th class="text-end">PL</th>' : '') +
      '<th class="text-end">p</th><th class="text-end">r</th><th class="text-end">Delta</th><th>วิจารณ์</th><th>สรุป</th></tr></thead><tbody>';
    res.items.forEach(function (it) {
      if (!it.options.length) {
        h += '<tr class="text-muted"><td class="fw-bold">' + it.no + '</td><td colspan="' + (is27 ? 10 : 8) + '">' + (it.cancelled ? 'ยกเลิกข้อนี้ — ไม่นำมาวิเคราะห์' : 'ไม่มีเฉลย') + '</td></tr>';
        return;
      }
      it.options.forEach(function (x, k) {
        var first = k === 0, qq = Q[it.quality];
        h += '<tr class="' + (first ? 'item-first' : '') + (x.isKey ? ' fw-semibold' : '') + '">' +
          (first ? '<td rowspan="4" class="fw-bold align-top">' + it.no + '</td>' : '') +
          '<td>' + (x.isKey ? '*' : '&nbsp;') + CH[x.choice - 1] + '</td><td class="text-end">' + x.H + '</td><td class="text-end">' + x.L + '</td>' +
          (is27 ? '<td class="text-end">' + f2(x.pH) + '</td><td class="text-end">' + f2(x.pL) + '</td>' : '') +
          '<td class="text-end">' + f2(x.p) + '</td><td class="text-end">' + f2(x.r) + '</td><td class="text-end">' + (Math.round(x.delta * 10) / 10).toFixed(1) + '</td>' +
          '<td class="small">' + esc(x.comment) + '</td>' +
          (first ? '<td rowspan="4" class="align-top small"><span class="badge bg-' + qq.badge + '-subtle text-' + qq.badge + '-emphasis">' + qq.label + '</span><div class="text-muted mt-1">' + esc((it.notes || []).join(' · ')) + '</div></td>' : '') + '</tr>';
      });
    });
    body.innerHTML = h + '</tbody></table></div></div>';
  }

  /** p (แกนนอน) – r (แกนตั้ง) รายข้อ · กรอบเกณฑ์ใช้ได้เป็นพื้นจาง */
  function scatter(res) {
    var W = 520, H = 260, m = { l: 40, r: 12, t: 10, b: 32 }, iw = W - m.l - m.r, ih = H - m.t - m.b;
    var rMin = -0.2, rMax = 1, X = function (p) { return m.l + p * iw; }, Y = function (r) { return m.t + (rMax - Math.max(rMin, Math.min(rMax, r))) / (rMax - rMin) * ih; };
    var c = res.quality.criteria, s = '<svg viewBox="0 0 ' + W + ' ' + H + '" class="w-100" role="img" aria-label="กราฟความยากและอำนาจจำแนกรายข้อ">';
    s += '<rect x="' + X(c.pMin) + '" y="' + Y(rMax) + '" width="' + (X(c.pMax) - X(c.pMin)) + '" height="' + (Y(c.rMin) - Y(rMax)) + '" fill="#15803d" opacity=".07"/>';
    [0, 0.2, 0.4, 0.6, 0.8, 1].forEach(function (v) {
      s += '<line x1="' + X(v) + '" y1="' + m.t + '" x2="' + X(v) + '" y2="' + (m.t + ih) + '" stroke="#eef0f3"/><text x="' + X(v) + '" y="' + (H - 14) + '" font-size="12" fill="#6b7280" text-anchor="middle">' + f2(v) + '</text>';
    });
    [-0.2, 0, 0.2, 0.4, 0.6, 0.8, 1].forEach(function (v) {
      s += '<line x1="' + m.l + '" y1="' + Y(v) + '" x2="' + (m.l + iw) + '" y2="' + Y(v) + '" stroke="' + (v === 0 ? '#9ca3af' : '#eef0f3') + '"/><text x="' + (m.l - 6) + '" y="' + (Y(v) + 3) + '" font-size="12" fill="#6b7280" text-anchor="end">' + f2(v) + '</text>';
    });
    s += '<text x="' + (m.l + iw / 2) + '" y="' + (H - 1) + '" font-size="12" fill="#6b7280" text-anchor="middle">ความยาก p (มาก = ง่าย)</text>';
    s += '<text x="10" y="' + (m.t + ih / 2) + '" font-size="12" fill="#6b7280" text-anchor="middle" transform="rotate(-90 10 ' + (m.t + ih / 2) + ')">อำนาจจำแนก r</text>';
    res.items.forEach(function (it) {
      if (!it.options.length) return;
      var q = Q[it.quality], x = X(it.p), y = Y(it.r), tip = '<title>ข้อ ' + it.no + ' · p ' + f2(it.p) + ' · r ' + f2(it.r) + ' · ' + q.label + '</title>';
      var mark = q.shape === 'circle' ? '<circle cx="' + x + '" cy="' + y + '" r="4.5" fill="' + q.color + '" stroke="#fff" stroke-width="2"/>'
        : q.shape === 'triangle' ? '<path d="M' + x + ' ' + (y - 5.5) + 'L' + (x + 5) + ' ' + (y + 4) + 'L' + (x - 5) + ' ' + (y + 4) + 'Z" fill="' + q.color + '" stroke="#fff" stroke-width="2"/>'
          : '<path d="M' + (x - 4) + ' ' + (y - 4) + 'L' + (x + 4) + ' ' + (y + 4) + 'M' + (x + 4) + ' ' + (y - 4) + 'L' + (x - 4) + ' ' + (y + 4) + '" stroke="' + q.color + '" stroke-width="2.5" stroke-linecap="round"/>';
      s += '<g>' + tip + '<circle cx="' + x + '" cy="' + y + '" r="10" fill="transparent"/>' + mark + '</g>';
    });
    s += '</svg><div class="small text-muted d-flex gap-3 flex-wrap mt-1">' +
      '<span><svg width="12" height="12"><circle cx="6" cy="6" r="4.5" fill="' + Q.good.color + '"/></svg> ใช้ได้</span>' +
      '<span><svg width="12" height="12"><path d="M6 1L11 10L1 10Z" fill="' + Q.revise.color + '"/></svg> ควรปรับปรุง</span>' +
      '<span><svg width="12" height="12"><path d="M2 2L10 10M10 2L2 10" stroke="' + Q.drop.color + '" stroke-width="2.5"/></svg> ควรตัดทิ้ง</span>' +
      '<span>พื้นเขียวจาง = อยู่ในเกณฑ์ · ชี้ที่จุดเพื่อดูเลขข้อ</span></div>';
    return s;
  }

  /** จำนวนผู้สอบตามคะแนน (แท่งเดียวสี) */
  function histogram(res) {
    var K = res.summary.nItems, counts = [], i;
    for (i = 0; i <= K; i++) counts.push(0);
    res.scores.forEach(function (s) { counts[s.score]++; });
    var W = 720, H = 160, m = { l: 28, r: 8, t: 14, b: 22 }, iw = W - m.l - m.r, ih = H - m.t - m.b;
    var mx = Math.max.apply(null, counts) || 1, bw = iw / (K + 1), step = K > 40 ? 10 : K > 20 ? 5 : K > 10 ? 2 : 1;
    var s = '<svg viewBox="0 0 ' + W + ' ' + H + '" class="w-100" role="img" aria-label="การแจกแจงคะแนน">';
    s += '<line x1="' + m.l + '" y1="' + (m.t + ih) + '" x2="' + (m.l + iw) + '" y2="' + (m.t + ih) + '" stroke="#9ca3af"/>';
    s += '<text x="' + (m.l - 6) + '" y="' + (m.t + 4) + '" font-size="12" fill="#6b7280" text-anchor="end">' + mx + '</text>';
    counts.forEach(function (c, v) {
      var x = m.l + v * bw, hgt = c / mx * ih;
      if (c) s += '<g><title>' + v + ' คะแนน: ' + c + ' คน</title><rect x="' + (x + 1) + '" y="' + (m.t + ih - hgt) + '" width="' + Math.max(1, bw - 2) + '" height="' + hgt + '" rx="' + Math.min(4, bw / 3) + '" fill="var(--sb-primary)"/></g>';
      if (v % step === 0) s += '<text x="' + (x + bw / 2) + '" y="' + (H - 6) + '" font-size="12" fill="#6b7280" text-anchor="middle">' + v + '</text>';
    });
    return s + '</svg>';
  }

  document.getElementById('anTxt').addEventListener('click', function () {
    if (!last) { SG.toast('ยังไม่มีผลวิเคราะห์', true); return; }
    var blob = new Blob(['﻿' + Evana.toTxt(last, meta)], { type: 'text/plain;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = (meta.subject_code || 'exam') + '_' + last.technique + '.txt';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
  });
  tech.addEventListener('change', run);
  room.addEventListener('change', run);
  run();
})();
