/* ตัวช่วยที่ใช้ร่วมกันในหน้าตรวจข้อสอบ (เฉลย / สแกน / ตรวจทาน / วิเคราะห์) */
(function () {
  'use strict';
  var SG = window.SG = window.SG || {};
  SG.CH = ['ก', 'ข', 'ค', 'ง'];

  /** ให้คะแนน 1 แผ่น — ตรรกะเดียวกับ Exam::score() ฝั่ง PHP · marks: 1 ถูก · 0 ผิด · 'c' ยกเลิก */
  SG.score = function (ex, answers) {
    var n = ex.n_items, pts = Number(ex.points) || 1, canc = {}, give = ex.cancel_mode !== 'drop', sc = 0, mx = 0, marks = [];
    (ex.cancelled || []).forEach(function (q) { canc[q] = 1; });
    for (var i = 0; i < n; i++) {
      if (canc[i + 1]) { marks.push('c'); if (give) { sc += pts; mx += pts; } continue; }
      mx += pts;
      var a = String(answers).charAt(i), k = String((ex.key || [])[i] || ''), ok = a >= '1' && a <= '4' && k.indexOf(a) > -1;
      marks.push(ok ? 1 : 0); if (ok) sc += pts;
    }
    return { score: Math.round(sc * 100) / 100, max: Math.round(mx * 100) / 100, marks: marks };
  };

  /** ก–ง / 1–4 → '1'..'4' (อื่น ๆ ทิ้ง) */
  SG.normalize = function (s) {
    return String(s || '').replace(/ก/g, '1').replace(/ข/g, '2').replace(/ค/g, '3').replace(/ง/g, '4');
  };

  SG.esc = function (s) {
    return String(s === null || s === undefined ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  };

  SG.csrf = function () { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.content : ''; };

  /** POST JSON → JSON (โยน Error พร้อมข้อความภาษาไทยจาก server) */
  SG.post = function (url, data, timeoutMs) {
    var ctrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = setTimeout(function () { if (ctrl) ctrl.abort(); }, timeoutMs || 20000);
    return fetch(url, {
      method: 'POST', credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined,
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': SG.csrf(), 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(data)
    }).then(function (r) {
      clearTimeout(timer);
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (r.status === 419 || r.status === 401) throw new Error('หมดเวลาใช้งาน — รีเฟรชหน้าแล้วเข้าสู่ระบบใหม่');
        if (!r.ok) {
          var first = j.errors ? j.errors[Object.keys(j.errors)[0]][0] : null;
          throw new Error(first || j.message || ('ผิดพลาด (' + r.status + ')'));
        }
        return j;
      });
    }, function (e) {
      clearTimeout(timer);
      throw new Error(e && e.name === 'AbortError' ? 'เซิร์ฟเวอร์ตอบช้า ลองใหม่อีกครั้ง' : 'เชื่อมต่อไม่ได้');
    });
  };

  var toastT;
  SG.toast = function (msg, bad) {
    var el = document.querySelector('.sg-toast');
    if (!el) { el = document.createElement('div'); el.className = 'sg-toast'; el.setAttribute('role', 'status'); document.body.appendChild(el); }
    el.textContent = msg; el.classList.toggle('bad', !!bad); el.classList.add('show');
    clearTimeout(toastT); toastT = setTimeout(function () { el.classList.remove('show'); }, bad ? 4200 : 2400);
  };
})();
