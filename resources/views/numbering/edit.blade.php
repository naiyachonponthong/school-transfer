@extends('layouts.app')
@section('title', $k['title'])

@section('content')
<div class="page-head">
    <div><h1>{{ $k['title'] }}</h1><div class="sub">ใช้เมื่อเว้นช่องเลข/รหัส{{ $k['item'] }}ว่างตอนเพิ่มรายการ · เลขที่มีอยู่แล้วไม่เปลี่ยน</div></div>
    <div class="actions"><a href="{{ route($k['index']) }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กลับรายการ{{ $k['item'] }}</a></div>
</div>

<form method="POST" action="{{ route($k['update'], $k['params'] ?? []) }}">
    @csrf @method('PUT')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="side-sticky">
                <div class="card mb-3">
                    <div class="card-header"><i class="bi bi-123"></i> รูปแบบเลข</div>
                    <div class="card-body">
                        <input name="pattern" id="pattern" value="{{ old('pattern', $pattern) }}" maxlength="{{ $k['max'] }}" required autocomplete="off"
                               class="form-control form-control-lg font-monospace @error('pattern') is-invalid @enderror">
                        @error('pattern')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            @foreach (array_merge(array_keys($series::TOKENS), ['{SEQ3}', '{SEQ5}'], $categories ? [] : ['{SEQ8}'], ['-', '/', '.']) as $t)
                                <button type="button" class="btn btn-sm btn-light border font-monospace" data-token="{{ $t }}">{{ $t }}</button>
                            @endforeach
                        </div>
                        <div class="rounded-3 p-3 mt-3 text-center" style="background:var(--bs-tertiary-bg)">
                            <div class="small text-muted">ตัวอย่าง: {{ $k['example'] ? $k['example'].' ' : '' }}รายการแรก (ปีงบประมาณ {{ $fy }})</div>
                            <div id="preview" class="font-monospace fs-3 fw-bold text-break">&nbsp;</div>
                            <div id="previewWarn" class="small text-danger"></div>
                        </div>
                        <table class="table table-sm small mt-3 mb-0">
                            @foreach ($series::TOKENS as $token => $desc)
                                <tr><td class="font-monospace text-nowrap">{{ $token }}</td><td>{{ $desc }}</td></tr>
                            @endforeach
                            <tr><td class="text-nowrap">ตัวอักษรอื่น</td><td>พิมพ์ตามนั้น เช่น อักษรย่อโรงเรียน ขีด ทับ จุด</td></tr>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header"><i class="bi bi-magic"></i> รูปแบบสำเร็จรูป <span class="text-muted small">กดเพื่อใช้</span></div>
                    <div class="list-group list-group-flush">
                        @foreach ($series::PRESETS as $preset => $desc)
                            <button type="button" class="list-group-item list-group-item-action" data-preset="{{ $preset }}">
                                <div class="d-flex justify-content-between gap-2"><span class="font-monospace">{{ $preset }}</span><span class="font-monospace fw-semibold text-primary ex"></span></div>
                                <div class="small text-muted">{{ $desc }}</div>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            @if (! $categories)
            <div class="card">
                <div class="card-body">
                    <div class="small text-muted">เลขถัดไป (ตามรูปแบบที่บันทึกไว้)</div>
                    <div class="font-monospace fs-2 fw-bold">{{ $nextPlain }}</div>
                    <div class="small text-muted mt-2"><i class="bi bi-info-circle"></i> เลขลำดับนับต่อจากเลขสูงสุดที่มีอยู่แล้วในชุดเดียวกัน · {{ $k['note'] }}</div>
                </div>
            </div>
            @else
            <div class="card">
                <div class="card-header"><i class="bi bi-tags"></i> รหัส{{ $k['cat'] }}{{ $k['item'] }} <span class="text-muted small">ใช้แทน <span class="font-monospace">{CAT}</span></span></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr><th>{{ $k['cat'] }}</th><th style="width:140px">รหัส</th><th class="text-end">ในทะเบียน</th><th>เลขถัดไป <span class="fw-normal text-muted small">(ตามรูปแบบที่บันทึกไว้)</span></th></tr></thead>
                        <tbody>
                        @foreach ($categories as $c)
                            <tr>
                                <td>{{ $c }}</td>
                                <td>
                                    <input name="codes[{{ $c }}]" value="{{ old('codes.'.$c, $codes[$c] ?? '') }}" maxlength="10" required data-code="{{ $c }}"
                                           class="form-control form-control-sm font-monospace @error('codes.'.$c) is-invalid @enderror">
                                    @error('codes.'.$c)<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </td>
                                <td class="text-end">{{ number_format($counts[$c] ?? 0) }}</td>
                                <td class="font-monospace text-nowrap">{{ $next[$c] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-transparent small text-muted">
                    <i class="bi bi-info-circle"></i> รหัสเริ่มต้นเป็นตัวอย่าง ควรแก้ให้ตรงกับเลขในทะเบียนเดิมของโรงเรียน ·
                    เลขลำดับนับต่อจากเลขสูงสุดที่มีอยู่แล้วในชุดเดียวกัน ถ้ามีรหัสเดิมอยู่แล้วระบบจะนับต่อให้เอง ·
                    {{ $k['note'] }}
                </div>
            </div>
            @endif
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึก</button>
                <a href="{{ route($k['create']) }}" class="btn btn-light border btn-lg"><i class="bi bi-plus-lg"></i> ไปเพิ่ม{{ $k['item'] }}</a>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const input = document.getElementById('pattern');
    const fy = @json($fy);
    const year = @json(today()->year + 543);
    const known = ['{CAT}', '{FY}', '{FY2}', '{YEAR}'];
    const example = @json($k['example']);
    const catCode = () => [...document.querySelectorAll('[data-code]')].find((el) => el.dataset.code === example)?.value || '';

    const render = (pattern) => {
        const seq = pattern.match(/\{SEQ([3-9])?\}/g) || [];
        const unknown = (pattern.match(/\{[^}]*\}/g) || []).filter((t) => !known.includes(t) && !/^\{SEQ([3-9])?\}$/.test(t));
        const text = pattern
            .replaceAll('{CAT}', catCode()).replaceAll('{FY2}', String(fy).slice(-2)).replaceAll('{FY}', fy).replaceAll('{YEAR}', year)
            .replace(/\{SEQ([3-9])?\}/g, (_, n) => '1'.padStart(Number(n || 4), '0'));
        const warn = seq.length !== 1 ? 'ต้องมี {SEQ} หนึ่งตำแหน่ง' : (unknown.length ? 'ไม่รู้จัก ' + unknown.join(' ') : '');
        return { text, warn };
    };

    const update = () => {
        const { text, warn } = render(input.value);
        document.getElementById('preview').textContent = text || ' ';
        document.getElementById('previewWarn').textContent = warn;
        document.querySelectorAll('[data-preset]').forEach((b) => {
            b.querySelector('.ex').textContent = render(b.dataset.preset).text;
            b.classList.toggle('bg-primary-subtle', b.dataset.preset === input.value);
        });
    };

    document.querySelectorAll('[data-token]').forEach((b) => b.addEventListener('click', () => {
        input.focus();
        input.setRangeText(b.dataset.token, input.selectionStart, input.selectionEnd, 'end');
        update();
    }));
    document.querySelectorAll('[data-preset]').forEach((b) => b.addEventListener('click', () => {
        input.value = b.dataset.preset;
        update();
        input.focus();
    }));
    input.addEventListener('input', update);
    document.querySelectorAll('[data-code]').forEach((el) => el.addEventListener('input', update));
    update();
})();
</script>
@endpush
