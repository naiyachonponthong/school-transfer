@extends('layouts.app')
@section('title', $book->exists ? 'แก้ไข '.$book->title : 'ลงทะเบียนหนังสือ')

@php
    use App\Support\Dewey;
    $v = fn ($k, $d = null) => old($k, $book->{$k} ?? $d);
@endphp

@section('content')
<div class="page-head">
    <div><h1>{{ $book->exists ? 'แก้ไขระเบียนหนังสือ' : 'ลงทะเบียนหนังสือ' }}</h1>
        <div class="sub">ลงรายการตามหลักบรรณารักษ์: ข้อมูลบรรณานุกรม · เลขหมู่ระบบทศนิยมของดิวอี้ (DDC) · เลขผู้แต่ง · ตัวเล่ม (บาร์โค้ด + เลขทะเบียน)</div></div>
    <div class="actions"><a href="{{ $book->exists ? route('library.show', $book) : route('library.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับ</a></div>
</div>

<form method="POST" enctype="multipart/form-data" id="bookForm" action="{{ $book->exists ? route('library.update', $book) : route('library.store') }}">
    @csrf @if ($book->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-lg-8">
            {{-- ========== บรรณานุกรม ========== --}}
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-journal-text"></i> ข้อมูลบรรณานุกรม</div>
                <div class="card-body row g-3">
                    <div class="col-md-4"><label class="form-label">ISBN</label>
                        <input name="isbn" value="{{ $v('isbn') }}" class="form-control font-monospace @error('isbn') is-invalid @enderror" inputmode="numeric" placeholder="978-616-…" autocomplete="off">
                        @error('isbn')<div class="invalid-feedback">{{ $message }}</div>@else<div class="form-text">10 หรือ 13 หลัก ดูหลังปก/หน้าลิขสิทธิ์ · สแกนบาร์โค้ดหลังปกได้</div>@enderror</div>
                    <div class="col-md-8"><label class="form-label">ชื่อเรื่อง <span class="text-danger">*</span></label>
                        <input name="title" id="title" value="{{ $v('title') }}" class="form-control form-control-lg @error('title') is-invalid @enderror" required autofocus>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label">ผู้แต่ง</label>
                        <input name="author" id="author" value="{{ $v('author') }}" class="form-control" placeholder="ผู้แต่งไทย: ชื่อ นามสกุล · ต่างชาติ: Surname, Firstname">
                        <div class="form-text">ไม่ต้องใส่คำนำหน้า (นาย/นาง/ดร.)</div></div>
                    <div class="col-md-6"><label class="form-label">ผู้แต่งร่วม / ผู้แปล / ผู้วาดภาพ</label><input name="contributors" value="{{ $v('contributors') }}" class="form-control" placeholder="เช่น สมใจ ใจดี (ผู้แปล)"></div>
                    <div class="col-6 col-md-2"><label class="form-label">ครั้งที่พิมพ์</label><input name="edition" value="{{ $v('edition') }}" class="form-control" placeholder="1"></div>
                    <div class="col-6 col-md-3"><label class="form-label">สถานที่พิมพ์</label><input name="pub_place" value="{{ $v('pub_place') }}" class="form-control" list="places" placeholder="กรุงเทพฯ"></div>
                    <datalist id="places"><option>กรุงเทพฯ</option><option>นนทบุรี</option><option>เชียงใหม่</option><option>ขอนแก่น</option></datalist>
                    <div class="col-8 col-md-5"><label class="form-label">สำนักพิมพ์</label><input name="publisher" value="{{ $v('publisher') }}" class="form-control"></div>
                    <div class="col-4 col-md-2"><label class="form-label">ปีพิมพ์</label><input name="pub_year" value="{{ $v('pub_year') }}" class="form-control @error('pub_year') is-invalid @enderror" inputmode="numeric" maxlength="4" placeholder="2567">
                        @error('pub_year')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-4 col-md-3"><label class="form-label">จำนวนหน้า</label><input type="number" name="pages" min="1" value="{{ $v('pages') }}" class="form-control"></div>
                    <div class="col-4 col-md-3"><label class="form-label">ความสูง (ซม.)</label><input type="number" name="size_cm" min="5" max="80" value="{{ $v('size_cm') }}" class="form-control" placeholder="21"></div>
                    <div class="col-4 col-md-3 d-flex align-items-end"><label class="form-check form-switch mb-2"><input type="checkbox" name="illustrated" value="1" class="form-check-input" @checked($v('illustrated'))> มีภาพประกอบ</label></div>
                    <div class="col-md-3"><label class="form-label">ภาษา</label><input name="language" value="{{ $v('language', 'ไทย') }}" class="form-control" list="langs"></div>
                    <datalist id="langs"><option>ไทย</option><option>อังกฤษ</option><option>จีน</option><option>ญี่ปุ่น</option><option>ไทย-อังกฤษ</option></datalist>
                    <div class="col-md-12"><label class="form-label">ชื่อชุด</label><input name="series" value="{{ $v('series') }}" class="form-control" placeholder="เช่น ชุด สารานุกรมไทยสำหรับเยาวชน"></div>
                    <div class="col-12"><label class="form-label">หัวเรื่อง</label>
                        <textarea name="subjects" rows="2" class="form-control" placeholder="คั่นด้วย ; เช่น ไดโนเสาร์ ; สัตว์ดึกดำบรรพ์ ; หนังสือสำหรับเด็ก">{{ $v('subjects') }}</textarea>
                        <div class="form-text">หัวเรื่องช่วยให้ค้นหาเจอแม้ไม่รู้ชื่อเรื่อง ใช้คำเดียวกันทุกเล่ม (ตามบัญชีหัวเรื่องสำหรับหนังสือภาษาไทย)</div></div>
                    <div class="col-12"><label class="form-label">สาระสังเขป</label><textarea name="summary" rows="3" class="form-control" placeholder="เนื้อหาโดยย่อ">{{ $v('summary') }}</textarea></div>
                    <div class="col-12"><label class="form-label">หมายเหตุ</label><input name="note" value="{{ $v('note') }}" class="form-control" placeholder="เช่น มีแผ่นซีดีประกอบ · รางวัลหนังสือดีเด่น"></div>
                </div>
            </div>

            {{-- ========== การจัดหมวดหมู่ ========== --}}
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-diagram-3"></i> การจัดหมวดหมู่ (ระบบทศนิยมของดิวอี้) และเลขเรียกหนังสือ</div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><label class="form-label">ประเภททรัพยากร</label>
                        <select name="collection" id="collection" class="form-select">
                            @foreach (Dewey::COLLECTIONS as $k => [$label, $symbol, $place])
                                <option value="{{ $k }}" data-symbol="{{ $symbol }}" data-place="{{ $place }}" @selected($v('collection', 'general') === $k)>{{ $label }}{{ $symbol ? ' ('.$symbol.')' : '' }}</option>
                            @endforeach
                        </select>
                        <div class="form-text" id="collectionHint"></div></div>
                    <div class="col-md-6" data-ddc><label class="form-label">หมวดใหญ่</label>
                        <select id="ddcMain" class="form-select"><option value="">— เลือก —</option>@foreach (Dewey::CLASSES as $c => $label)<option value="{{ $c }}">{{ $c }} {{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-6" data-ddc><label class="form-label">หมวดย่อย</label><select id="ddcDiv" class="form-select"><option value="">— เลือกหมวดใหญ่ก่อน —</option></select></div>
                    <div class="col-md-6" data-ddc><label class="form-label">เลขหมู่ <span class="text-danger" id="classReq">*</span></label>
                        <input name="class_number" id="classNumber" value="{{ $v('class_number') }}" class="form-control font-monospace fs-5 @error('class_number') is-invalid @enderror" list="ddcCommon" placeholder="เช่น 567.9" autocomplete="off">
                        @error('class_number')<div class="invalid-feedback">{{ $message }}</div>@else<div class="form-text" id="classLabel">ทศนิยมละเอียดขึ้นได้ เช่น 590 → 591.5</div>@enderror</div>
                    <datalist id="ddcCommon">@foreach (Dewey::COMMON as $n => $label)<option value="{{ $n }}">{{ $label }}</option>@endforeach</datalist>

                    <div class="col-md-8">
                        <label class="form-label">เลขผู้แต่ง <span class="text-muted small">(อักษรตัวแรกผู้แต่ง + เลขจากตารางเลขผู้แต่ง + อักษรตัวแรกชื่อเรื่อง)</span></label>
                        <div class="input-group">
                            <input id="amAuthor" class="form-control text-center fw-bold" style="max-width:62px" title="อักษรตัวแรกของผู้แต่ง" maxlength="3">
                            <input id="amNumber" class="form-control text-center font-monospace" style="max-width:96px" placeholder="เลข" inputmode="numeric" maxlength="4" title="เลขจากตารางเลขผู้แต่งภาษาไทย / Cutter-Sanborn">
                            <input id="amTitle" class="form-control text-center fw-bold" style="max-width:62px" title="อักษรตัวแรกของชื่อเรื่อง" maxlength="3">
                            <span class="input-group-text">=</span>
                            <input name="author_mark" id="authorMark" value="{{ $v('author_mark') }}" class="form-control font-monospace fw-bold" maxlength="20">
                        </div>
                        <div class="form-text">ระบบเติมอักษรจากผู้แต่ง/ชื่อเรื่องให้ · ใส่เลขจากตารางเลขผู้แต่งถ้าห้องสมุดใช้ (ไม่ใส่ก็ได้ เช่น นวนิยาย "น ศร")</div>
                    </div>
                    <div class="col-md-4"><label class="form-label">เล่มที่ (ล.)</label><input name="volume" id="volume" value="{{ $v('volume') }}" class="form-control" placeholder="เช่น 1"><div class="form-text">หนังสือชุดหลายเล่ม</div></div>
                </div>
            </div>

            {{-- ========== ตัวเล่ม (ตอนลงทะเบียนครั้งแรก) ========== --}}
            @unless ($book->exists)
                <div class="card">
                    <div class="card-header"><i class="bi bi-upc-scan"></i> ตัวเล่มที่รับเข้า <span class="small text-muted fw-normal ms-1">ทุกเล่มได้บาร์โค้ดและเลขทะเบียนของตัวเอง</span>
                        <a href="{{ route('library.numbering', 'library-barcode') }}" class="small ms-auto">ตั้งรูปแบบเลข</a></div>
                    <div class="card-body row g-3">
                        <div class="col-6 col-md-3"><label class="form-label">จำนวนเล่ม</label><input type="number" name="copies_count" id="copiesCount" min="1" max="200" value="{{ old('copies_count', 1) }}" class="form-control @error('copies_count') is-invalid @enderror" required></div>
                        <div class="col-6 col-md-3"><label class="form-label">ราคา/เล่ม (บาท)</label><input type="number" step="0.01" min="0" name="price" value="{{ old('price') }}" class="form-control"></div>
                        <div class="col-6 col-md-3"><label class="form-label">วันที่ได้รับ</label><input type="date" name="acquired_on" value="{{ old('acquired_on', today()->toDateString()) }}" class="form-control"></div>
                        <div class="col-6 col-md-3"><label class="form-label">วิธีได้มา</label><input name="source" value="{{ old('source') }}" class="form-control" list="sources" placeholder="จัดซื้อ"></div>
                        <datalist id="sources">@foreach (\App\Models\BookCopy::SOURCES as $s)<option>{{ $s }}</option>@endforeach</datalist>
                        <div class="col-md-6"><label class="form-label">ชั้นจัดเก็บ</label><input name="location" value="{{ old('location') }}" class="form-control" list="locs" placeholder="เช่น ชั้น 500 แถว 2"></div>
                        <datalist id="locs">@foreach ($locations as $l)<option>{{ $l }}</option>@endforeach</datalist>
                        <div class="col-md-6"><label class="form-label">บาร์โค้ด <span class="text-muted small">(มีบาร์โค้ดเดิมติดอยู่แล้ว ใส่/สแกนได้)</span></label>
                            <input name="barcode" id="barcode" value="{{ old('barcode') }}" class="form-control font-monospace @error('barcode') is-invalid @enderror" placeholder="เว้นว่าง = ออกให้อัตโนมัติ" autocomplete="off">
                            @error('barcode')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        <div class="col-12 small text-muted"><i class="bi bi-magic"></i> เล่มแรกจะได้บาร์โค้ด <b class="font-monospace text-body">{{ $nextBarcode }}</b> · เลขทะเบียน <b class="font-monospace text-body">{{ $nextAccession }}</b> (เล่มต่อไปเรียงต่อกัน)</div>
                    </div>
                </div>
            @endunless
        </div>

        <div class="col-lg-4">
            <div class="side-sticky">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-image"></i> ภาพปก</div>
                    <div class="card-body"><x-photo-drop name="cover" :current="$book->coverUrl()" hint="ถ่ายภาพปกหน้า" style="aspect-ratio:3/4" /></div>
                </div>
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-bookmark"></i> ตัวอย่างป้ายสัน</div>
                    <div class="card-body d-flex gap-3 align-items-start">
                        <div class="spine big" id="spinePreview"><div class="band"></div><div class="lines"></div></div>
                        <div class="small">
                            <div class="text-muted">เลขเรียกหนังสือ</div>
                            <div class="font-monospace fw-bold fs-6 mb-2" id="callText">-</div>
                            <div class="text-muted">หมวด</div>
                            <div id="callClass">-</div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body d-grid gap-2">
                        <button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึก</button>
                        @unless ($book->exists)<button name="another" value="1" class="btn btn-outline-primary">บันทึกแล้วลงทะเบียนเล่มถัดไป</button>@endunless
                        <a href="{{ $book->exists ? route('library.show', $book) : route('library.index') }}" class="btn btn-light border">ยกเลิก</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const $ = (id) => document.getElementById(id);
    const CLASSES = @json(Dewey::CLASSES), DIVS = @json(Dewey::DIVISIONS), COLORS = @json(Dewey::COLORS);
    const coll = $('collection'), main = $('ddcMain'), div = $('ddcDiv'), cls = $('classNumber'), mark = $('authorMark');
    const amA = $('amAuthor'), amN = $('amNumber'), amT = $('amTitle');
    let markTouched = mark.value.trim() !== '';

    // ---------- เลขผู้แต่ง ----------
    const initial = (t) => {
        t = (t || '').trim();
        let m = t.match(/^[เแโใไ]?([ก-ฮ])/);
        if (m) return m[1];
        m = t.match(/[A-Za-z]/);
        return m ? m[0].toUpperCase() : t.charAt(0);
    };
    const authorEntry = (a) => {
        a = (a || '').trim().replace(/^(นางสาว|นาง|นาย|ด\.ช\.|ด\.ญ\.|เด็กชาย|เด็กหญิง|ศ\.ดร\.|รศ\.ดร\.|ผศ\.ดร\.|ดร\.|ศ\.|รศ\.|ผศ\.|อ\.|Dr\.?|Mr\.?|Mrs\.?|Ms\.?)\s*/, '');
        if (!a || /[ก-ฮ]/.test(a.slice(0, 2))) return a;
        if (a.includes(',')) return a.split(',')[0].trim();
        const p = a.split(/\s+/);
        return p[p.length - 1];
    };
    const titleEntry = (t) => (t || '').trim().replace(/^(the|a|an)\s+/i, '');
    const titleLetter = () => { const i = initial(titleEntry($('title').value)); return /[A-Z]/.test(i) ? i.toLowerCase() : i; };
    // แยกเลขผู้แต่งเดิม เช่น ศ532ร → ศ · 532 · ร
    const parsed = mark.value.match(/^(\D*?)(\d*)(\D*)$/);
    if (parsed) { amA.value = parsed[1]; amN.value = parsed[2]; amT.value = parsed[3]; }
    const compose = () => { mark.value = amA.value.trim() + amN.value.trim() + amT.value.trim(); render(); };
    const autofill = () => {
        if (markTouched) return;
        amA.value = initial(authorEntry($('author').value));
        amT.value = titleLetter();
        compose();
    };
    [amA, amN, amT].forEach((el) => el.addEventListener('input', () => { markTouched = true; compose(); }));
    mark.addEventListener('input', () => { markTouched = true; render(); });
    $('author').addEventListener('input', autofill);
    $('title').addEventListener('input', autofill);

    // ---------- DDC ----------
    const fillDivs = (m) => {
        div.innerHTML = '<option value="">— เลือก —</option>' + Object.entries(DIVS).filter(([k]) => m && k[0] === m[0])
            .map(([k, l]) => `<option value="${k}">${k} ${l}</option>`).join('');
    };
    main.addEventListener('change', () => { fillDivs(main.value); if (main.value && !cls.value.startsWith(main.value[0])) cls.value = main.value; render(); });
    div.addEventListener('change', () => { if (div.value && !cls.value.startsWith(div.value.slice(0, 2))) cls.value = div.value; render(); });
    cls.addEventListener('input', () => {
        const n = cls.value.trim();
        if (/^\d{3}/.test(n)) {
            const m = n[0] + '00', d = n.slice(0, 2) + '0';
            if (main.value !== m) { main.value = m; fillDivs(m); }
            div.value = d;
        }
        render();
    });

    // ---------- ตัวอย่างป้ายสัน ----------
    const render = () => {
        const opt = coll.selectedOptions[0], symbol = opt.dataset.symbol, place = opt.dataset.place;
        const lines = [];
        if (place === 'prefix') lines.push(symbol);
        if (place === 'class') lines.push(symbol); else if (cls.value.trim()) lines.push(cls.value.trim());
        if (mark.value.trim()) lines.push(mark.value.trim());
        if ($('volume').value.trim()) lines.push('ล.' + $('volume').value.trim().replace(/^ล\.?\s*/, ''));
        const n = cls.value.trim(), m = place === 'class' ? '800' : (/^\d{3}/.test(n) ? n[0] + '00' : '');
        const band = $('spinePreview').querySelector('.band');
        band.style.background = COLORS[m] || '#d1d5db';
        band.textContent = place === 'class' ? symbol : (m || '');
        $('spinePreview').querySelector('.lines').innerHTML = lines.map((l) => `<div>${l.replace(/[&<>]/g, '')}</div>`).join('') || '<div class="text-muted small">—</div>';
        $('callText').textContent = lines.join(' ') || '-';
        const d = /^\d{3}/.test(n) ? n.slice(0, 2) + '0' : '';
        $('callClass').textContent = place === 'class' ? opt.textContent : (m ? `${m} ${CLASSES[m]}${DIVS[d] ? ' › ' + d + ' ' + DIVS[d] : ''}` : '-');
        $('classLabel') && ($('classLabel').textContent = m && place !== 'class' ? `${m} ${CLASSES[m]}${DIVS[d] ? ' › ' + d + ' ' + DIVS[d] : ''}` : 'ทศนิยมละเอียดขึ้นได้ เช่น 590 → 591.5');
        // นวนิยาย/เรื่องสั้น: ใช้สัญลักษณ์แทนเลขหมู่
        document.querySelectorAll('[data-ddc]').forEach((el) => el.classList.toggle('opacity-50', place === 'class'));
        $('classReq').classList.toggle('d-none', place === 'class');
        $('collectionHint').textContent = place === 'class' ? `ใช้ "${symbol}" แทนเลขหมู่ ไม่ต้องใส่เลขหมู่` : (place === 'prefix' ? `พิมพ์ "${symbol}" ไว้เหนือเลขหมู่บนป้ายสัน` : 'ใช้เลขหมู่ DDC');
    };
    coll.addEventListener('change', render);
    $('volume').addEventListener('input', render);
    $('copiesCount')?.addEventListener('input', (e) => { const multi = Number(e.target.value) > 1; $('barcode').disabled = multi; $('barcode').placeholder = multi ? 'ออกให้อัตโนมัติ (หลายเล่ม)' : 'เว้นว่าง = ออกให้อัตโนมัติ'; });

    cls.dispatchEvent(new Event('input'));
    autofill();
    render();
})();
</script>
@endpush
