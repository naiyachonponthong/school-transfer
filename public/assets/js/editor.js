/*
 * ตัวแก้ไขข้อความแบบมีแถบเครื่องมือ (ไม่พึ่งไลบรารีภายนอก)
 * ใช้: <div data-rich-editor data-upload-url="..."><textarea name="body" hidden>...</textarea></div>
 * HTML ที่ได้จะถูกกรองอีกครั้งที่ฝั่งเซิร์ฟเวอร์ (App\Support\HtmlSanitizer)
 */
(function () {
  const BTN = (cmd, icon, title, extra = '') => `<button type="button" class="rte-btn" data-cmd="${cmd}" title="${title}" aria-label="${title}" ${extra}><i class="bi bi-${icon}"></i></button>`;
  const TXT = (cmd, text, title, arg = '') => `<button type="button" class="rte-btn rte-txt" data-cmd="${cmd}" data-arg="${arg}" title="${title}" aria-label="${title}">${text}</button>`;
  const SEP = '<span class="rte-sep"></span>';

  const toolbar = () => [
    BTN('bold', 'type-bold', 'ตัวหนา (Ctrl+B)'), BTN('italic', 'type-italic', 'ตัวเอียง (Ctrl+I)'),
    BTN('underline', 'type-underline', 'ขีดเส้นใต้ (Ctrl+U)'), BTN('strikeThrough', 'type-strikethrough', 'ขีดฆ่า'), SEP,
    `<select class="form-select form-select-sm rte-size" data-cmd="fontSize" title="ขนาดตัวอักษร" aria-label="ขนาดตัวอักษร">
       <option value="">ขนาด</option><option value="2">เล็ก</option><option value="3">ปกติ</option><option value="4">ใหญ่</option><option value="5">ใหญ่มาก</option><option value="6">ใหญ่พิเศษ</option></select>`, SEP,
    TXT('formatBlock', 'H2', 'หัวข้อใหญ่', 'h2'), TXT('formatBlock', 'H3', 'หัวข้อรอง', 'h3'), TXT('formatBlock', 'H4', 'หัวข้อย่อย', 'h4'),
    TXT('formatBlock', 'ปกติ', 'ย่อหน้าปกติ', 'p'), SEP,
    BTN('insertUnorderedList', 'list-ul', 'รายการแบบจุด'), BTN('insertOrderedList', 'list-ol', 'รายการแบบตัวเลข'), SEP,
    BTN('justifyLeft', 'text-left', 'ชิดซ้าย'), BTN('justifyCenter', 'text-center', 'กึ่งกลาง'), BTN('justifyRight', 'text-right', 'ชิดขวา'), SEP,
    `<label class="rte-btn rte-color" title="สีตัวอักษร"><i class="bi bi-fonts"></i><span class="bar" style="background:#dc2626"></span><input type="color" data-color="foreColor" value="#dc2626" aria-label="สีตัวอักษร"></label>`,
    `<label class="rte-btn rte-color" title="สีไฮไลต์"><i class="bi bi-highlighter"></i><span class="bar" style="background:#fde047"></span><input type="color" data-color="hiliteColor" value="#fde047" aria-label="สีไฮไลต์"></label>`, SEP,
    BTN('source', 'code-slash', 'แก้ไข HTML'), BTN('link', 'link-45deg', 'ใส่ลิงก์'), BTN('image', 'image', 'แทรกรูป'),
    BTN('youtube', 'youtube', 'แทรกวิดีโอ YouTube'), BTN('removeFormat', 'eraser', 'ล้างรูปแบบ', 'data-danger'),
  ].join('');

  const ytId = (url) => (String(url).match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|shorts\/|embed\/|live\/))([A-Za-z0-9_-]{11})/) || [])[1];

  function init(root) {
    const ta = root.querySelector('textarea');
    root.classList.add('rte');
    root.insertAdjacentHTML('afterbegin', `<div class="rte-toolbar" role="toolbar" aria-label="จัดรูปแบบข้อความ">${toolbar()}</div>
      <div class="rte-area" contenteditable="true" role="textbox" aria-multiline="true" aria-label="${root.dataset.label || 'เนื้อหา'}"></div>
      <input type="file" accept="image/jpeg,image/png,image/webp" hidden data-rte-file>`);
    const area = root.querySelector('.rte-area');
    const file = root.querySelector('[data-rte-file]');
    area.innerHTML = ta.value;
    area.dataset.placeholder = root.dataset.placeholder || 'เริ่มเขียนเนื้อหาที่นี่...';
    ta.classList.add('rte-source', 'form-control');
    let sourceMode = false;
    let saved = null;

    document.execCommand('styleWithCSS', false, true);
    document.execCommand('defaultParagraphSeparator', false, 'p');

    const sync = () => { if (!sourceMode) ta.value = area.innerHTML.trim() === '<br>' ? '' : area.innerHTML; };
    const save = () => { const s = getSelection(); if (s.rangeCount && area.contains(s.anchorNode)) saved = s.getRangeAt(0).cloneRange(); };
    const restore = () => { area.focus(); if (saved) { const s = getSelection(); s.removeAllRanges(); s.addRange(saved); } };
    const exec = (cmd, arg = null) => { restore(); document.execCommand(cmd, false, arg); save(); sync(); refresh(); };
    const insert = (html) => exec('insertHTML', html);

    const refresh = () => {
      root.querySelectorAll('[data-cmd]').forEach((b) => {
        const cmd = b.dataset.cmd;
        let on = false;
        try {
          if (cmd === 'formatBlock') on = document.queryCommandValue('formatBlock').toLowerCase() === b.dataset.arg;
          else if (['bold', 'italic', 'underline', 'strikeThrough', 'insertUnorderedList', 'insertOrderedList', 'justifyLeft', 'justifyCenter', 'justifyRight'].includes(cmd)) on = document.queryCommandState(cmd);
        } catch (e) { /* ไม่รองรับ */ }
        b.classList.toggle('on', on);
      });
    };

    // ถามค่า (ลิงก์ / YouTube) ด้วยหน้าต่างเล็กในตัว ไม่ใช้ prompt()
    const ask = (title, placeholder, done) => {
      root.querySelector('.rte-ask')?.remove();
      root.querySelector('.rte-toolbar').insertAdjacentHTML('afterend', `<div class="rte-ask"><label class="small fw-semibold">${title}</label>
        <div class="d-flex gap-2"><input class="form-control form-control-sm" placeholder="${placeholder}"><button type="button" class="btn btn-sm btn-primary" data-ok>ใส่</button><button type="button" class="btn btn-sm btn-light border" data-cancel>ยกเลิก</button></div></div>`);
      const box = root.querySelector('.rte-ask'), input = box.querySelector('input');
      const close = () => box.remove();
      const ok = () => { const v = input.value.trim(); close(); if (v) done(v); };
      box.querySelector('[data-ok]').onclick = ok;
      box.querySelector('[data-cancel]').onclick = () => { close(); restore(); };
      input.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); ok(); } if (e.key === 'Escape') { close(); restore(); } });
      input.focus();
    };

    // ป๊อปอัปแจ้งข้อผิดพลาด หน้าตาเดียวกับ partials/flash
    const toast = (msg) => {
      let box = document.querySelector('.sb-toasts');
      if (!box) { box = document.createElement('div'); box.className = 'sb-toasts'; box.setAttribute('aria-live', 'polite'); document.body.appendChild(box); }
      const t = document.createElement('div');
      t.className = 'sb-toast danger'; t.setAttribute('role', 'alert');
      t.innerHTML = '<span class="ic"><i class="bi bi-exclamation-octagon"></i></span><div class="tx"><b>ทำไม่สำเร็จ</b></div><button type="button" class="x" aria-label="ปิดข้อความแจ้งเตือน"><i class="bi bi-x-lg"></i></button>';
      t.querySelector('.tx').append(msg);
      t.querySelector('.x').onclick = () => t.remove();
      box.appendChild(t);
      setTimeout(() => t.remove(), 8000);
    };

    root.querySelector('.rte-toolbar').addEventListener('mousedown', (e) => { if (e.target.closest('button')) e.preventDefault(); });
    root.querySelector('.rte-toolbar').addEventListener('click', (e) => {
      const b = e.target.closest('button[data-cmd]');
      if (!b) return;
      const cmd = b.dataset.cmd;
      if (cmd === 'source') {
        sourceMode = !sourceMode;
        if (sourceMode) { sync(); ta.hidden = false; area.hidden = true; ta.focus(); } else { area.innerHTML = ta.value; ta.hidden = true; area.hidden = false; area.focus(); }
        b.classList.toggle('on', sourceMode);
        root.querySelectorAll('.rte-toolbar [data-cmd]:not([data-cmd=source]), .rte-toolbar input, .rte-toolbar select').forEach((x) => { x.disabled = sourceMode; });
        return;
      }
      if (sourceMode) return;
      if (cmd === 'link') {
        save();
        return ask('ลิงก์', 'https://', (url) => {
          if (!/^(https?:\/\/|mailto:|tel:|\/)/i.test(url)) url = 'https://' + url;
          if (saved && !saved.collapsed) exec('createLink', url);
          else insert(`<a href="${url.replace(/"/g, '&quot;')}" target="_blank">${url.replace(/</g, '&lt;')}</a>&nbsp;`);
        });
      }
      if (cmd === 'youtube') {
        save();
        return ask('ลิงก์วิดีโอ YouTube', 'https://www.youtube.com/watch?v=...', (url) => {
          const id = ytId(url);
          if (!id) return toast('ลิงก์นี้ไม่ใช่วิดีโอ YouTube');
          insert(`<div class="rte-video"><iframe src="https://www.youtube-nocookie.com/embed/${id}" allowfullscreen></iframe></div><p><br></p>`);
        });
      }
      if (cmd === 'image') { save(); return file.click(); }
      exec(cmd, b.dataset.arg ? `<${b.dataset.arg}>` : null);
    });
    root.querySelector('.rte-size').addEventListener('change', (e) => { if (e.target.value) exec('fontSize', e.target.value); e.target.value = ''; });
    root.querySelectorAll('[data-color]').forEach((inp) => {
      inp.addEventListener('pointerdown', save);
      inp.addEventListener('input', () => { inp.previousElementSibling.style.background = inp.value; });
      inp.addEventListener('change', () => exec(inp.dataset.color, inp.value));
    });

    file.addEventListener('change', async () => {
      const f = file.files[0];
      file.value = '';
      if (!f) return;
      const btn = root.querySelector('[data-cmd=image]');
      btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
      try {
        const fd = new FormData(); fd.append('image', f);
        const res = await fetch(root.dataset.uploadUrl, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' } });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.errors?.image?.[0] || json.message || 'อัปโหลดรูปไม่สำเร็จ');
        insert(`<img src="${json.url}" alt=""><p><br></p>`);
      } catch (err) { toast(err.message); } finally { btn.disabled = false; btn.innerHTML = '<i class="bi bi-image"></i>'; }
    });

    // วางข้อความจากเว็บ/Word: เก็บเป็นข้อความล้วน กัน HTML รก (Shift+Ctrl+V ปกติก็ได้ผลเดียวกัน)
    area.addEventListener('paste', (e) => {
      const html = e.clipboardData.getData('text/html');
      if (!html) return;
      e.preventDefault();
      const text = e.clipboardData.getData('text/plain');
      document.execCommand('insertHTML', false, text.split(/\r?\n\r?\n/).map((p) => `<p>${p.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/\r?\n/g, '<br>')}</p>`).join(''));
    });
    ['keyup', 'mouseup', 'input'].forEach((ev) => area.addEventListener(ev, () => { save(); sync(); refresh(); }));
    area.addEventListener('blur', sync);
    ta.addEventListener('input', () => { /* โหมด HTML: ค่าส่งตรงจาก textarea */ });
    root.closest('form')?.addEventListener('submit', () => { if (sourceMode) area.innerHTML = ta.value; else sync(); });
    ta.hidden = true;
  }

  document.querySelectorAll('[data-rich-editor]').forEach(init);
})();
