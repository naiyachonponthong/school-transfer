/* =====================================================================
 * หน้าสแกนกระดาษคำตอบ — ย้ายมาจาก ScanGrade scanner/app.js
 * กล้องสด → หา 4 มุม (OMR.detect) → นิ่ง 3 เฟรม = ถ่ายเอง → อ่านทั้งแผ่น (OMR.scan) → แสดงผล + คะแนนทันที
 * → เก็บลงคิวในเครื่อง (IndexedDB) → ส่งเข้าระบบทีละ 10 แผ่นเมื่อมีเน็ต
 * ต่างจากเดิม: ใช้ session ของระบบ (ไม่ต้องตั้งลิงก์/ล็อกอินซ้ำ) และสแกนจากรูปหลายรูปได้ในหน้าเดียวกัน
 * ===================================================================== */
(function () {
  'use strict';
  var $ = function (s) { return document.querySelector(s); };
  var SG = window.SG, CH = SG.CH, esc = SG.esc;
  var app = $('#app');
  var R = JSON.parse($('#rosterData').textContent);
  var EXAM_ID = Number(app.dataset.examId);
  var S = { exam: R.exam, groups: R.groups, byCode: {}, scanned: {}, session: 0 };

  var LS = {
    get: function (k) { try { return localStorage.getItem('sg_' + k) || ''; } catch (e) { return ''; } },
    set: function (k, v) { try { localStorage.setItem('sg_' + k, v); } catch (e) { /* โหมดส่วนตัว */ } }
  };
  function loadRoster(d) {
    R = d; S.exam = d.exam; S.groups = d.groups || S.groups; S.byCode = {}; S.scanned = {};
    d.students.forEach(function (s) { S.byCode[s.code] = s; });
    (d.scanned || []).forEach(function (id) { S.scanned[id] = 1; });
    LS.set('roster_' + EXAM_ID, JSON.stringify(d));
  }
  loadRoster(R);
  var sens = $('#sens');
  sens.value = LS.get('sens') || '0.30';
  sens.addEventListener('change', function () { LS.set('sens', sens.value); });
  function omrOpts(extra) { return Object.assign({ n: S.exam.n_items, groups: S.groups, min: Number(sens.value) || 0.3, margin: 0.14 }, extra || {}); }
  function normCode(c) { var s = String(c || '').replace(/\D/g, ''); return s ? String(Number(s)) : ''; }
  function uuid() {
    if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) { var r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); });
  }
  var screens = ['scrStart', 'scrScan', 'scrPhotos'];
  function show(id) { screens.forEach(function (s) { $('#' + s).classList.toggle('hidden', s !== id); }); window.scrollTo(0, 0); }

  /* ---------------- คิวในเครื่อง (IndexedDB · ใช้หน่วยความจำแทนถ้าเปิดไม่ได้) ---------------- */
  var DB = (function () {
    var dbp = null, mem = {};
    function open() {
      if (dbp) return dbp;
      dbp = new Promise(function (res) {
        try {
          var rq = indexedDB.open('school-scangrade', 1);
          rq.onupgradeneeded = function () { rq.result.createObjectStore('queue', { keyPath: 'request_id' }); };
          rq.onsuccess = function () { res(rq.result); };
          rq.onerror = function () { res(null); };
        } catch (e) { res(null); }
      });
      return dbp;
    }
    function tx(mode, fn) {
      return open().then(function (db) {
        if (!db) return fn(null);
        return new Promise(function (res, rej) {
          var t = db.transaction('queue', mode), out = fn(t.objectStore('queue'));
          t.oncomplete = function () { res(out && typeof out === 'object' && 'readyState' in out ? out.result : out); };
          t.onerror = function () { rej(t.error); };
        });
      });
    }
    return {
      put: function (it) { return tx('readwrite', function (st) { if (!st) { mem[it.request_id] = it; return; } st.put(it); }); },
      all: function () {
        return tx('readonly', function (st) { return st ? st.getAll() : Object.keys(mem).map(function (k) { return mem[k]; }); })
          .then(function (r) { return (r || []).filter(function (x) { return x.exam_id === EXAM_ID; }).sort(function (a, b) { return String(b.scanned_at).localeCompare(String(a.scanned_at)); }); });
      },
      del: function (id) { return tx('readwrite', function (st) { if (!st) { delete mem[id]; return; } st.delete(id); }); }
    };
  })();

  /* ---------------- ตัดสินผล 1 แผ่น (ใช้ร่วมกันทั้งกล้องและรูป) ---------------- */
  function judge(r) {
    var ex = S.exam, st = S.byCode[normCode(r.code)] || null, flags = [];
    if (r.n_mismatch) flags.push(['bad', 'แผ่นนี้เป็นแบบ ' + r.n + ' ข้อ แต่ชุดข้อสอบมี ' + ex.n_items + ' ข้อ']);
    if (!st) flags.push(['bad', /[?*]/.test(r.code) ? 'ระบายเลขประจำตัวไม่ครบ/ซ้อน' : 'ไม่พบเลขประจำตัว ' + r.code + ' ในห้องที่สอบ']);
    var seatNo = /^(\d+)/.exec(r.seat || '');
    if (st && seatNo && st.seat && Number(seatNo[1]) !== Number(st.seat)) flags.push(['warn', 'เลขที่บนกระดาษ ' + seatNo[1] + ' ไม่ตรงกับรายชื่อ (' + st.seat + ')']);
    if (st && S.scanned[st.id]) flags.push(['warn', 'คนนี้สแกนไปแล้ว']);
    var pick = function (p) { return r.flags.filter(function (f) { return f.indexOf(p) === 0; }).map(function (f) { return f.slice(p.length); }); };
    var multi = pick('multi:'), blank = pick('blank:'), low = pick('low_conf:');
    if (r.flags.indexOf('warp') > -1) flags.push(['bad', 'กระดาษโค้ง/ไม่เรียบ — วางให้เรียบแล้วสแกนใหม่']);
    if (multi.length) flags.push(['warn', 'ตอบซ้อน ข้อ ' + multi.join(', ')]);
    if (low.length) flags.push(['warn', 'อ่านไม่ชัด ข้อ ' + low.join(', ')]);
    if (blank.length) flags.push(['mute', 'ไม่ตอบ ' + blank.length + ' ข้อ']);
    // อ่านไม่ได้หลายข้อ มักเป็นเพราะแสงน้อย (ดินสอดูจางลง) ไม่ใช่นักเรียนไม่ตอบ
    if (blank.length + low.length >= Math.max(3, Math.ceil(ex.n_items * 0.2))) flags.push(['warn', 'หลายข้ออ่านไม่ได้ — ถ้านักเรียนระบายครบ แสงอาจน้อยไป ลองเปิดไฟฉาย/ย้ายที่สว่าง หรือปรับ "ความไว"']);
    return {
      st: st, flags: flags, sc: ex.key_ready ? SG.score(ex, r.answers) : null,
      review: r.review || !st || flags.some(function (f) { return f[0] !== 'mute'; })
    };
  }
  function gridHtml(answers, sc) {
    var h = '', marks = sc ? sc.marks : [];
    for (var i = 0; i < S.exam.n_items; i++) {
      var a = answers.charAt(i), cls = a === '9' ? 'm' : marks[i] === 'c' ? 'c' : marks[i] === 1 ? 'y' : sc ? 'n' : '';
      h += '<span class="' + cls + '">' + (i + 1) + '<b>' + (a >= '1' && a <= '4' ? CH[a - 1] : a === '9' ? '✱' : '–') + '</b></span>';
    }
    return h;
  }
  function flagsHtml(flags) {
    return flags.length ? '<div class="r-flags">' + flags.map(function (f) { return '<span class="pill pill-' + ({ bad: 'bad', warn: 'warn' }[f[0]] || 'mute') + '">' + esc(f[1]) + '</span>'; }).join('') + '</div>' : '';
  }
  function makeItem(r, j, codeOverride, source) {
    var code = codeOverride ? ('00000' + codeOverride.replace(/\D/g, '')).slice(-5) : r.code;
    var st = codeOverride ? S.byCode[normCode(code)] || null : j.st;
    return {
      request_id: uuid(), exam_id: EXAM_ID, student_code: code, seat: r.seat, answers: r.answers, confidence: r.confidence,
      flags: r.flags.concat(r.n_mismatch ? ['n_mismatch'] : []),
      // ครูแก้เลขประจำตัวแล้วเจอชื่อ และตัวอ่านเองไม่ติดอะไร → ไม่ต้องตรวจทาน
      review: codeOverride && st && !r.review ? false : j.review,
      image: r.image, scanned_at: new Date().toISOString(), source: source,
      status: 'pending', local: { name: st ? st.name : '', seat: st ? st.seat : r.seat, score: j.sc ? j.sc.score : null }
    };
  }
  function enqueue(item) {
    return DB.put(item).then(function () {
      var st = S.byCode[normCode(item.student_code)];
      if (st) S.scanned[st.id] = 1;
      updateStats(); sync();
    });
  }

  /* ---------------- ส่งเข้าระบบ ---------------- */
  var syncing = false;
  function sync() {
    if (syncing || !navigator.onLine) return Promise.resolve();
    syncing = true;
    return DB.all().then(function (all) {
      var pend = all.filter(function (x) { return x.status === 'pending'; }).slice(0, 10);
      if (!pend.length) return null;
      var payload = pend.map(function (x) { var y = Object.assign({}, x); delete y.local; delete y.status; delete y.server; delete y.exam_id; return y; });
      return SG.post(app.dataset.submitUrl, { items: payload }, 45000).then(function (d) {
        var byId = {}; d.results.forEach(function (r) { byId[r.request_id] = r; });
        return Promise.all(pend.map(function (x) {
          var r = byId[x.request_id]; if (!r) return null;
          x.status = 'sent'; x.server = r; x.image = ''; // ส่งแล้วลบรูปออกจากเครื่อง
          return DB.put(x);
        })).then(function () { return pend.length === 10 ? 'more' : null; });
      }).catch(function (e) {
        // ข้อมูลผิดรูปแบบ (422) ส่งซ้ำก็ไม่ผ่าน → ทำเครื่องหมายไว้ให้เห็น ไม่วนส่งไม่รู้จบ
        if (/ข้อ|answers|คำตอบ/.test(e.message)) return Promise.all(pend.map(function (x) { x.status = 'failed'; x.server = { message: e.message }; return DB.put(x); }));
        SG.toast('ยังส่งไม่ได้: ' + e.message + ' (เก็บไว้ในเครื่องแล้ว)', true);
        return null;
      });
    }).then(function (more) { syncing = false; updateStats(); if (more) return sync(); },
      function () { syncing = false; updateStats(); });
  }
  setInterval(sync, 8000);
  window.addEventListener('online', sync);
  $('#btnSync').addEventListener('click', function () { sync().then(function () { SG.toast('ส่งแล้ว'); }); });

  function updateStats() {
    return DB.all().then(function (all) {
      var c = { pending: 0, sent: 0, review: 0, failed: 0 };
      all.forEach(function (x) {
        if (x.status === 'pending') c.pending++;
        else if (x.status === 'failed') c.failed++;
        else { c.sent++; if (x.server && x.server.status === 'review') c.review++; }
      });
      $('#scPending').textContent = c.pending;
      $('#stats').innerHTML = '<div><b>' + c.pending + '</b><small>รอส่ง</small></div><div><b>' + c.sent + '</b><small>ส่งแล้ว</small></div><div><b>' + c.review + '</b><small>ต้องตรวจทาน</small></div>' + (c.failed ? '<div><b>' + c.failed + '</b><small>ผิดพลาด</small></div>' : '');
      $('#queue').innerHTML = all.length ? all.slice(0, 100).map(function (x) {
        var sv = x.server || {}, st = sv.student || {};
        var pill = x.status === 'pending' ? '<span class="pill pill-mute">รอส่ง</span>'
          : x.status === 'failed' ? '<span class="pill pill-bad">' + esc(sv.message || 'ผิดพลาด') + '</span>'
            : sv.status === 'review' ? '<span class="pill pill-warn">ตรวจทาน</span>' : '<span class="pill pill-ok">สำเร็จ</span>';
        var score = sv.max ? sv.score + '/' + sv.max : x.local.score !== null ? String(x.local.score) : '';
        return '<div class="item"><div class="grow"><b>' + esc(st.name || x.local.name || ('รหัส ' + x.student_code)) + '</b><small>' +
          esc([st.room, 'เลขที่ ' + (st.seat || x.local.seat || '-'), new Date(x.scanned_at).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })].filter(Boolean).join(' · ')) +
          '</small></div><div class="sc">' + esc(score) + '<br>' + pill + '</div></div>';
      }).join('') : '<div class="empty">ยังไม่มีแผ่นที่สแกนในเครื่องนี้</div>';
      return all;
    });
  }

  /* ---------------- กล้อง ---------------- */
  var cam = { stream: null, track: null, raf: 0, last: 0, hist: [], paused: false, needClear: false, lostAt: 0, torch: false, lastCorners: null };
  var video = $('#video'), overlay = $('#overlay'), octx = overlay.getContext('2d');
  var small = $('#small'), sctx = small.getContext('2d', { willReadFrequently: true });
  var work = $('#work'), wctx = work.getContext('2d', { willReadFrequently: true });
  var hasCamera = !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
  if (!hasCamera) { $('#noCamera').classList.remove('hidden'); $('#btnCamera').disabled = true; }

  function setHint(t, good) { var h = $('#scHint'); h.textContent = t; h.classList.toggle('good', !!good); }
  function startCamera() {
    S.session = 0; $('#scCount').textContent = '0'; $('#result').classList.add('hidden');
    $('#scSub').textContent = S.exam.n_items + ' ข้อ · ' + R.students.length + ' คน · ตัวอ่าน v' + OMR.VERSION;
    show('scrScan');
    navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1440 } } })
      .then(function (st) {
        cam.stream = st; cam.track = st.getVideoTracks()[0]; video.srcObject = st;
        var caps = cam.track.getCapabilities ? cam.track.getCapabilities() : {};
        $('#scTorch').classList.toggle('hidden', !caps.torch);
        return video.play();
      })
      .then(function () { cam.paused = false; cam.hist = []; cam.needClear = false; loop(); })
      .catch(function (e) { setHint('เปิดกล้องไม่ได้: ' + (e.message || e.name), false); });
  }
  function stopCamera() {
    cancelAnimationFrame(cam.raf); cam.raf = 0;
    if (cam.stream) cam.stream.getTracks().forEach(function (t) { t.stop(); });
    cam.stream = null; cam.track = null; cam.torch = false; $('#scTorch').classList.remove('on');
  }
  $('#btnCamera').addEventListener('click', startCamera);
  $('#scBack').addEventListener('click', function () { stopCamera(); clearAuto(); show('scrStart'); updateStats(); });
  $('#scTorch').addEventListener('click', function () {
    if (!cam.track) return;
    cam.torch = !cam.torch;
    cam.track.applyConstraints({ advanced: [{ torch: cam.torch }] }).catch(function () { /* บางเครื่องไม่รองรับ */ });
    this.classList.toggle('on', cam.torch);
  });
  $('#scShutter').addEventListener('click', function () { if (!cam.paused) capture(null); });
  document.addEventListener('visibilitychange', function () { if (document.hidden && cam.stream) { stopCamera(); show('scrStart'); } });

  function toScreen(p, vw, vh) {
    var W = overlay.clientWidth, H = overlay.clientHeight, k = Math.min(W / vw, H / vh);
    return { x: (W - vw * k) / 2 + p.x * k, y: (H - vh * k) / 2 + p.y * k };
  }
  function drawCorners(q, vw, vh, good) {
    var W = overlay.clientWidth, H = overlay.clientHeight, dpr = window.devicePixelRatio || 1;
    if (overlay.width !== W * dpr) { overlay.width = W * dpr; overlay.height = H * dpr; }
    octx.setTransform(dpr, 0, 0, dpr, 0, 0); octx.clearRect(0, 0, W, H);
    if (!q) return;
    var pts = q.map(function (p) { return toScreen(p, vw, vh); }), col = good ? '#3ddc84' : '#ffd23f';
    octx.lineWidth = 3; octx.strokeStyle = col; octx.fillStyle = good ? 'rgba(61,220,132,.16)' : 'rgba(255,210,63,.12)';
    octx.beginPath(); pts.forEach(function (p, i) { if (i) octx.lineTo(p.x, p.y); else octx.moveTo(p.x, p.y); }); octx.closePath(); octx.fill(); octx.stroke();
    pts.forEach(function (p) { octx.beginPath(); octx.arc(p.x, p.y, 9, 0, 7); octx.fillStyle = col; octx.fill(); });
  }
  function moved(a, b, w) { var m = 0; for (var i = 0; i < 4; i++) m = Math.max(m, Math.hypot(a[i].x - b[i].x, a[i].y - b[i].y)); return m / w; }
  function loop() {
    cam.raf = requestAnimationFrame(loop);
    var now = performance.now();
    if (cam.paused || now - cam.last < 110 || video.readyState < 2) return;
    cam.last = now;
    var vw = video.videoWidth, vh = video.videoHeight;
    if (!vw) return;
    var sw = 480, sh = Math.round(vh * sw / vw);
    if (small.width !== sw) { small.width = sw; small.height = sh; }
    sctx.drawImage(video, 0, 0, sw, sh);
    var q = OMR.detect(OMR.fromImageData(sctx.getImageData(0, 0, sw, sh)), sw), k = vw / sw;
    var qv = q ? q.map(function (p) { return { x: p.x * k, y: p.y * k, s: p.s * k }; }) : null;
    if (!qv) {
      cam.hist = [];
      if (cam.needClear) { if (!cam.lostAt) cam.lostAt = now; if (now - cam.lostAt > 400) { cam.needClear = false; cam.lostAt = 0; } }
      drawCorners(null, vw, vh); setHint(cam.needClear ? 'เปลี่ยนแผ่นถัดไป' : 'วางกระดาษให้เห็นสี่เหลี่ยมดำครบ 4 มุม', false);
      return;
    }
    cam.lostAt = 0;
    if (cam.needClear) { // แผ่นเดิมยังอยู่ — รอจนเปลี่ยนแผ่น (มุมหายหรือขยับมาก)
      if (cam.lastCorners && moved(cam.lastCorners, qv, vw) > 0.12) cam.needClear = false;
      else { drawCorners(qv, vw, vh, false); setHint('เปลี่ยนแผ่นถัดไป', false); return; }
    }
    cam.hist.push(qv); if (cam.hist.length > 3) cam.hist.shift();
    var stable = cam.hist.length === 3 && moved(cam.hist[0], cam.hist[2], vw) < 0.02;
    drawCorners(qv, vw, vh, stable);
    setHint(stable ? 'กำลังอ่าน…' : 'ถือนิ่ง ๆ', stable);
    if (stable) capture(qv);
  }
  function capture(corners) {
    var vw = video.videoWidth, vh = video.videoHeight;
    if (!vw) return;
    cam.paused = true;
    work.width = vw; work.height = vh; wctx.drawImage(video, 0, 0, vw, vh);
    var fl = $('#flash'); fl.classList.add('go'); setTimeout(function () { fl.classList.remove('go'); }, 60);
    if (navigator.vibrate) navigator.vibrate(40);
    setTimeout(function () {
      var g = OMR.fromImageData(wctx.getImageData(0, 0, vw, vh));
      var r = OMR.scan(g, omrOpts({ corners: corners }));
      if (!r.ok) { SG.toast(r.message, true); cam.hist = []; cam.paused = false; return; }
      cam.lastCorners = corners || r.corners;
      r.image = makeImage(g, r.H, r.field);
      showResult(r);
    }, 30);
  }
  /** ภาพดัดตรงขนาดเล็ก (JPEG เทา) ไว้ให้ครูตรวจทานเทียบกับวงที่ระบบอ่าน */
  function makeImage(g, H, F) {
    try {
      var rg = OMR.rectify(g, H, 3.6, F), c = document.createElement('canvas');
      c.width = rg.w; c.height = rg.h;
      var ctx = c.getContext('2d'), img = ctx.createImageData(rg.w, rg.h);
      for (var i = 0, j = 0; i < rg.d.length; i++, j += 4) { img.data[j] = img.data[j + 1] = img.data[j + 2] = rg.d[i]; img.data[j + 3] = 255; }
      ctx.putImageData(img, 0, 0);
      return c.toDataURL('image/jpeg', 0.6);
    } catch (e) { return ''; }
  }

  var autoT = null;
  function clearAuto() { if (autoT) { cancelAnimationFrame(autoT); autoT = null; var b = $('#rBar'); if (b) b.parentNode.remove(); } }
  function showResult(r) {
    var j = judge(r), st = j.st, sc = j.sc, el = $('#result');
    el.innerHTML =
      '<div class="r-head"><div class="grow"><div class="r-name">' + (st ? esc(st.name) : 'ไม่ทราบชื่อ') + '</div>' +
      '<div class="r-meta">รหัส ' + esc(r.code) + (st ? ' · ' + esc(st.room) + ' เลขที่ ' + esc(st.seat) : r.seat ? ' · เลขที่ ' + esc(r.seat) : '') + '</div></div>' +
      '<div class="r-score">' + (sc ? '<b>' + sc.score + '</b><small>จาก ' + sc.max + '</small>' : '<small>ยังไม่มีเฉลย</small>') + '</div></div>' +
      flagsHtml(j.flags) +
      (!st ? '<div class="r-code"><span>แก้เลขประจำตัว</span><input id="rCode" inputmode="numeric" maxlength="6" value="' + esc(r.code.replace(/\D/g, '')) + '"><span id="rCodeName"></span></div>' : '') +
      '<div class="r-grid">' + gridHtml(r.answers, sc) + '</div>' +
      (j.review ? '<div class="r-review"><i class="bi bi-info-circle"></i> แผ่นนี้จะเข้า <b>คิวตรวจทาน</b> ให้ดูภาพเทียบอีกครั้ง</div>' : '') +
      '<div class="r-actions"><button class="btn" id="rRetry">สแกนใหม่</button><button class="btn btn-primary" id="rSave">บันทึก</button></div>' +
      (!j.review ? '<div class="countdown"><i id="rBar"></i></div>' : '');
    el.classList.remove('hidden');
    var codeIn = $('#rCode');
    if (codeIn) codeIn.addEventListener('input', function () {
      clearAuto();
      var s2 = S.byCode[normCode(codeIn.value)];
      $('#rCodeName').textContent = s2 ? '✓ ' + s2.name + ' (' + s2.room + ' เลขที่ ' + s2.seat + ')' : '';
    });
    var done = function (save) {
      clearAuto();
      if (save) {
        var item = makeItem(r, j, codeIn && codeIn.value.trim() ? codeIn.value : '', 'camera');
        enqueue(item).catch(function (e) { SG.toast('บันทึกในเครื่องไม่ได้: ' + e.message, true); });
        S.session++; $('#scCount').textContent = S.session;
        cam.needClear = true;
      }
      el.classList.add('hidden'); cam.hist = []; cam.paused = false;
    };
    $('#rRetry').addEventListener('click', function () { cam.needClear = false; done(false); });
    $('#rSave').addEventListener('click', function () { done(true); });
    el.addEventListener('pointerdown', function (e) { if (!e.target.closest('button')) clearAuto(); }, { once: true });
    if (!j.review) {
      var bar = $('#rBar'), t0 = performance.now(), dur = 1600;
      var tick = function () {
        if (!autoT) return;
        var p = (performance.now() - t0) / dur;
        bar.style.transform = 'scaleX(' + Math.max(0, 1 - p) + ')';
        if (p >= 1) { autoT = null; done(true); } else autoT = requestAnimationFrame(tick);
      };
      autoT = requestAnimationFrame(tick);
    }
  }

  /* ---------------- สแกนจากรูป (รูปจากมือถือ / ไฟล์จากเครื่องสแกนเอกสาร) ---------------- */
  var photos = [];
  function loadImage(file) {
    return new Promise(function (res, rej) {
      var url = URL.createObjectURL(file), img = new Image();
      img.onload = function () { URL.revokeObjectURL(url); res(img); };
      img.onerror = function () { URL.revokeObjectURL(url); rej(new Error('เปิดรูปไม่ได้')); };
      img.src = url;
    });
  }
  $('#photoInput').addEventListener('change', function () {
    var files = Array.prototype.slice.call(this.files || []);
    this.value = '';
    if (!files.length) return;
    photos = []; show('scrPhotos');
    $('#phSave').disabled = true;
    $('#phList').innerHTML = '<div class="empty">กำลังอ่าน 0/' + files.length + ' รูป…</div>';
    var i = 0;
    var next = function () {
      if (i >= files.length) { renderPhotos(); return; }
      var f = files[i++];
      $('#phList').innerHTML = '<div class="empty">กำลังอ่าน ' + i + '/' + files.length + ' รูป…<br><small>' + esc(f.name) + '</small></div>';
      loadImage(f).then(function (img) {
        // ย่อด้านยาวไม่เกิน 2400 px — ละเอียดพอสำหรับวงคำตอบ และไม่ช้าเกินบนมือถือ
        var k = Math.min(1, 2400 / Math.max(img.naturalWidth, img.naturalHeight));
        work.width = Math.round(img.naturalWidth * k); work.height = Math.round(img.naturalHeight * k);
        wctx.drawImage(img, 0, 0, work.width, work.height);
        var g = OMR.fromImageData(wctx.getImageData(0, 0, work.width, work.height));
        var r = OMR.scan(g, omrOpts());
        if (r.ok) { r.image = makeImage(g, r.H, r.field); photos.push({ file: f.name, r: r, j: judge(r) }); }
        else photos.push({ file: f.name, error: r.message });
      }).catch(function (e) { photos.push({ file: f.name, error: e.message }); })
        .then(function () { setTimeout(next, 10); }); // ให้หน้าจอได้วาดความคืบหน้า
    };
    next();
  });
  function renderPhotos() {
    var ok = photos.filter(function (p) { return !p.error; }), bad = photos.length - ok.length;
    var reviews = ok.filter(function (p) { return p.j.review; }).length;
    $('#phSub').textContent = 'อ่านได้ ' + ok.length + ' แผ่น' + (reviews ? ' · ต้องตรวจทาน ' + reviews : '') + (bad ? ' · อ่านไม่ได้ ' + bad + ' รูป' : '');
    $('#phList').innerHTML = photos.map(function (p) {
      if (p.error) return '<div class="photo-card"><span class="pill pill-bad">อ่านไม่ได้</span> <b>' + esc(p.file) + '</b><div class="sub">' + esc(p.error) + ' — ถ่ายใหม่ให้เห็นทั้งแผ่นและ 4 มุมชัด ๆ</div></div>';
      var st = p.j.st, sc = p.j.sc;
      return '<div class="photo-card"><div class="r-head"><div class="grow"><div class="r-name">' + (st ? esc(st.name) : 'ไม่ทราบชื่อ') + '</div><div class="r-meta">' +
        esc(p.file) + ' · รหัส ' + esc(p.r.code) + (st ? ' · ' + esc(st.room) + ' เลขที่ ' + esc(st.seat) : '') + '</div></div>' +
        '<div class="r-score">' + (sc ? '<b>' + sc.score + '</b><small>จาก ' + sc.max + '</small>' : '') + '</div></div>' + flagsHtml(p.j.flags) +
        '<details><summary class="sub">ดูคำตอบรายข้อ</summary><div class="r-grid">' + gridHtml(p.r.answers, sc) + '</div></details></div>';
    }).join('');
    $('#phSave').disabled = !ok.length;
    $('#phSave').innerHTML = '<i class="bi bi-save"></i> บันทึก ' + ok.length + ' แผ่น';
  }
  $('#phSave').addEventListener('click', function () {
    var btn = this; btn.disabled = true;
    Promise.all(photos.filter(function (p) { return !p.error; }).map(function (p) { return DB.put(makeItem(p.r, p.j, '', 'photo')); }))
      .then(function () { SG.toast('บันทึกแล้ว กำลังส่งเข้าระบบ…'); photos = []; show('scrStart'); return sync(); })
      .then(updateStats);
  });
  $('#phBack').addEventListener('click', function () { photos = []; show('scrStart'); });

  // รายชื่อ/เฉลยล่าสุด (เช่น เพิ่งบันทึกเฉลยในอีกแท็บ) — ออฟไลน์ใช้ที่เก็บไว้
  if (navigator.onLine) {
    fetch(app.dataset.rosterUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; }).then(function (d) { if (d) loadRoster(d); }).catch(function () { /* ใช้ข้อมูลในหน้า */ });
  }
  window.ScanApp = { S: S, DB: DB, sync: sync, judge: judge }; // สำหรับทดสอบ
  updateStats(); sync();
})();
