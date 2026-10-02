@extends('layouts.app')
@section('title', 'คุณลักษณะอันพึงประสงค์ / อ่าน คิดวิเคราะห์ และเขียน')

@section('content')
@php($levels = \App\Support\Evaluation::LEVELS)
<div class="page-head">
    <div>
        <h1>คุณลักษณะ / อ่าน คิดวิเคราะห์ และเขียน</h1>
        <div class="sub">{{ $term?->label() }} · ใช้ใน ปพ.1 และสมุดรายงานผล (ปพ.6)</div>
    </div>
</div>

<form method="GET" class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div style="min-width:180px">
            <label class="form-label">ภาคเรียน</label>
            <select name="term" class="form-select" data-autosubmit>
                @foreach ($terms as $t)<option value="{{ $t->id }}" @selected($term?->id === $t->id)>{{ $t->label() }}</option>@endforeach
            </select>
        </div>
        <div style="min-width:160px">
            <label class="form-label">ห้องเรียน</label>
            <select name="classroom" class="form-select" data-autosubmit>
                @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected($classroom?->id === $c->id)>{{ $c->name() }}</option>@endforeach
            </select>
        </div>
        <div class="ms-auto small text-muted">ระดับ: 3 ดีเยี่ยม · 2 ดี · 1 ผ่าน · 0 ไม่ผ่าน</div>
    </div>
</form>

@if (! $classroom)
    <div class="card"><div class="empty"><i class="bi bi-door-closed"></i>ไม่มีห้องที่คุณเป็นครูประจำชั้นในปีการศึกษานี้</div></div>
@else
<form method="POST" action="{{ route('evaluations.save', $classroom) }}" id="evalForm">
    @csrf
    <input type="hidden" name="term_id" value="{{ $term->id }}">
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 eval-grid">
                <thead>
                    <tr class="small">
                        <th class="sticky-col" rowspan="2">เลขที่ · ชื่อ</th>
                        <th class="text-center" colspan="{{ count(\App\Support\Evaluation::TRAITS) + 1 }}">คุณลักษณะอันพึงประสงค์</th>
                        <th class="text-center fw-normal lh-sm" rowspan="2" style="width:100px;min-width:100px;white-space:normal">อ่าน คิดวิเคราะห์ และเขียน</th>
                    </tr>
                    <tr class="small">
                        @foreach (\App\Support\Evaluation::TRAITS as $no => $name)
                            <th class="text-center fw-normal lh-sm" title="{{ $name }}" style="width:94px;min-width:94px;white-space:normal">{{ $no }}. {{ $name }}</th>
                        @endforeach
                        <th class="text-center" style="width:76px">สรุป</th>
                    </tr>
                    <tr class="table-light small">
                        <td class="sticky-col text-muted">ตั้งทุกคนในคอลัมน์</td>
                        @foreach (\App\Support\Evaluation::TRAITS as $no => $name)
                            <td><select class="form-select form-select-sm" data-fill="t{{ $no }}" aria-label="ตั้งทุกคน ข้อ {{ $no }}"><option value="">-</option>@foreach ($levels as $v => $l)<option value="{{ $v }}" title="{{ $l }}">{{ $v }}</option>@endforeach</select></td>
                        @endforeach
                        <td></td>
                        <td><select class="form-select form-select-sm" data-fill="rtw" aria-label="ตั้งทุกคน อ่านคิดเขียน"><option value="">-</option>@foreach ($levels as $v => $l)<option value="{{ $v }}" title="{{ $l }}">{{ $v }}</option>@endforeach</select></td>
                    </tr>
                </thead>
                <tbody>
                @forelse ($students as $s)
                    @php($e = $evaluations[$s->id] ?? null)
                    <tr data-eval-row>
                        <td class="sticky-col text-nowrap"><span class="text-muted me-1">{{ $s->number }}</span> {{ $s->fullName() }}</td>
                        @foreach (\App\Support\Evaluation::TRAITS as $no => $name)
                            @php($cur = $e?->trait($no))
                            <td><select name="eval[{{ $s->id }}][t][{{ $no }}]" class="form-select form-select-sm" data-col="t{{ $no }}" data-trait>
                                <option value="">-</option>
                                @foreach ($levels as $v => $l)<option value="{{ $v }}" title="{{ $l }}" @selected($cur === $v)>{{ $v }}</option>@endforeach
                            </select></td>
                        @endforeach
                        <td class="text-center" data-summary>
                            @php($sum = $e?->traitsSummary())
                            <span class="badge bg-{{ \App\Support\Evaluation::color($sum) }}">{{ \App\Support\Evaluation::label($sum) }}</span>
                        </td>
                        <td><select name="eval[{{ $s->id }}][rtw]" class="form-select form-select-sm" data-col="rtw">
                            <option value="">-</option>
                            @foreach ($levels as $v => $l)<option value="{{ $v }}" title="{{ $l }}" @selected($e?->rtw === $v)>{{ $v }}</option>@endforeach
                        </select></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count(\App\Support\Evaluation::TRAITS) + 3 }}"><div class="empty"><i class="bi bi-people"></i>ห้องนี้ยังไม่มีนักเรียน</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center mt-3">
        <button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึก</button>
        <span class="small text-muted">สรุปคุณลักษณะคำนวณเมื่อประเมินครบ 8 ข้อ ตามเกณฑ์ของหลักสูตรแกนกลางฯ</span>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('evalForm');
    if (!form) return;
    const labels = @json($levels);
    const colors = { 3: 'success', 2: 'info', 1: 'warning', 0: 'danger' };
    // เกณฑ์เดียวกับ App\Support\Evaluation::summarize()
    const summarize = (vals) => {
        if (vals.some((v) => v === '')) return null;
        const n = vals.map(Number);
        if (Math.min(...n) === 0) return 0;
        const excellent = n.filter((v) => v === 3).length, goodUp = n.filter((v) => v >= 2).length;
        if (goodUp === n.length) return excellent >= 5 ? 3 : 2;
        return goodUp >= 5 ? 2 : 1;
    };
    const refresh = (tr) => {
        const s = summarize([...tr.querySelectorAll('[data-trait]')].map((el) => el.value));
        tr.querySelector('[data-summary]').innerHTML = `<span class="badge bg-${s === null ? 'secondary' : colors[s]}">${s === null ? '-' : labels[s]}</span>`;
    };
    form.addEventListener('change', (e) => {
        const fill = e.target.dataset.fill;
        if (fill !== undefined) {
            if (e.target.value === '') return;
            form.querySelectorAll(`[data-col="${fill}"]`).forEach((el) => { el.value = e.target.value; });
            form.querySelectorAll('[data-eval-row]').forEach(refresh);
            e.target.value = '';
            return;
        }
        const tr = e.target.closest('[data-eval-row]');
        if (tr) refresh(tr);
    });
})();
</script>
@endpush
