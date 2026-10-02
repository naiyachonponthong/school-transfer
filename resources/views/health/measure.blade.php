@extends('layouts.app')
@section('title', 'น้ำหนัก-ส่วนสูง')

@section('content')
<div class="page-head">
    <div><h1>ชั่งน้ำหนัก / วัดส่วนสูง</h1><div class="sub">กรอกทั้งห้องในหน้าเดียว · กด Enter เลื่อนลงคนถัดไป</div></div>
    <div class="actions">
        <form method="GET"><select name="classroom" class="form-select" data-autosubmit>
            @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected($classroom?->id === $c->id)>{{ $c->name() }}</option>@endforeach
        </select></form>
    </div>
</div>

<form method="POST" action="{{ route('health.measure.save') }}" class="card" id="measureForm">
    @csrf
    <div class="card-header">วันที่วัด <input type="date" name="measured_on" value="{{ today()->toDateString() }}" class="form-control form-control-sm w-auto ms-2"></div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>เลขที่</th><th>ชื่อ</th><th>ครั้งก่อน</th><th style="width:120px">น้ำหนัก (กก.)</th><th style="width:120px">ส่วนสูง (ซม.)</th><th>BMI</th></tr></thead>
            <tbody>
            @foreach ($students as $s)
                @php($prev = $s->measurements->first())
                <tr>
                    <td>{{ $s->number }}</td>
                    <td>{{ $s->fullName() }}</td>
                    <td class="small text-muted">
                        @if ($prev){{ thai_date($prev->measured_on) }} · {{ $prev->weight }} กก. / {{ $prev->height }} ซม.
                            <span class="badge bg-{{ $prev->bmiLabel()[1] }}-subtle text-{{ $prev->bmiLabel()[1] }}-emphasis">{{ $prev->bmiLabel()[0] }}</span>@else - @endif
                    </td>
                    <td><input name="rows[{{ $s->id }}][weight]" type="number" step="0.1" class="form-control form-control-sm m-in" data-bmi></td>
                    <td><input name="rows[{{ $s->id }}][height]" type="number" step="0.1" class="form-control form-control-sm m-in" data-bmi></td>
                    <td class="small bmi-out text-muted">-</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-transparent"><button class="btn btn-primary"><i class="bi bi-save"></i> บันทึก</button></div>
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const inputs = [...document.querySelectorAll('.m-in')];
    inputs.forEach((i, idx) => {
        i.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); inputs[idx + 2]?.focus(); } });
        i.addEventListener('input', () => {
            const tr = i.closest('tr'); const [w, h] = [...tr.querySelectorAll('.m-in')].map((x) => Number(x.value));
            tr.querySelector('.bmi-out').textContent = w && h ? (w / ((h / 100) ** 2)).toFixed(1) : '-';
        });
    });
});
</script>
@endpush
