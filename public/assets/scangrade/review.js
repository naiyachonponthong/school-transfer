/* หน้าตรวจทาน: วาดวงทับภาพดัดตรง (พิกัด มม. จาก sheet-layout.js) · แตะวงหรือช่องคำตอบเพื่อแก้ · คะแนนเปลี่ยนทันที */
(function () {
  'use strict';
  var box = document.getElementById('sheet');
  if (!box) return;
  var SG = window.SG, CH = SG.CH, NS = 'http://www.w3.org/2000/svg';
  var ex = {
    n_items: Number(box.dataset.n), key: JSON.parse(box.dataset.key), cancelled: JSON.parse(box.dataset.cancelled),
    cancel_mode: box.dataset.cancelMode, points: Number(box.dataset.points)
  };
  var groups = JSON.parse(box.dataset.groups);
  var L = window.SheetLayout.layout(ex.n_items, groups);
  var input = document.getElementById('answers');
  var answers = input.value.split('');
  var flagged = {}; // ข้อที่ตัวอ่านไม่แน่ใจ (ตอบซ้อน/อ่านไม่ชัด) — ไฮไลต์ให้ดูก่อน
  JSON.parse(box.dataset.flagged || '[]').forEach(function (no) { flagged[no] = true; });

  // ไม่มีภาพ → วาดแผ่นเปล่าเป็นพื้นหลังแทน
  var blank = document.getElementById('blankSheet');
  if (blank) blank.innerHTML = window.SGSheet.svg({ n: ex.n_items, groups: groups });

  var svg = document.createElementNS(NS, 'svg');
  svg.setAttribute('viewBox', '0 0 210 297');
  svg.setAttribute('preserveAspectRatio', 'none');
  box.appendChild(svg);

  function el(tag, attrs) { var e = document.createElementNS(NS, tag); Object.keys(attrs).forEach(function (k) { e.setAttribute(k, attrs[k]); }); return e; }
  function draw() {
    svg.innerHTML = '';
    var r = L.answer.r;
    L.answer.items.forEach(function (it, i) {
      var key = String(ex.key[i] || '');
      if (flagged[it.no]) svg.appendChild(el('rect', { x: it.labelX - 6, y: it.y - L.answer.pitch / 2, width: it.xs[3] - it.labelX + 10, height: L.answer.pitch, fill: 'rgba(250,204,21,.28)', rx: 1 }));
      it.xs.forEach(function (x, c) {
        var ch = String(c + 1), chosen = answers[i] === ch;
        if (key.indexOf(ch) > -1) svg.appendChild(el('circle', { cx: x, cy: it.y, r: r + 0.9, fill: 'none', stroke: '#16a34a', 'stroke-width': 0.45 }));
        var dot = el('circle', { cx: x, cy: it.y, r: r + 0.2, class: 'ov', fill: chosen ? 'rgba(37,99,235,.45)' : 'rgba(0,0,0,0)', stroke: chosen ? '#2563eb' : 'none', 'stroke-width': 0.4 });
        dot.addEventListener('click', function () { answers[i] = answers[i] === ch ? '0' : ch; sync(); });
        svg.appendChild(dot);
      });
      if (answers[i] === '9') { var t = el('text', { x: it.xs[3] + 3.2, y: it.y + 1.2, 'font-size': 3.4, fill: '#b45309' }); t.textContent = '✱'; svg.appendChild(t); }
    });
  }
  function grid() {
    var sc = SG.score(ex, answers.join('')), h = '';
    answers.forEach(function (a, i) {
      var m = sc.marks[i], cls = a === '9' ? 'm' : m === 'c' ? 'c' : m === 1 ? 'y' : ex.key[i] ? 'n' : '';
      h += '<span class="' + cls + '" data-i="' + i + '" role="button">' + (i + 1) + '<b>' + (a >= '1' && a <= '4' ? CH[a - 1] : a === '9' ? '✱' : '–') + '</b></span>';
    });
    document.getElementById('ansGrid').innerHTML = h;
    document.getElementById('liveScore').textContent = sc.score;
    document.getElementById('liveMax').textContent = sc.max;
  }
  function sync() { input.value = answers.join(''); draw(); grid(); }
  document.getElementById('ansGrid').addEventListener('click', function (e) {
    var s = e.target.closest('[data-i]'); if (!s) return;
    var i = Number(s.dataset.i), a = answers[i];
    answers[i] = a >= '1' && a <= '3' ? String(Number(a) + 1) : a === '4' ? '0' : '1'; // ก→ข→ค→ง→ว่าง (ตอบซ้อน/ว่าง → ก)
    sync();
  });

  // กรองรายชื่อเจ้าของแผ่น
  var filter = document.getElementById('stuFilter'), sel = document.getElementById('stuSelect');
  filter.addEventListener('input', function () {
    var q = filter.value.trim().toLowerCase(), first = null;
    Array.prototype.forEach.call(sel.options, function (o) {
      var on = !q || o.text.toLowerCase().indexOf(q) > -1 || (/^\d+$/.test(q) && o.text.indexOf(String(Number(q))) > -1);
      o.hidden = !on; if (on && !first) first = o;
    });
    if (first && (!sel.selectedOptions[0] || sel.selectedOptions[0].hidden)) sel.value = first.value;
  });
  sync();
})();
