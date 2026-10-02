/* สำเนาจาก ScanGrade lib/evana.js โดยไม่แก้ไข — แก้ที่ต้นฉบับแล้วคัดลอกมาใหม่ (กระดาษที่พิมพ์ไปแล้วต้องอ่านได้เหมือนเดิม) */
/**
 * evana.js — คำนวณวิเคราะห์ข้อสอบแบบโปรแกรม EVANA (เทคนิค 25% และ 27% ตาราง Chung-Teh Fan)
 * ใช้ได้ทั้งฝั่ง GAS (Code.gs) และ browser — ไม่มี dependency
 *
 * ตรวจสอบแล้วกับผลจริงของ EVANA:
 *   - 25%: Delta ตรงตัวอย่างในเอกสาร Evana ทุกค่า
 *   - 27%: ตัวเลขตรง 80/80 แถว ของ ค31202 เทอม 2/2568 (p ±.01, r ±.02, Delta ±.1 = ความละเอียดของตาราง Fan)
 *          คำวิจารณ์ตรง 78/80 — 2 แถวที่ต่างคือค่าที่อยู่บนรอยตัดพอดี (เราได้ .395/.397 → ปัดเป็น .40, ตาราง EVANA พิมพ์ .39)
 *   - ขนาดกลุ่ม 27% = round(N × .27) → 66 คน = 18 ตรงผลจริง
 *   - หมายเหตุ: คอลัมน์ PH/PL ในไฟล์ .txt ของ EVANA แสดงค่าค้างจากแถวก่อนเมื่อ H หรือ L = 0 (บั๊กของโปรแกรมเดิม)
 *     แต่ p/r/Delta คำนวณจากค่าจริง — ระบบนี้แสดง PH/PL ที่ถูกต้อง
 */
var Evana = (function () {
  'use strict';

  // ---------- สถิติพื้นฐาน ----------
  function phi(x) { return Math.exp(-x * x / 2) / Math.sqrt(2 * Math.PI); }

  function Phi(x) { // CDF ปกติมาตรฐาน (erfc, แม่น ~1e-7)
    var z = Math.abs(x) / Math.SQRT2, t = 1 / (1 + 0.5 * z);
    var r = t * Math.exp(-z * z - 1.26551223 + t * (1.00002368 + t * (0.37409196 + t * (0.09678418 +
      t * (-0.18628806 + t * (0.27886807 + t * (-1.13520398 + t * (1.48851587 +
      t * (-0.82215223 + t * 0.17087277)))))))));
    return x >= 0 ? 1 - r / 2 : r / 2;
  }

  function PhiInv(p) { // Acklam
    var a = [-39.69683028665376, 220.9460984245205, -275.9285104469687, 138.3577518672690, -30.66479806614716, 2.506628277459239],
        b = [-54.47609879822406, 161.5858368580409, -155.6989798598866, 66.80131188771972, -13.28068155288572],
        c = [-0.007784894002430293, -0.3223964580411365, -2.400758277161838, -2.549732539343734, 4.374664141464968, 2.938163982698783],
        d = [0.007784695709041462, 0.3224671290700398, 2.445134137142996, 3.754408661907416];
    var q, r;
    if (p < 0.02425) { q = Math.sqrt(-2 * Math.log(p));
      return (((((c[0]*q+c[1])*q+c[2])*q+c[3])*q+c[4])*q+c[5]) / ((((d[0]*q+d[1])*q+d[2])*q+d[3])*q+1); }
    if (p > 1 - 0.02425) { q = Math.sqrt(-2 * Math.log(1 - p));
      return -(((((c[0]*q+c[1])*q+c[2])*q+c[3])*q+c[4])*q+c[5]) / ((((d[0]*q+d[1])*q+d[2])*q+d[3])*q+1); }
    q = p - 0.5; r = q * q;
    return (((((a[0]*r+a[1])*r+a[2])*r+a[3])*r+a[4])*r+a[5]) * q / (((((b[0]*r+b[1])*r+b[2])*r+b[3])*r+b[4])*r+1);
  }

  /** P(X > h, Y > k) ของปกติสองตัวแปร สหสัมพันธ์ r — อัลกอริทึม BVNU ของ Alan Genz */
  function bvnu(h, k, r) {
    var w, x;
    if (Math.abs(r) < 0.3) {
      w = [0.1713244923791705, 0.3607615730481384, 0.4679139345726904];
      x = [0.9324695142031522, 0.6612093864662647, 0.2386191860831970];
    } else if (Math.abs(r) < 0.75) {
      w = [0.04717533638651177, 0.1069393259953183, 0.1600783285433464, 0.2031674267230659, 0.2334925365383547, 0.2491470458134029];
      x = [0.9815606342467191, 0.9041172563704750, 0.7699026741943050, 0.5873179542866171, 0.3678314989981802, 0.1252334085114692];
    } else {
      w = [0.01761400713915212, 0.04060142980038694, 0.06267204833410906, 0.08327674157670475, 0.1019301198172404,
           0.1181945319615184, 0.1316886384491766, 0.1420961093183821, 0.1491729864726037, 0.1527533871307259];
      x = [0.9931285991850949, 0.9639719272779138, 0.9122344282513259, 0.8391169718222188, 0.7463319064601508,
           0.6360536807265150, 0.5108670019508271, 0.3737060887154196, 0.2277858511416451, 0.07652652113349733];
    }
    var tp = 2 * Math.PI, hk = h * k, bvn = 0, i, is, hs, asr, sn, a, b, bs, c, d, as, xs, rs, sp, ep;
    if (Math.abs(r) < 0.925) {
      hs = (h * h + k * k) / 2; asr = Math.asin(r);
      for (i = 0; i < w.length; i++) for (is = -1; is <= 1; is += 2) {
        sn = Math.sin(asr * (is * x[i] + 1) / 2);
        bvn += w[i] * Math.exp((sn * hk - hs) / (1 - sn * sn));
      }
      return bvn * asr / (2 * tp) + Phi(-h) * Phi(-k);
    }
    if (r < 0) { k = -k; hk = -hk; }
    if (Math.abs(r) < 1) {
      as = (1 - r) * (1 + r); a = Math.sqrt(as); bs = (h - k) * (h - k);
      c = (4 - hk) / 8; d = (12 - hk) / 16; asr = -(bs / as + hk) / 2;
      if (asr > -100) bvn = a * Math.exp(asr) * (1 - c * (bs - as) * (1 - d * bs / 5) / 3 + c * d * as * as / 5);
      if (-hk < 100) { b = Math.sqrt(bs); sp = Math.sqrt(tp) * Phi(-b / a);
        bvn -= Math.exp(-hk / 2) * sp * b * (1 - c * bs * (1 - d * bs / 5) / 3); }
      a = a / 2;
      for (i = 0; i < w.length; i++) for (is = -1; is <= 1; is += 2) {
        xs = a * (is * x[i] + 1); xs = xs * xs; rs = Math.sqrt(1 - xs); asr = -(bs / xs + hk) / 2;
        if (asr > -100) { sp = 1 + c * xs * (1 + d * xs); ep = Math.exp(-hk * (1 - rs) / (2 * (1 + rs))) / rs;
          bvn += a * w[i] * Math.exp(asr) * (ep - sp); }
      }
      bvn = -bvn / tp;
    }
    if (r > 0) bvn += Phi(-Math.max(h, k));
    else { bvn = -bvn; if (k > h) bvn += (h < 0 ? Phi(k) - Phi(h) : Phi(-h) - Phi(-k)); }
    return bvn;
  }

  function delta(p) { // ความยากมาตรฐาน Delta = 13 + 4Z, Z = Φ⁻¹(1−p)
    var q = Math.min(Math.max(1 - p, 0.0001), 0.9999);
    return 13 + 4 * PhiInv(q);
  }

  // ---------- เทคนิค 27%: ตาราง Chung-Teh Fan (คำนวณตรงจากโมเดลที่ใช้สร้างตาราง) ----------
  var K27 = 0.6128; // z ที่ตัดหาง 27%
  var T27 = 0.27;

  /** รับสัดส่วนตอบในกลุ่มสูง pH และกลุ่มต่ำ pL คืน {p, r, delta} แบบตาราง Fan */
  function fan(pH, pL) {
    var sign = 1;
    if (pH < pL) { var t = pH; pH = pL; pL = t; sign = -1; }
    // ขอบตาราง (ถอดจากผล EVANA): ค่าที่มากกว่าไม่ต่ำกว่า .10, ทั้งคู่อยู่ใน .01–.99
    pH = Math.round(pH * 100) / 100; pL = Math.round(pL * 100) / 100; // เปิดตารางด้วยสัดส่วน 2 ตำแหน่ง
    pH = Math.min(Math.max(pH, 0.10), 0.99);
    pL = Math.min(Math.max(pL, 0.01), 0.99);
    if (Math.abs(pH - pL) < 1e-9) { var pp = pH; return { p: pp, r: 0, delta: delta(pp) }; }
    function F(c, z) { // คืน [ส่วนต่างกลุ่มสูง, ส่วนต่างกลุ่มต่ำ]
      var rho = Math.tanh(z);
      return [bvnu(c, K27, rho) / T27 - pH, bvnu(c, K27, -rho) / T27 - pL]; // P(Y>c, X<−k) = P(Y>c, −X>k), corr = −ρ
    }
    var c = PhiInv(1 - (pH + pL) / 2), z = 0.3, e = 1e-5;
    for (var it = 0; it < 60; it++) {
      var f = F(c, z), fc = F(c + e, z), fz = F(c, z + e);
      var j11 = (fc[0] - f[0]) / e, j12 = (fz[0] - f[0]) / e, j21 = (fc[1] - f[1]) / e, j22 = (fz[1] - f[1]) / e;
      var det = j11 * j22 - j12 * j21; if (Math.abs(det) < 1e-14) break;
      var dc = (f[0] * j22 - f[1] * j12) / det, dz = (j11 * f[1] - j21 * f[0]) / det;
      var s = Math.max(Math.abs(dc), Math.abs(dz)); if (s > 1) { dc /= s; dz /= s; } // step limit
      c -= dc; z -= dz;
      if (Math.abs(dc) < 1e-9 && Math.abs(dz) < 1e-9) break;
    }
    return { p: 1 - Phi(c), r: sign * Math.tanh(z), delta: 13 + 4 * c };
  }

  // ---------- คำวิจารณ์ (ถอดจากผล EVANA) ----------
  var LEVEL = {
    p: [[0.80, 'ง่ายมาก'], [0.60, 'ค่อนข้างง่าย'], [0.40, 'ยากง่ายปานกลาง'], [0.20, 'ค่อนข้างยาก'], [-1, 'ยากมาก']],
    r: [[0.40, 'อำนาจจำแนกดีมาก'], [0.30, 'อำนาจจำแนกดี'], [0.20, 'อำนาจจำแนกพอใช้ได้']]
  };
  function r2(x) { return Math.round(x * 100) / 100; } // ตัดสินจากค่าที่แสดง 2 ตำแหน่ง เหมือน EVANA
  function comment(isKey, H, L, p, r) {
    p = r2(p); r = r2(r);
    if (!isKey) {
      if (H === 0 && L === 0) return 'ไม่ดี ไม่มีคนเลือก';
      if (r > 0) return 'ดี คนอ่อนหลงตอบมากกว่า';
      if (r < 0) return 'ไม่ดี คนเก่งหลงตอบมากกว่า';
      return 'ไม่ดี ไม่มีอำนาจจำแนก';
    }
    var lp = LEVEL.p.filter(function (l) { return p >= l[0]; })[0][1], lr;
    if (r < 0) lr = 'ไม่ดี คนเก่งหลงทำผิด';
    else if (r === 0) lr = 'ไม่มีอำนาจจำแนก';
    else { var hit = LEVEL.r.filter(function (l) { return r >= l[0]; })[0]; lr = hit ? hit[1] : 'อำนาจจำแนกไม่ดี'; }
    return lp + '  ' + lr;
  }

  // ---------- วิเคราะห์ทั้งฉบับ ----------
  /**
   * @param {Object} o
   *   key       : สตริงเฉลย "2333112..." หรือ array ['2','3','24',...] ('24' = ถูกได้หลายตัวเลือก)
   *   cancelled : [เลขข้อที่ยกเลิก] — ไม่นำมาวิเคราะห์และไม่นับในคะแนนรวม (เหมือนตัดข้อออกจากฉบับ)
   *   nChoices  : จำนวนตัวเลือก (4)
   *   students  : [{id:'1ก401', answers:'2341...'}]  เรียงตามเลขที่ (ใช้ตัดสินตอนคะแนนเท่ากันที่รอยตัด)
   *               answers: '1'..'4' · '0' = ว่าง · '9' = ตอบซ้อน
   *   technique : 25 | 27
   *   criteria  : { pMin:.20, pMax:.80, rMin:.20 } (ไม่ใส่ = ค่าเริ่มต้น)
   * @return {items:[...], summary:{...}, scores:[...], quality:{good,revise,drop}}
   */
  function analyze(o) {
    var keyArr = typeof o.key === 'string' ? o.key.split('') : (o.key || []).map(function (k) { return String(k || ''); });
    var n = keyArr.length, m = o.nChoices || 4, tech = o.technique === 25 ? 25 : 27;
    var canc = {}; (o.cancelled || []).forEach(function (q) { canc[Number(q)] = 1; });
    var isOk = function (i, ch) { return ch >= '1' && ch <= '9' && keyArr[i] && keyArr[i].indexOf(ch) > -1; };
    var live = []; for (var q = 0; q < n; q++) if (!canc[q + 1] && keyArr[q]) live.push(q);
    var st = o.students.map(function (s, idx) {
      var a = String(s.answers), sc = 0;
      live.forEach(function (i) { if (isOk(i, a.charAt(i))) sc++; });
      return { id: s.id, answers: a, score: sc, order: idx };
    });
    var N = st.length;
    // เรียงคะแนนมาก→น้อย, คะแนนเท่ากันใช้ลำดับเดิม (เลขที่)
    var sorted = st.slice().sort(function (a, b) { return b.score - a.score || a.order - b.order; });
    var g = Math.max(1, Math.round(N * tech / 100)); // ขนาดกลุ่ม (27% ของ 66 คน = 18 ตรงผล EVANA)
    var hi = sorted.slice(0, g), lo = sorted.slice(Math.max(0, N - g));
    var items = [];
    for (var i = 0; i < n; i++) {
      if (canc[i + 1] || !keyArr[i]) { items.push({ no: i + 1, key: keyArr[i], cancelled: !!canc[i + 1], nokey: !keyArr[i], options: [], p: null, r: null }); continue; }
      var opts = [];
      for (var c = 1; c <= m; c++) {
        var ch = String(c), H = 0, L = 0;
        hi.forEach(function (s) { if (s.answers.charAt(i) === ch) H++; });
        lo.forEach(function (s) { if (s.answers.charAt(i) === ch) L++; });
        opts.push(stat_(tech, g, keyArr[i].indexOf(ch) > -1, H, L, c));
      }
      // ค่าประจำข้อ = ค่าของตัวถูก (ถ้าถูกหลายตัวเลือก นับ "ตอบถูก" รวมกัน)
      var item = { no: i + 1, key: keyArr[i], options: opts };
      var keys = opts.filter(function (x) { return x.isKey; });
      if (keys.length === 1) { item.p = keys[0].p; item.r = keys[0].r; item.delta = keys[0].delta; item.comment = keys[0].comment; }
      else {
        var cH = 0, cL = 0;
        hi.forEach(function (s) { if (isOk(i, s.answers.charAt(i))) cH++; });
        lo.forEach(function (s) { if (isOk(i, s.answers.charAt(i))) cL++; });
        var comb = stat_(tech, g, true, cH, cL, 0);
        item.p = comb.p; item.r = comb.r; item.delta = comb.delta; item.comment = comb.comment;
      }
      items.push(item);
    }
    // KR-20 ใช้ผู้สอบทุกคน · K = จำนวนข้อที่วิเคราะห์
    var K = live.length;
    var mean = N ? st.reduce(function (a, s) { return a + s.score; }, 0) / N : 0;
    var vr = N ? st.reduce(function (a, s) { return a + Math.pow(s.score - mean, 2); }, 0) / N : 0;
    var spq = 0;
    live.forEach(function (i) {
      var pc = st.filter(function (s) { return isOk(i, s.answers.charAt(i)); }).length / (N || 1);
      spq += pc * (1 - pc);
    });
    var kr20 = K > 1 && vr > 0 ? (K / (K - 1)) * (1 - spq / vr) : 0;
    var kr21 = K > 1 && vr > 0 ? (K / (K - 1)) * (1 - mean * (K - mean) / (K * vr)) : 0;
    var sd = Math.sqrt(vr);
    var res = {
      technique: tech, groupSize: g,
      items: items,
      summary: { nItems: K, nTotal: n, nCancelled: n - K, nPapers: N, mean: mean, sd: sd, kr20: kr20, kr21: kr21, sem: sd * Math.sqrt(Math.max(0, 1 - kr20)),
        max: N ? Math.max.apply(null, st.map(function (s) { return s.score; })) : 0, min: N ? Math.min.apply(null, st.map(function (s) { return s.score; })) : 0 },
      scores: st.map(function (s) { return { id: s.id, score: s.score }; })
    };
    res.quality = classify(res, o.criteria);
    return res;
  }

  /** ค่าสถิติของตัวเลือก 1 ตัว */
  function stat_(tech, g, isKey, H, L, c) {
    var pH = H / g, pL = L / g, p, r, d;
    if (tech === 25) {
      p = (H + L) / (2 * g);
      r = (isKey ? (H - L) : (L - H)) / g;
      d = delta(p);
    } else {
      var f = isKey ? fan(pH, pL) : fan(pL, pH); // ตัวลวง: สลับให้ r เป็นบวกเมื่อกลุ่มต่ำเลือกมากกว่า
      p = f.p; r = f.r; d = f.delta;
      if (H === 0 && L === 0) { p = 0; r = 0; d = delta(0); } // ไม่มีใครเลือก
    }
    return { choice: c, isKey: isKey, H: H, L: L, pH: pH, pL: pL, p: p, r: r, delta: d, comment: comment(isKey, H, L, p, r) };
  }

  /**
   * สรุปคุณภาพรายข้อ (ใช้ p, r ของตัวถูก)
   *   ใช้ได้      : p อยู่ในช่วง และ r ≥ rMin และตัวลวงทำงานทุกตัว
   *   ควรปรับปรุง : r ≥ rMin แต่ p นอกช่วง หรือมีตัวลวงที่ไม่ดี (ไม่มีคนเลือก / คนเก่งเลือกมากกว่า)
   *   ควรตัดทิ้ง   : r < rMin
   */
  function classify(res, crit) {
    crit = crit || {};
    var pMin = crit.pMin === undefined ? 0.2 : crit.pMin, pMax = crit.pMax === undefined ? 0.8 : crit.pMax, rMin = crit.rMin === undefined ? 0.2 : crit.rMin;
    var CH = ['ก', 'ข', 'ค', 'ง', 'จ', 'ฉ', 'ช', 'ซ', 'ฌ'];
    var out = { good: [], revise: [], drop: [], criteria: { pMin: pMin, pMax: pMax, rMin: rMin } };
    res.items.forEach(function (it) {
      if (it.cancelled || it.nokey) { it.quality = 'skip'; it.notes = [it.cancelled ? 'ยกเลิกข้อนี้' : 'ไม่มีเฉลย']; return; }
      var p = r2(it.p), r = r2(it.r), notes = [];
      var badD = it.options.filter(function (x) { return !x.isKey && (x.H + x.L === 0 || r2(x.r) < 0); });
      if (p < pMin) notes.push('ยากเกินไป (p ' + fmt2(p) + ')');
      if (p > pMax) notes.push('ง่ายเกินไป (p ' + fmt2(p) + ')');
      if (r < rMin) notes.push('อำนาจจำแนกต่ำ (r ' + fmt2(r) + ')');
      badD.forEach(function (x) { notes.push('ตัวลวง ' + CH[x.choice - 1] + (x.H + x.L === 0 ? ' ไม่มีคนเลือก' : ' คนเก่งเลือกมากกว่า')); });
      it.notes = notes;
      if (r < rMin) { it.quality = 'drop'; out.drop.push(it.no); }
      else if (p < pMin || p > pMax || badD.length) { it.quality = 'revise'; out.revise.push(it.no); }
      else { it.quality = 'good'; out.good.push(it.no); }
    });
    return out;
  }

  /** ตัวเลขแบบ EVANA: .61  -.06  1.00 */
  function fmt2(v) {
    if (v === null || v === undefined || isNaN(v)) return '';
    var x = Math.round(v * 100) / 100, s = Math.abs(x).toFixed(2);
    if (s.charAt(0) === '0') s = s.slice(1);
    return (x < 0 ? '-' : '') + s;
  }
  function pad(s, w, right) { s = String(s); var len = thLen(s); var sp = new Array(Math.max(0, w - len) + 1).join(' '); return right ? sp + s : s + sp; }
  /** ความกว้างที่เห็นจริงของข้อความไทย (สระบน/ล่าง/วรรณยุกต์ไม่กินที่) */
  function thLen(s) { return String(s).replace(/[ัิ-ฺ็-๎]/g, '').length; }

  /**
   * ผลวิเคราะห์เป็นข้อความรูปแบบเดียวกับไฟล์ .txt ของโปรแกรม EVANA + ส่วนสรุปทั้งฉบับ
   * meta = { subject_code, subject_name, term, year, teacher, school, n_choices }
   */
  function toTxt(res, meta) {
    meta = meta || {};
    var CH = ['ก', 'ข', 'ค', 'ง', 'จ', 'ฉ', 'ช', 'ซ', 'ฌ'];
    var line = new Array(125).join('-'), L = [], is27 = res.technique === 27;
    L.push('     การวิเคราะห์ข้อสอบรายข้อ  ' + (is27 ? 'โดยใช้ตาราง CHUNG TEH FAN กลุ่มสูง กลุ่มต่ำ 27 %' : 'โดยใช้สูตรอย่างง่าย กลุ่มสูง กลุ่มต่ำ 25 %'));
    L.push('    วิชา ' + (meta.subject_code || '') + '  ' + (meta.subject_name || '') + '   เทอม ' + (meta.term || '') + '/' + (meta.year || '') + '    อาจารย์ผู้สอน : ' + (meta.teacher || ''));
    L.push(line);
    L.push(is27 ? '  ข้อ ตัวเลือก  H     L    PH    PL      p      r     Delta          วิจารณ์' : '  ข้อ ตัวเลือก  H     L      p      r     Delta          วิจารณ์');
    L.push(line);
    res.items.forEach(function (it) {
      if (!it.options.length) { L.push(pad(it.no, 5, true) + '     ' + (it.cancelled ? '(ยกเลิกข้อนี้ — ไม่นำมาวิเคราะห์)' : '(ไม่มีเฉลย)')); L.push(line); return; }
      var mid = Math.floor((it.options.length - 1) / 2);
      it.options.forEach(function (x, k) {
        var s = (k === mid ? pad(it.no, 5, true) : '     ') + '     ' + (x.isKey ? '*' : ' ') + CH[x.choice - 1] + '  ' + pad(x.H, 4, true) + '   ' + pad(x.L, 4, true);
        if (is27) s += '  ' + pad(fmt2(x.pH), 4, true) + '  ' + pad(fmt2(x.pL), 4, true);
        s += '   ' + pad(fmt2(x.p), 4, true) + '   ' + pad(fmt2(x.r), 4, true) + '   ' + pad((Math.round(x.delta * 10) / 10).toFixed(1), 4, true) + '    ' + x.comment;
        L.push(s);
      });
      L.push(line);
    });
    var S = res.summary, q = res.quality, f = function (v, d) { return (Math.round(v * Math.pow(10, d)) / Math.pow(10, d)).toFixed(d); };
    L.push('');
    L.push('     ผลการวิเคราะห์ทั้งฉบับ');
    L.push(line);
    L.push('     จำนวนข้อสอบที่วิเคราะห์      ' + S.nItems + (S.nCancelled ? '  (ยกเลิก ' + S.nCancelled + ' ข้อ)' : ''));
    L.push('     จำนวนกระดาษคำตอบ            ' + S.nPapers + '   (กลุ่มสูง/กลุ่มต่ำ กลุ่มละ ' + res.groupSize + ' คน)');
    L.push('     คะแนนเฉลี่ย                   ' + f(S.mean, 2) + '   สูงสุด ' + S.max + '   ต่ำสุด ' + S.min);
    L.push('     ส่วนเบี่ยงเบนมาตรฐาน          ' + f(S.sd, 4));
    L.push('     ค่าความเชื่อมั่น (KR-20)       ' + f(S.kr20, 4) + '     (KR-21 ' + f(S.kr21, 4) + ')');
    L.push('     ความคลาดเคลื่อนมาตรฐาน (SEM)  ' + f(S.sem, 4));
    L.push(line);
    L.push('     สรุปคุณภาพของข้อสอบ  (เกณฑ์: p ' + fmt2(q.criteria.pMin) + '–' + fmt2(q.criteria.pMax) + ', r ≥ ' + fmt2(q.criteria.rMin) + ')');
    L.push('     ข้อสอบที่ใช้ได้      ' + pad(q.good.length, 3, true) + ' ข้อ : ' + (q.good.join(', ') || '-'));
    L.push('     ข้อสอบที่ควรปรับปรุง ' + pad(q.revise.length, 3, true) + ' ข้อ : ' + (q.revise.join(', ') || '-'));
    L.push('     ข้อสอบที่ควรตัดทิ้ง   ' + pad(q.drop.length, 3, true) + ' ข้อ : ' + (q.drop.join(', ') || '-'));
    L.push(line);
    if (meta.school) L.push('     สถานศึกษา ' + meta.school);
    return L.join('\r\n');
  }

  return { analyze: analyze, classify: classify, toTxt: toTxt, fmt2: fmt2, fan: fan, delta: delta, comment: comment, Phi: Phi, PhiInv: PhiInv, bvnu: bvnu };
})();

if (typeof module !== 'undefined') module.exports = Evana;
