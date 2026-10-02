@extends('layouts.app')
@section('title', $asset->exists ? 'แก้ไข '.$asset->code : 'เพิ่มครุภัณฑ์')

@section('content')
<div class="page-head">
    <div><h1>{{ $asset->exists ? 'แก้ไข: '.$asset->code : 'เพิ่มครุภัณฑ์' }}</h1><div class="sub">ช่องที่มี <span class="text-danger">*</span> จำเป็น · เว้นเลขครุภัณฑ์ว่างไว้ ระบบจะออกเลขให้ตามรูปแบบที่ตั้งไว้</div></div>
    <div class="actions"><a href="{{ route('assets.numbering') }}" class="btn btn-light border"><i class="bi bi-123"></i> ตั้งรูปแบบเลขครุภัณฑ์</a></div>
</div>

<form method="POST" enctype="multipart/form-data" id="assetForm" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}">
    @csrf @if ($asset->exists) @method('PUT') @endif
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-upc"></i> เลขครุภัณฑ์และชื่อ</div>
                <div class="card-body row g-3">
                    <div class="{{ $asset->exists ? 'col-md-5' : 'col-md-4' }}">
                        <label class="form-label">เลขครุภัณฑ์</label>
                        <input name="code" id="code" value="{{ old('code', $asset->code) }}" class="form-control font-monospace @error('code') is-invalid @enderror" placeholder="เว้นว่าง = ออกเลขอัตโนมัติ" autocomplete="off">
                        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" id="codeHint" aria-live="polite">&nbsp;</div>
                    </div>
                    @unless ($asset->exists)
                        <div class="col-md-2">
                            <label class="form-label">จำนวน (ชิ้น)</label>
                            <input type="number" name="quantity" id="quantity" min="1" max="200" value="{{ old('quantity', 1) }}" class="form-control @error('quantity') is-invalid @enderror">
                            @error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endunless
                    <div class="{{ $asset->exists ? 'col-md-7' : 'col-md-6' }}">
                        <label class="form-label">ชื่อครุภัณฑ์ <span class="text-danger">*</span></label>
                        <input name="name" value="{{ old('name', $asset->name) }}" class="form-control @error('name') is-invalid @enderror" required autofocus placeholder="เช่น เครื่องคอมพิวเตอร์ตั้งโต๊ะ">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @unless ($asset->exists)
                        <div class="col-12 d-none" id="multiNote"><div class="alert alert-info small mb-0 py-2"><i class="bi bi-collection"></i> ซื้อมาหลายชิ้นพร้อมกัน (เช่น โต๊ะ 40 ตัว) ระบบจะเพิ่มให้ทีละชิ้นโดยข้อมูลเหมือนกันและเลขเรียงต่อกัน หมายเลขเครื่องค่อยเติมรายชิ้นภายหลัง · หลังบันทึกพิมพ์สติกเกอร์ QR ชุดนี้ได้ทันที</div></div>
                    @endunless
                    <div class="col-md-6">
                        <label class="form-label">ประเภท</label>
                        <select name="category" class="form-select" id="category"><option value="">-</option>@foreach (\App\Models\Asset::CATEGORIES as $c => $life)<option value="{{ $c }}" data-life="{{ $life }}" @selected(old('category', $asset->category) === $c)>{{ $c }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-6"><label class="form-label">ยี่ห้อ / รุ่น</label><input name="brand" value="{{ old('brand', $asset->brand) }}" class="form-control" placeholder="เช่น Dell OptiPlex 3000"></div>
                    <div class="col-md-6"><label class="form-label">หมายเลขเครื่อง (Serial)</label><input name="serial_no" id="serial" value="{{ old('serial_no', $asset->serial_no) }}" class="form-control font-monospace"></div>
                    <div class="col-md-6"><label class="form-label">แหล่งงบ / วิธีได้มา</label><input name="budget_source" value="{{ old('budget_source', $asset->budget_source) }}" class="form-control" list="budgets"></div>
                    <datalist id="budgets"><option>เงินงบประมาณ</option><option>เงินอุดหนุน</option><option>เงินรายได้สถานศึกษา</option><option>รับบริจาค</option></datalist>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><i class="bi bi-cash-coin"></i> มูลค่าและค่าเสื่อมราคา</div>
                <div class="card-body row g-3">
                    <div class="col-md-4"><label class="form-label">วันที่ได้มา</label><input type="date" name="acquired_on" id="acquired" value="{{ old('acquired_on', $asset->acquired_on?->toDateString()) }}" class="form-control"><div class="form-text">ใช้คิดปีงบประมาณในเลขครุภัณฑ์</div></div>
                    <div class="col-md-4"><label class="form-label">ราคาต่อชิ้น (บาท)</label><input type="number" step="0.01" min="0" name="price" id="price" value="{{ old('price', $asset->price) }}" class="form-control"><div class="form-text" id="priceTotal">&nbsp;</div></div>
                    <div class="col-md-4"><label class="form-label">อายุการใช้งาน (ปี)</label><input type="number" min="1" name="useful_life" id="life" value="{{ old('useful_life', $asset->useful_life) }}" class="form-control" placeholder="ตามประเภท"></div>
                    <div class="col-12 small text-muted">ค่าเสื่อมราคาคิดแบบเส้นตรงจากวันที่ได้มา คงมูลค่าซาก 1 บาท · อายุการใช้งานตามประเภทเป็นค่าเริ่มต้น ตรวจกับหลักเกณฑ์ของต้นสังกัด</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="bi bi-geo-alt"></i> ที่ตั้ง ผู้รับผิดชอบ และสถานะ</div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><label class="form-label">สถานที่ / ห้อง</label><input name="location" value="{{ old('location', $asset->location) }}" class="form-control" list="locations" placeholder="เช่น ห้องคอมพิวเตอร์ 1"></div>
                    <datalist id="locations">@foreach ($locations as $l)<option>{{ $l }}</option>@endforeach</datalist>
                    <div class="col-md-6"><label class="form-label">ผู้รับผิดชอบ</label><select name="responsible_id" class="form-select"><option value="">-</option>@foreach ($staff as $u)<option value="{{ $u->id }}" @selected((int) old('responsible_id', $asset->responsible_id) === $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">สถานะ</label><select name="status" class="form-select">@foreach (\App\Models\Asset::STATUSES as $k => [$label])<option value="{{ $k }}" @selected(old('status', $asset->status) === $k)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">วันที่จำหน่าย</label><input type="date" name="disposed_on" value="{{ old('disposed_on', $asset->disposed_on?->toDateString()) }}" class="form-control"></div>
                    <div class="col-12"><label class="form-label">หมายเหตุ</label><textarea name="note" rows="2" class="form-control">{{ old('note', $asset->note) }}</textarea></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="side-sticky">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-image"></i> รูปครุภัณฑ์</div>
                    <div class="card-body">
                        <x-photo-drop :current="$asset->photo ? asset('storage/'.$asset->photo) : null" />
                        <div class="form-text">ถ่ายให้เห็นตัวเครื่องและป้ายเลขครุภัณฑ์ ใช้ประกอบการตรวจสอบพัสดุประจำปี</div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-body d-grid gap-2">
                        <button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึก</button>
                        @unless ($asset->exists)<button name="another" value="1" class="btn btn-outline-primary">บันทึกแล้วเพิ่มต่อ (ห้อง/ประเภทเดิม)</button>@endunless
                        <a href="{{ $asset->exists ? route('assets.show', $asset) : route('assets.index') }}" class="btn btn-light border">ยกเลิก</a>
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
    const code = $('code'), qty = $('quantity'), cat = $('category'), date = $('acquired'), hint = $('codeHint');
    const editing = @json($asset->exists);

    const say = (...parts) => {
        // parts: ข้อความธรรมดา หรือ [ข้อความตัวหนา]
        hint.replaceChildren(...parts.map((p) => {
            if (!Array.isArray(p)) return document.createTextNode(p);
            const b = document.createElement('b');
            b.className = 'font-monospace text-body text-nowrap';
            b.textContent = p[0];
            return b;
        }));
    };

    let timer;
    const refresh = () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const n = Math.max(1, parseInt(qty?.value || '1', 10) || 1);
            if (code.value.trim()) {
                say(n > 1 ? 'เพิ่มหลายชิ้นต้องเว้นเลขว่าง' : 'ใช้เลขที่พิมพ์เอง');
                return;
            }
            const params = new URLSearchParams({ category: cat.value, acquired_on: date.value, quantity: n });
            try {
                const r = await fetch(@json(route('assets.next-number')) + '?' + params, { headers: { Accept: 'application/json' } });
                if (!r.ok) return;
                const d = await r.json();
                const note = d.needs_category ? ' (ยังไม่เลือกประเภท ใช้รหัส "อื่น ๆ")' : '';
                if (d.count > 1) say('จะได้เลข ', [d.first], ' ถึง ', [d.last], note);
                else say(editing ? 'ลบเลขเดิมออก = ออกเลขใหม่ ' : 'จะได้เลข ', [d.first], note);
            } catch (e) { /* ออฟไลน์: ไม่แสดงตัวอย่าง */ }
        }, 200);
    };

    const syncQty = () => {
        if (!qty) return;
        const multi = (parseInt(qty.value, 10) || 1) > 1;
        $('multiNote').classList.toggle('d-none', !multi);
        $('serial').disabled = multi;
        $('serial').placeholder = multi ? 'เติมรายชิ้นภายหลัง' : '';
        const price = parseFloat($('price').value) || 0;
        $('priceTotal').textContent = multi && price ? `รวม ${(price * qty.value).toLocaleString('th-TH', { minimumFractionDigits: 2 })} บาท` : ' ';
    };

    cat.addEventListener('change', (e) => {
        const life = e.target.selectedOptions[0]?.dataset.life;
        $('life').placeholder = life ? `ตามประเภท (${life} ปี)` : 'ตามประเภท';
    });
    [cat, date, code, qty].forEach((el) => el?.addEventListener('input', refresh));
    [cat, date].forEach((el) => el.addEventListener('change', refresh));
    [qty, $('price')].forEach((el) => el?.addEventListener('input', syncQty));
    cat.dispatchEvent(new Event('change'));
    syncQty();
    refresh();
})();
</script>
@endpush
