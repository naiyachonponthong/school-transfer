@extends('layouts.app')
@section('title', 'ประเมินพัฒนาการปฐมวัย')

@section('content')
@php
    $P = \App\Support\Preschool::class;
    $levels = $P::LEVELS;
@endphp
<div class="page-head">
    <div>
        <h1>ประเมินพัฒนาการปฐมวัย</h1>
        <div class="sub">{{ $term?->label() }} · พัฒนาการ 4 ด้าน 12 มาตรฐาน ตามหลักสูตรการศึกษาปฐมวัย พ.ศ. 2560</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('preschool.daily', array_filter(['classroom' => $classroom?->id])) }}" class="btn btn-light border"><i class="bi bi-cup-straw"></i>บันทึกประจำวัน</a>
        @if ($classroom && $term)
            <a href="{{ route('preschool.report', [$classroom, 'term' => $term->id]) }}" class="btn btn-primary"><i class="bi bi-printer"></i>พิมพ์ ปฐ.03 ทั้งห้อง</a>
        @endif
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
        <div class="ms-auto small text-muted">ระดับ: 3 ดี · 2 พอใช้ · 1 ควรส่งเสริม</div>
    </div>
</form>

<details class="card mb-3">
    <summary class="card-body py-2 d-flex align-items-center gap-2" style="cursor:pointer">
        <i class="bi bi-list-ol"></i><b>มาตรฐานคุณลักษณะที่พึงประสงค์ 12 ข้อ</b>
        @if (auth()->user()->hasPermission('academics.manage'))
            <button type="button" class="btn btn-sm btn-light border ms-auto" data-bs-toggle="modal" data-bs-target="#standardsModal"><i class="bi bi-pencil"></i>แก้ชื่อมาตรฐาน</button>
        @endif
    </summary>
    <div class="card-body pt-0 small">
        <div class="row g-3">
            @foreach ($P::DOMAINS as [$dname, $nos])
                <div class="col-md-6 col-xl-3">
                    <div class="fw-semibold mb-1">{{ $dname }}</div>
                    @foreach ($nos as $no)<div class="mb-1"><b>{{ $no }}.</b> {{ $standards[$no] }}</div>@endforeach
                </div>
            @endforeach
        </div>
    </div>
</details>

@if (! $classroom)
    <div class="card"><div class="empty"><i class="bi bi-door-closed"></i>ไม่มีห้องอนุบาลที่คุณเป็นครูประจำชั้นในปีการศึกษานี้</div></div>
@else
<form method="POST" action="{{ route('preschool.save', $classroom) }}" id="psForm">
    @csrf
    <input type="hidden" name="term_id" value="{{ $term->id }}">
    <div class="card">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 eval-grid ps-grid">
                <thead>
                    <tr class="small">
                        <th class="sticky-col" rowspan="2">เลขที่ · ชื่อ</th>
                        @foreach ($P::DOMAINS as [$dname, $nos])<th class="text-center border-start" colspan="{{ count($nos) }}">{{ $dname }}</th>@endforeach
                        <th class="text-center border-start" rowspan="2" style="min-width:130px">สรุป 4 ด้าน</th>
                        <th class="border-start" rowspan="2" style="min-width:220px">ความเห็นครู</th>
                    </tr>
                    <tr class="small">
                        @foreach ($P::DOMAINS as [, $nos])
                            @foreach ($nos as $i => $no)<th class="text-center fw-normal {{ $i === 0 ? 'border-start' : '' }}" title="{{ $standards[$no] }}" style="width:66px;min-width:66px">มฐ.{{ $no }}</th>@endforeach
                        @endforeach
                    </tr>
                    <tr class="table-light small">
                        <td class="sticky-col text-muted">ตั้งทุกคนในคอลัมน์</td>
                        @foreach ($P::DOMAINS as [, $nos])
                            @foreach ($nos as $i => $no)
                                <td class="{{ $i === 0 ? 'border-start' : '' }}"><select class="form-select form-select-sm" data-fill="l{{ $no }}" aria-label="ตั้งทุกคน มาตรฐาน {{ $no }}"><option value="">-</option>@foreach ($levels as $v => $l)<option value="{{ $v }}" title="{{ $l }}">{{ $v }}</option>@endforeach</select></td>
                            @endforeach
                        @endforeach
                        <td class="border-start"></td><td class="border-start"></td>
                    </tr>
                </thead>
                <tbody>
                @forelse ($students as $s)
                    @php($a = $assessments[$s->id] ?? null)
                    <tr data-ps-row>
                        <td class="sticky-col text-nowrap"><span class="text-muted me-1">{{ $s->number }}</span> {{ $s->fullName() }}</td>
                        @foreach ($P::DOMAINS as $dkey => [, $nos])
                            @foreach ($nos as $i => $no)
                                @php($cur = $a?->level($no))
                                <td class="{{ $i === 0 ? 'border-start' : '' }}"><select name="ps[{{ $s->id }}][l][{{ $no }}]" class="form-select form-select-sm" data-col="l{{ $no }}" data-domain="{{ $dkey }}" aria-label="มาตรฐาน {{ $no }} {{ $s->fullName() }}">
                                    <option value="">-</option>
                                    @foreach ($levels as $v => $l)<option value="{{ $v }}" title="{{ $l }}" @selected($cur === $v)>{{ $v }}</option>@endforeach
                                </select></td>
                            @endforeach
                        @endforeach
                        <td class="border-start" data-summary>
                            @foreach (($a?->domains() ?? array_fill_keys(array_keys($P::DOMAINS), null)) as $dkey => $lv)
                                <span class="badge bg-{{ $P::color($lv) }}" data-sum="{{ $dkey }}" title="{{ $P::DOMAINS[$dkey][0] }}">{{ $P::DOMAINS[$dkey][2] }} {{ $lv ?? '-' }}</span>
                            @endforeach
                        </td>
                        <td class="border-start"><textarea name="ps[{{ $s->id }}][comment]" rows="1" class="form-control form-control-sm" maxlength="1000" aria-label="ความเห็นครู {{ $s->fullName() }}">{{ $a?->comment }}</textarea></td>
                    </tr>
                @empty
                    <tr><td colspan="16"><div class="empty"><i class="bi bi-people"></i>ห้องนี้ยังไม่มีนักเรียน</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="d-flex gap-2 align-items-center mt-3">
        <button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึก</button>
        <span class="small text-muted">สรุปแต่ละด้านคำนวณจากค่าเฉลี่ยของมาตรฐานในด้านนั้น เมื่อประเมินครบทุกมาตรฐาน</span>
    </div>
</form>
@endif

@if (auth()->user()->hasPermission('academics.manage'))
<div class="modal fade" id="standardsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" action="{{ route('preschool.standards') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">แก้ชื่อมาตรฐาน</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body">
                <p class="small text-muted">แก้ให้ตรงกับหลักสูตรสถานศึกษาได้ จำนวนมาตรฐานและการจัดกลุ่ม 4 ด้านคงที่ เว้นว่าง = ใช้ชื่อตามหลักสูตรแกนกลาง</p>
                @foreach ($P::DOMAINS as [$dname, $nos])
                    <div class="fw-semibold mt-3 mb-1">{{ $dname }}</div>
                    @foreach ($nos as $no)
                        <div class="input-group input-group-sm mb-2"><span class="input-group-text" style="width:64px">มฐ.{{ $no }}</span><input name="standards[{{ $no }}]" class="form-control" maxlength="255" value="{{ $standards[$no] }}"></div>
                    @endforeach
                @endforeach
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึก</button></div>
        </form>
    </div>
</div>
@endif
@endsection

@push('head')
<style>
    /* ช่องเลือกระดับแคบ: ลดที่ว่างของลูกศรให้เห็นตัวเลข */
    .ps-grid .form-select-sm { padding-left: .45rem; padding-right: 1.35rem; background-position: right .3rem center; min-width: 54px; }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const form = document.getElementById('psForm');
    if (!form) return;
    const colors = { 3: 'success', 2: 'info', 1: 'warning' };
    // เกณฑ์เดียวกับ App\Support\Preschool::summarize()
    const summarize = (vals) => {
        if (!vals.length || vals.some((v) => v === '')) return null;
        const avg = vals.reduce((a, v) => a + Number(v), 0) / vals.length;
        return avg >= 2.5 ? 3 : (avg >= 1.5 ? 2 : 1);
    };
    const refresh = (tr) => {
        tr.querySelectorAll('[data-sum]').forEach((b) => {
            const s = summarize([...tr.querySelectorAll(`[data-domain="${b.dataset.sum}"]`)].map((el) => el.value));
            b.className = `badge bg-${s === null ? 'secondary' : colors[s]}`;
            b.textContent = b.textContent.replace(/\S+$/, s === null ? '-' : s);
        });
    };
    form.addEventListener('change', (e) => {
        const fill = e.target.dataset.fill;
        if (fill !== undefined) {
            if (e.target.value === '') return;
            form.querySelectorAll(`[data-col="${fill}"]`).forEach((el) => { el.value = e.target.value; });
            form.querySelectorAll('[data-ps-row]').forEach(refresh);
            e.target.value = '';
            return;
        }
        const tr = e.target.closest('[data-ps-row]');
        if (tr) refresh(tr);
    });
})();
</script>
@endpush
