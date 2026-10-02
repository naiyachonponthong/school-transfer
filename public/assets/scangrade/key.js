/* หน้าใส่เฉลย: คลิกวง · พิมพ์ต่อเนื่อง (1–4 / ก–ง ทั้งแป้นไทยและอังกฤษ) · วางแถว KEY · ยกเลิกข้อ · ทดลองตรวจ */
(function () {
  'use strict';
  var card = document.getElementById('keyCard');
  if (!card) return;
  var SG = window.SG, CH = SG.CH;
  var n = Number(card.dataset.n);
  var key = JSON.parse(card.dataset.key || '[]');
  var cancelled = {};
  JSON.parse(card.dataset.cancelled || '[]').forEach(function (q) { cancelled[q] = true; });
  while (key.length < n) key.push('');
  var grid = document.getElementById('keyGrid'), cur = 0, dirty = false;
  var multi = document.getElementById('multiKey');
  multi.checked = key.some(function (k) { return k.length > 1; });

  function render() {
    var h = '';
    for (var i = 0; i < n; i++) {
      if (i % 5 === 0 && i) h += '<div class="kg-gap"></div>';
      h += '<div class="kg-row' + (i === cur ? ' cur' : '') + (cancelled[i + 1] ? ' canc' : '') + (!key[i] && !cancelled[i + 1] ? ' empty' : '') + '" data-i="' + i + '">' +
        '<span class="kg-no">' + (i + 1) + '</span>';
      for (var c = 1; c <= 4; c++) h += '<button type="button" class="kb' + (key[i].indexOf(String(c)) > -1 ? ' on' : '') + '" data-c="' + c + '" tabindex="-1">' + CH[c - 1] + '</button>';
      h += '<button type="button" class="kg-x" data-x title="ยกเลิกข้อนี้ (X)" tabindex="-1"><i class="bi bi-slash-circle"></i></button></div>';
    }
    grid.innerHTML = h;
    var filled = key.filter(function (k, i) { return k || cancelled[i + 1]; }).length, nc = Object.keys(cancelled).length;
    document.getElementById('keyProgress').textContent = 'ใส่แล้ว ' + filled + '/' + n + ' ข้อ' + (nc ? ' · ยกเลิก ' + nc + ' ข้อ' : '');
    var st = document.getElementById('keyState');
    st.textContent = dirty ? '● ยังไม่บันทึก' : '';
    st.className = 'small ms-auto ' + (dirty ? 'text-warning' : '');
    tryScore();
  }
  function change() { dirty = true; render(); }
  function set(i, c) {
    if (i < 0 || i >= n) return;
    var s = String(c);
    if (multi.checked) key[i] = key[i].indexOf(s) > -1 ? key[i].replace(s, '') : (key[i] + s).split('').sort().join('');
    else key[i] = key[i] === s ? '' : s;
    delete cancelled[i + 1];
    change();
  }
  function move(d) { cur = Math.max(0, Math.min(n - 1, cur + d)); render(); var el = grid.querySelector('.cur'); if (el) el.scrollIntoView({ block: 'nearest' }); }

  grid.addEventListener('click', function (e) {
    var row = e.target.closest('.kg-row'); if (!row) return;
    cur = Number(row.dataset.i);
    var b = e.target.closest('[data-c]'), x = e.target.closest('[data-x]');
    if (b) set(cur, b.dataset.c);
    else if (x) { if (cancelled[cur + 1]) delete cancelled[cur + 1]; else cancelled[cur + 1] = true; change(); }
    else render();
    grid.focus();
  });

  // แป้นไทย: ก ข ค ง พิมพ์ตรงได้เลย · ตัวเลขแถวบนของแป้นไทยไม่ใช่ 1–4 จึงดูที่ปุ่มจริง (e.code) ด้วย
  var CODE = { Digit1: 1, Digit2: 2, Digit3: 3, Digit4: 4, Numpad1: 1, Numpad2: 2, Numpad3: 3, Numpad4: 4 };
  grid.addEventListener('keydown', function (e) {
    if (e.ctrlKey || e.metaKey || e.altKey) return;
    var c = CODE[e.code] || { 'ก': 1, 'ข': 2, 'ค': 3, 'ง': 4 }[e.key];
    if (c) { e.preventDefault(); if (multi.checked) { set(cur, c); } else { key[cur] = String(c); delete cancelled[cur + 1]; dirty = true; move(1); } return; }
    if (e.key === 'x' || e.key === 'X' || e.code === 'KeyX') { e.preventDefault(); if (cancelled[cur + 1]) delete cancelled[cur + 1]; else cancelled[cur + 1] = true; dirty = true; move(1); return; }
    if (e.key === 'Backspace' || e.key === 'Delete') { e.preventDefault(); key[cur] = ''; delete cancelled[cur + 1]; dirty = true; if (e.key === 'Backspace') move(-1); else render(); return; }
    var cols = Math.max(1, Math.round(grid.clientWidth / 190));
    var d = { ArrowDown: 1, ArrowUp: -1, ArrowRight: Math.ceil(n / cols), ArrowLeft: -Math.ceil(n / cols), Enter: 1 }[e.key];
    if (d) { e.preventDefault(); move(d); }
  });
  multi.addEventListener('change', function () { grid.focus(); });

  /** วางเฉลย: แถว KEY ของ EVANA/Excel (คั่นด้วยแท็บ = 1 ช่องต่อข้อ รองรับ "24") · 2333112… · ข ค ค ค ก */
  document.getElementById('keyPasteBtn').addEventListener('click', function () {
    var raw = document.getElementById('keyPaste').value.trim();
    if (!raw) { SG.toast('วางข้อความเฉลยก่อน', true); return; }
    var line = raw.split(/\r?\n/).filter(function (l) { return /key/i.test(l); })[0] || raw.split(/\r?\n/)[0];
    line = SG.normalize(line.replace(/key/ig, ''));
    var cells = /[\t,;]/.test(line) ? line.split(/[\t,;]/).map(function (s) { return s.replace(/[^1-4]/g, ''); })
      : /\s/.test(line.trim()) ? line.trim().split(/\s+/).map(function (s) { return s.replace(/[^1-4]/g, ''); })
        : line.replace(/[^1-4]/g, '').split('');
    // แถวจาก Excel มักมีช่องแรกเป็นชื่อแถว (ว่างหลังตัด KEY) — ตัดช่องว่างนำหน้าออก
    while (cells.length > n && cells[0] === '') cells.shift();
    if (!cells.filter(Boolean).length) { SG.toast('ไม่พบตัวเลือก 1–4 หรือ ก–ง ในข้อความ', true); return; }
    for (var i = 0; i < n; i++) { key[i] = (cells[i] || '').split('').filter(function (c, j, a) { return a.indexOf(c) === j; }).sort().join(''); if (key[i]) delete cancelled[i + 1]; }
    if (key.some(function (k) { return k.length > 1; })) multi.checked = true;
    change();
    SG.toast('ใส่เฉลย ' + Math.min(cells.length, n) + ' ข้อ' + (cells.length > n ? ' (ตัดส่วนเกิน ' + (cells.length - n) + ' ข้อ)' : cells.length < n ? ' (ขาด ' + (n - cells.length) + ' ข้อ)' : '') + ' — ตรวจแล้วกดบันทึก');
  });

  function exam() {
    return {
      n_items: n, key: key, cancelled: Object.keys(cancelled).map(Number),
      cancel_mode: document.getElementById('cancelMode').value, points: Number(document.getElementById('keyPoints').value) || 1
    };
  }

  var tryIn = document.getElementById('tryAnswers'), tryOut = document.getElementById('tryResult');
  function tryScore() {
    var a = SG.normalize(tryIn.value).replace(/[^0-9]/g, '').slice(0, n);
    if (!a) { tryOut.innerHTML = 'พิมพ์คำตอบแล้วดูคะแนนและถูก/ผิดรายข้อ (ยังไม่บันทึก)'; return; }
    var r = SG.score(exam(), a), cells = '';
    for (var i = 0; i < a.length; i++) {
      var m = r.marks[i];
      cells += '<span class="' + (m === 'c' ? 'c' : m ? 'y' : 'n') + '">' + (i + 1) + '<b>' + (CH[a.charAt(i) - 1] || '–') + '</b></span>';
    }
    tryOut.innerHTML = '<div class="fs-5 fw-bold text-body mb-1">' + r.score + ' <small class="text-muted fw-normal">/ ' + r.max + '</small></div><div class="r-grid sm">' + cells + '</div>';
  }
  tryIn.addEventListener('input', tryScore);
  ['keyPoints', 'cancelMode'].forEach(function (id) { document.getElementById(id).addEventListener('change', change); });

  var saveBtn = document.getElementById('keySave');
  saveBtn.addEventListener('click', function () {
    saveBtn.disabled = true;
    SG.post(card.dataset.url, exam()).then(function (d) {
      dirty = false; render(); SG.toast(d.message);
    }).catch(function (e) { SG.toast(e.message, true); }).then(function () { saveBtn.disabled = false; });
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  render();
  grid.focus();
})();
