/* ตั้งค่าฟอร์มรับสมัคร: แก้คำถามในหน้าเดียว → ตอนบันทึกรวมเป็น JSON ส่งไปที่ AdmissionFormController (ฝั่ง server ทำความสะอาดซ้ำ) */
(function () {
  'use strict';
  var form = document.getElementById('builderForm');
  if (!form) return;
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var esc = function (s) { return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var C = JSON.parse(form.dataset.config), TYPES = JSON.parse(form.dataset.types);
  var SENSITIVE = JSON.parse(form.dataset.sensitive), ANSWERED = JSON.parse(form.dataset.answered), MAX = Number(form.dataset.max);
  var CHOICE = ['select', 'radio', 'checkbox'];
  var dirty = false;
  var list = $('#qList');

  function uid() { var s = 'q_', a = 'abcdefghijkmnpqrstuvwxyz23456789'; for (var i = 0; i < 8; i++) s += a[Math.floor(Math.random() * a.length)]; return s; }
  function levels() { return $('#levelsInput').value.split(',').map(function (s) { return s.trim(); }).filter(Boolean).filter(function (l, i, a) { return a.indexOf(l) === i; }); }
  function markDirty() { dirty = true; $('#dirtyState').innerHTML = '<span class="text-warning"><i class="bi bi-dot"></i>ยังไม่บันทึก</span>'; }
  function blank(type) { return { id: uid(), type: type, label: '', help: '', required: false, options: CHOICE.indexOf(type) > -1 ? ['ตัวเลือก 1', 'ตัวเลือก 2'] : [], levels: [], step: type === 'file' ? 'documents' : 'extra', photo: false }; }

  /* ---------------- การเปิดรับ ---------------- */
  var STEPS = { student: 'ข้อมูลผู้สมัคร', education: 'การศึกษาเดิม', family: 'ผู้ปกครองและที่อยู่', extra: 'ข้อมูลเพิ่มเติม', documents: 'เอกสารแนบ' };
  $('#feeNote').value = C.fee_note || '';
  $('#pledge').value = C.pledge || '';
  $('#examSlip').checked = C.exam_slip !== false;
  ['feeNote', 'pledge'].forEach(function (id) { $('#' + id).addEventListener('input', markDirty); });
  $('#examSlip').addEventListener('change', markDirty);
  $('#openFrom').value = C.open_from || '';
  $('#openUntil').value = C.open_until || '';
  $('#intro').value = C.intro || '';
  function renderCaps() {
    $('#capsBox').innerHTML = levels().map(function (l) {
      return '<div class="input-group input-group-sm" style="width:150px"><span class="input-group-text">' + esc(l) + '</span>' +
        '<input type="number" min="1" max="100000" class="form-control" data-cap="' + esc(l) + '" value="' + (C.caps[l] || '') + '" placeholder="ไม่จำกัด"></div>';
    }).join('') || '<span class="small text-muted">ใส่ระดับชั้นก่อน</span>';
  }
  function renderFees() {
    $('#feesBox').innerHTML = levels().map(function (l) {
      return '<div class="input-group input-group-sm" style="width:170px"><span class="input-group-text">' + esc(l) + '</span>' +
        '<input type="number" min="0" max="100000" step="1" class="form-control" data-fee="' + esc(l) + '" value="' + (C.fees[l] || '') + '" placeholder="ฟรี"><span class="input-group-text">บาท</span></div>';
    }).join('') || '<span class="small text-muted">ใส่ระดับชั้นก่อน</span>';
  }
  $('#feesBox').addEventListener('input', function (e) { var l = e.target.dataset.fee; if (l === undefined) return; if (Number(e.target.value) > 0) C.fees[l] = Number(e.target.value); else delete C.fees[l]; markDirty(); });
  $('#capsBox').addEventListener('input', function (e) { var l = e.target.dataset.cap; if (l === undefined) return; if (Number(e.target.value) > 0) C.caps[l] = Number(e.target.value); else delete C.caps[l]; markDirty(); });
  $('#levelsInput').addEventListener('input', function () { renderCaps(); renderFees(); renderList(); renderPreviewLevels(); markDirty(); });
  ['openFrom', 'openUntil', 'intro'].forEach(function (id) { $('#' + id).addEventListener('input', markDirty); });
  form.querySelectorAll('[data-opt]').forEach(function (r) { r.addEventListener('change', function () { C.optional[r.dataset.opt] = r.value; markDirty(); }); });
  form.querySelector('[name="admission_open"]').addEventListener('change', markDirty);

  /* ---------------- รายการคำถาม ---------------- */
  function sensitive(label) { return SENSITIVE.filter(function (w) { return label.indexOf(w) > -1; }); }
  function card(q, i) {
    var n = C.questions.length, isChoice = CHOICE.indexOf(q.type) > -1, lv = levels(), sens = sensitive(q.label), answered = ANSWERED[q.id] || 0;
    return '<div class="q-card" data-i="' + i + '">' +
      '<div class="d-flex flex-wrap align-items-center gap-2 mb-2">' +
      '<span class="q-no">' + (i + 1) + '</span>' +
      '<select class="form-select form-select-sm w-auto" data-f="type" aria-label="ชนิดคำถาม">' + Object.keys(TYPES).map(function (t) { return '<option value="' + t + '"' + (t === q.type ? ' selected' : '') + '>' + TYPES[t][0] + '</option>'; }).join('') + '</select>' +
      '<select class="form-select form-select-sm w-auto" data-f="step" aria-label="อยู่ในขั้น" title="แสดงในขั้นไหนของฟอร์ม">' + Object.keys(STEPS).map(function (k) { return '<option value="' + k + '"' + (k === q.step ? ' selected' : '') + '>ขั้น: ' + STEPS[k] + '</option>'; }).join('') + '</select>' +
      '<label class="form-check form-switch small mb-0 ms-1"><input type="checkbox" class="form-check-input" data-f="required"' + (q.required ? ' checked' : '') + '> บังคับตอบ</label>' +
      (q.type === 'file' ? '<label class="form-check small mb-0" title="พิมพ์รูปนี้ลงช่องรูปถ่ายในใบสมัครและใบมอบตัว"><input type="checkbox" class="form-check-input" data-f="photo"' + (q.photo ? ' checked' : '') + '> ใช้เป็นรูปถ่ายในเอกสาร</label>' : '') +
      (answered ? '<span class="badge bg-info-subtle text-info-emphasis" title="ใบสมัครที่ส่งแล้วยังเก็บคำตอบไว้ แม้แก้/ลบคำถามนี้">มีคนตอบแล้ว ' + answered + ' ใบ</span>' : '') +
      '<div class="ms-auto btn-group btn-group-sm">' +
      '<button type="button" class="btn btn-light border" data-act="up" title="เลื่อนขึ้น"' + (i ? '' : ' disabled') + '><i class="bi bi-arrow-up"></i></button>' +
      '<button type="button" class="btn btn-light border" data-act="down" title="เลื่อนลง"' + (i < n - 1 ? '' : ' disabled') + '><i class="bi bi-arrow-down"></i></button>' +
      '<button type="button" class="btn btn-light border" data-act="copy" title="ทำซ้ำ"' + (n < MAX ? '' : ' disabled') + '><i class="bi bi-copy"></i></button>' +
      '<button type="button" class="btn btn-light border text-danger" data-act="del" title="ลบ"><i class="bi bi-trash"></i></button></div></div>' +
      '<input class="form-control mb-2 fw-semibold' + (q.label.trim() ? '' : ' is-invalid') + '" data-f="label" maxlength="200" placeholder="คำถาม เช่น แผนการเรียนที่ต้องการ" value="' + esc(q.label) + '">' +
      '<input class="form-control form-control-sm mb-2" data-f="help" maxlength="300" placeholder="คำอธิบายใต้ช่อง (ไม่บังคับ)" value="' + esc(q.help) + '">' +
      (isChoice ? '<label class="small text-muted mb-1">ตัวเลือก — บรรทัดละ 1 ตัวเลือก</label><textarea class="form-control form-control-sm mb-2" rows="' + Math.max(2, q.options.length) + '" data-f="options">' + esc(q.options.join('\n')) + '</textarea>' : '') +
      '<div class="d-flex flex-wrap align-items-center gap-2 small"><span class="text-muted">ใช้กับชั้น:</span>' +
      lv.map(function (l) { return '<label class="form-check form-check-inline mb-0"><input type="checkbox" class="form-check-input" data-level="' + esc(l) + '"' + (q.levels.indexOf(l) > -1 ? ' checked' : '') + '> ' + esc(l) + '</label>'; }).join('') +
      '<span class="text-muted">' + (q.levels.length ? '' : '(ไม่เลือก = ทุกชั้น)') + '</span></div>' +
      (sens.length ? '<div class="alert alert-warning py-1 px-2 small mt-2 mb-0"><i class="bi bi-shield-exclamation"></i> คำถามนี้อาจเป็น<b>ข้อมูลอ่อนไหว</b>ตาม PDPA (' + esc(sens.join(', ')) + ') — ถามเฉพาะเมื่อจำเป็นจริง และบอกเหตุผลในคำอธิบาย</div>' : '') +
      '</div>';
  }
  function renderList() {
    list.innerHTML = C.questions.length ? C.questions.map(card).join('') : '<div class="empty py-4"><i class="bi bi-ui-checks"></i>ยังไม่มีคำถามเพิ่มเติม — ฟอร์มจะมีเฉพาะช่องหลักและช่องเสริม</div>';
    $('#qCount').textContent = C.questions.length + '/' + MAX + ' ข้อ';
    renderPreview();
  }
  function add(q) {
    if (C.questions.length >= MAX) { alert('เพิ่มได้สูงสุด ' + MAX + ' คำถาม'); return; }
    C.questions.push(q); renderList(); markDirty();
    var last = list.lastElementChild; if (last) { last.scrollIntoView({ block: 'center', behavior: 'smooth' }); var inp = $('[data-f="label"]', last); if (inp && !q.label) inp.focus(); }
  }

  list.addEventListener('input', function (e) {
    var c = e.target.closest('.q-card'); if (!c) return;
    var q = C.questions[Number(c.dataset.i)], f = e.target.dataset.f;
    if (f === 'label') { q.label = e.target.value; e.target.classList.toggle('is-invalid', !q.label.trim()); }
    else if (f === 'help') q.help = e.target.value;
    else if (f === 'options') q.options = e.target.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
    else return;
    markDirty(); renderPreview();
  });
  // คำเตือน PDPA อัปเดตตอนพิมพ์คำถามเสร็จ (ไม่วาดใหม่ระหว่างพิมพ์ ช่องจะไม่หลุดโฟกัส)
  list.addEventListener('change', function (e) {
    var c = e.target.closest('.q-card'); if (!c) return;
    var i = Number(c.dataset.i), q = C.questions[i], f = e.target.dataset.f;
    if (f === 'type') {
      q.type = e.target.value;
      if (CHOICE.indexOf(q.type) > -1 && !q.options.length) q.options = ['ตัวเลือก 1', 'ตัวเลือก 2'];
      if (q.type !== 'file') q.photo = false;
      if (q.type === 'file' && q.step === 'extra') q.step = 'documents';
    }
    else if (f === 'required') q.required = e.target.checked;
    else if (f === 'step') q.step = e.target.value;
    else if (f === 'photo') { C.questions.forEach(function (x) { x.photo = false; }); q.photo = e.target.checked; markDirty(); renderList(); return; } // รูปถ่ายมีได้ข้อเดียว
    else if (e.target.dataset.level !== undefined) {
      var l = e.target.dataset.level;
      q.levels = e.target.checked ? q.levels.concat([l]) : q.levels.filter(function (x) { return x !== l; });
    } else if (f !== 'label') return;
    markDirty();
    list.children[i].outerHTML = card(q, i);
    renderPreview();
  });
  list.addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) return;
    var i = Number(b.closest('.q-card').dataset.i), Q = C.questions, act = b.dataset.act;
    if (act === 'up' && i > 0) Q.splice(i - 1, 0, Q.splice(i, 1)[0]);
    else if (act === 'down' && i < Q.length - 1) Q.splice(i + 1, 0, Q.splice(i, 1)[0]);
    else if (act === 'copy') { var cp = JSON.parse(JSON.stringify(Q[i])); cp.id = uid(); cp.label += ' (สำเนา)'; Q.splice(i + 1, 0, cp); }
    else if (act === 'del') {
      var warn = ANSWERED[Q[i].id] ? '\n(ใบสมัครที่ส่งแล้ว ' + ANSWERED[Q[i].id] + ' ใบยังเก็บคำตอบของคำถามนี้ไว้)' : '';
      if (!confirm('ลบคำถาม "' + (Q[i].label || 'ไม่มีชื่อ') + '"?' + warn)) return;
      Q.splice(i, 1);
    }
    markDirty(); renderList();
  });

  $('#addMenu').innerHTML = Object.keys(TYPES).map(function (t) { return '<li><button type="button" class="dropdown-item" data-type="' + t + '"><i class="bi ' + TYPES[t][1] + ' me-2"></i>' + TYPES[t][0] + '</button></li>'; }).join('');
  $('#addMenu').addEventListener('click', function (e) { var b = e.target.closest('[data-type]'); if (b) add(blank(b.dataset.type)); });

  // คำถามที่โรงเรียนมักใช้ — กดแล้วได้คำถามพร้อมตัวเลือก แก้ต่อได้
  var TEMPLATES = [
    ['รูปถ่ายนักเรียน', { type: 'file', label: 'รูปถ่ายนักเรียน (หน้าตรง)', help: 'รูปถ่ายหน้าตรง ชุดนักเรียน (JPG/PNG)', required: true, photo: true }],
    ['สัญชาติ', { type: 'text', label: 'สัญชาติ', step: 'student' }],
    ['สำเนาทะเบียนบ้าน', { type: 'file', label: 'สำเนาทะเบียนบ้าน', help: '' }],
    ['แผนการเรียน', { type: 'radio', label: 'แผนการเรียนที่ต้องการ', options: ['วิทยาศาสตร์-คณิตศาสตร์', 'ภาษาอังกฤษ-คณิตศาสตร์', 'ภาษาอังกฤษ-ภาษาจีน', 'ทั่วไป'], required: true, levelHint: 'ม.4' }],
    ['ความสามารถพิเศษ', { type: 'textarea', label: 'ความสามารถพิเศษ / รางวัลที่เคยได้รับ' }],
    ['อาชีพผู้ปกครอง', { type: 'text', label: 'อาชีพผู้ปกครอง' }],
    ['ช่องทางที่รู้จักโรงเรียน', { type: 'checkbox', label: 'ทราบข่าวการรับสมัครจาก', options: ['เพจ/เว็บไซต์โรงเรียน', 'ครูโรงเรียนเดิม', 'เพื่อน/ญาติ', 'ป้ายประชาสัมพันธ์'] }]
  ];
  $('#templates').innerHTML = TEMPLATES.map(function (t, i) { return '<button type="button" class="btn btn-sm btn-light border py-0" data-tpl="' + i + '">+ ' + esc(t[0]) + '</button>'; }).join('');
  $('#templates').addEventListener('click', function (e) {
    var b = e.target.closest('[data-tpl]'); if (!b) return;
    var t = TEMPLATES[Number(b.dataset.tpl)][1], q = Object.assign(blank(t.type), JSON.parse(JSON.stringify(t)));
    q.levels = t.levelHint && levels().indexOf(t.levelHint) > -1 ? [t.levelHint] : [];
    delete q.levelHint;
    add(q);
  });

  /* ---------------- ตัวอย่าง (เหมือนที่ผู้ปกครองเห็น) ---------------- */
  function renderPreviewLevels() {
    var sel = $('#previewLevel'), cur = sel.value;
    sel.innerHTML = levels().map(function (l) { return '<option' + (l === cur ? ' selected' : '') + '>' + esc(l) + '</option>'; }).join('');
    renderPreview();
  }
  function renderPreview() {
    var lv = $('#previewLevel').value;
    var qs = C.questions.filter(function (q) { return q.label.trim() && (!q.levels.length || q.levels.indexOf(lv) > -1); });
    var lastStep = null, order = Object.keys(STEPS);
    $('#preview').innerHTML = qs.length ? qs.slice().sort(function (a, b) { return order.indexOf(a.step) - order.indexOf(b.step); }).map(function (q) {
      var head = q.step !== lastStep ? '<div class="small fw-semibold text-primary mt-2 mb-1">' + esc(STEPS[q.step] || '') + '</div>' : '';
      lastStep = q.step;
      var star = q.required ? ' <span class="text-danger">*</span>' : '', input;
      if (q.type === 'textarea') input = '<textarea class="form-control form-control-sm" rows="2" disabled></textarea>';
      else if (q.type === 'select') input = '<select class="form-select form-select-sm" disabled><option>- เลือก -</option></select>';
      else if (q.type === 'radio' || q.type === 'checkbox') input = q.options.map(function (o) { return '<label class="form-check small"><input type="' + q.type + '" class="form-check-input" disabled> ' + esc(o) + '</label>'; }).join('');
      else input = '<input class="form-control form-control-sm" disabled placeholder="' + esc({ number: '123', date: 'วว/ดด/ปปปป', file: 'เลือกไฟล์ (รูป/PDF)' }[q.type] || '') + '">';
      return head + '<div class="mb-3"><div class="form-label small mb-1">' + esc(q.label) + star + (q.photo ? ' <span class="badge bg-info-subtle text-info-emphasis">รูปในเอกสาร</span>' : '') + '</div>' + input + (q.help ? '<div class="form-text">' + esc(q.help) + '</div>' : '') + '</div>';
    }).join('') : '<div class="small text-muted">ชั้น ' + esc(lv || '-') + ' ไม่มีคำถามเพิ่มเติม</div>';
  }
  $('#previewLevel').addEventListener('change', renderPreview);

  /* ---------------- บันทึก ---------------- */
  form.addEventListener('submit', function (e) {
    var bad = C.questions.findIndex(function (q) { return !q.label.trim() || (CHOICE.indexOf(q.type) > -1 && !q.options.length); });
    if (bad > -1) {
      e.preventDefault();
      alert('คำถามข้อ ' + (bad + 1) + ' ยังไม่มีข้อความคำถาม หรือยังไม่มีตัวเลือก');
      list.children[bad].scrollIntoView({ block: 'center' });
      return;
    }
    $('#configInput').value = JSON.stringify({
      intro: $('#intro').value, open_from: $('#openFrom').value || null, open_until: $('#openUntil').value || null,
      caps: C.caps, fees: C.fees, fee_note: $('#feeNote').value, pledge: $('#pledge').value, exam_slip: $('#examSlip').checked,
      optional: C.optional, questions: C.questions
    });
    dirty = false;
  });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  if (Array.isArray(C.caps)) C.caps = {}; // PHP ส่ง [] มาเมื่อยังไม่มีค่า
  if (Array.isArray(C.fees)) C.fees = {};
  renderCaps(); renderFees(); renderList(); renderPreviewLevels();
})();
