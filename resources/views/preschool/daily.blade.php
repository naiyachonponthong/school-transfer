@extends('layouts.app')
@section('title', 'บันทึกประจำวันปฐมวัย')

@section('content')
@php($cols = \App\Support\Preschool::DAILY)
<div class="page-head">
    <div>
        <h1>บันทึกประจำวันปฐมวัย</h1>
        <div class="sub">{{ thai_date($date) }}@if ($logs->isNotEmpty()) · บันทึกแล้ว@endif</div>
    </div>
    <a href="{{ route('preschool.index', array_filter(['classroom' => $classroom?->id])) }}" class="btn btn-light border"><i class="bi bi-stars"></i>ประเมินพัฒนาการ</a>
</div>

<form method="GET" class="card mb-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-end">
        <div>
            <label class="form-label">วันที่</label>
            <input type="date" name="date" class="form-control" value="{{ $date->toDateString() }}" max="{{ today()->toDateString() }}" data-autosubmit>
        </div>
        <div style="min-width:160px">
            <label class="form-label">ห้องเรียน</label>
            <select name="classroom" class="form-select" data-autosubmit>
                @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected($classroom?->id === $c->id)>{{ $c->name() }}</option>@endforeach
            </select>
        </div>
    </div>
</form>

@if (! $classroom)
    <div class="card"><div class="empty"><i class="bi bi-door-closed"></i>ไม่มีห้องอนุบาลที่คุณเป็นครูประจำชั้นในปีการศึกษานี้</div></div>
@else
<form method="POST" action="{{ route('preschool.daily.save', $classroom) }}" id="dailyForm">
    @csrf
    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="small">
                        <th>เลขที่ · ชื่อ</th>
                        @foreach ($cols as $k => [$label, $icon])
                            <th class="text-center" style="width:120px">
                                <label class="d-flex flex-column align-items-center gap-1 mb-0" style="cursor:pointer">
                                    <span><i class="bi bi-{{ $icon }}"></i> {{ $label }}</span>
                                    <input type="checkbox" class="form-check-input" data-all="{{ $k }}" aria-label="เลือกทุกคน {{ $label }}">
                                </label>
                            </th>
                        @endforeach
                        <th style="min-width:200px">หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($students as $s)
                    @php($log = $logs[$s->id] ?? null)
                    <tr>
                        <td class="text-nowrap"><span class="text-muted me-1">{{ $s->number }}</span> {{ $s->fullName() }}</td>
                        @foreach ($cols as $k => [$label])
                            <td class="text-center"><input type="checkbox" class="form-check-input fs-5" name="log[{{ $s->id }}][{{ $k }}]" value="1" data-col="{{ $k }}" @checked($log?->{$k}) aria-label="{{ $label }} {{ $s->fullName() }}"></td>
                        @endforeach
                        <td><input name="log[{{ $s->id }}][note]" class="form-control form-control-sm" maxlength="255" value="{{ $log?->note }}" placeholder="เช่น ไม่สบาย กลับก่อน"></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($cols) + 2 }}"><div class="empty"><i class="bi bi-people"></i>ห้องนี้ยังไม่มีนักเรียน</div></td></tr>
                @endforelse
                </tbody>
                @if ($students->isNotEmpty())
                    <tfoot class="table-light small">
                        <tr><td class="text-end">รวม</td>@foreach ($cols as $k => $c)<td class="text-center" data-count="{{ $k }}"></td>@endforeach<td></td></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
    <div class="mt-3"><button class="btn btn-primary btn-lg"><i class="bi bi-save"></i> บันทึก</button></div>
</form>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('dailyForm');
    if (!form) return;
    const total = form.querySelectorAll('tbody tr [data-col]').length / {{ count($cols) }};
    const refresh = () => form.querySelectorAll('[data-count]').forEach((td) => {
        const k = td.dataset.count;
        const n = form.querySelectorAll(`[data-col="${k}"]:checked`).length;
        td.textContent = `${n}/${total}`;
        const all = form.querySelector(`[data-all="${k}"]`);
        all.checked = n === total && total > 0; all.indeterminate = n > 0 && n < total;
    });
    form.addEventListener('change', (e) => {
        const k = e.target.dataset.all;
        if (k) form.querySelectorAll(`[data-col="${k}"]`).forEach((el) => { el.checked = e.target.checked; });
        refresh();
    });
    refresh();
})();
</script>
@endpush
