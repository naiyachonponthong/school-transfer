@extends('layouts.app')
@section('title', 'คะแนนความประพฤติ')

@section('content')
<div class="page-head">
    <div>
        <h1>คะแนนความประพฤติ</h1>
        <div class="sub">คะแนนเริ่มต้น {{ \App\Models\Student::BASE_BEHAVIOR }} · เลือกนักเรียนได้หลายคนแล้วกดหัวข้อเดียวจบ</div>
    </div>
</div>

<form class="card mb-3" method="GET">
    <div class="card-body d-flex flex-wrap gap-2">
        <select name="classroom" class="form-select w-auto" data-autosubmit>
            <option value="">- เลือกห้องเพื่อบันทึกหลายคน -</option>
            @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected(request('classroom') == $c->id)>{{ $c->name() }}</option>@endforeach
        </select>
        <select name="type" class="form-select w-auto" data-autosubmit>
            <option value="">ทุกประเภท</option>
            <option value="good" @selected(request('type') === 'good')>ความดี (+)</option>
            <option value="bad" @selected(request('type') === 'bad')>หักคะแนน (−)</option>
        </select>
    </div>
</form>

@if ($roster->isNotEmpty())
<form method="POST" action="{{ route('behavior.store') }}" class="card mb-3">
    @csrf
    <div class="card-header">
        <i class="bi bi-people"></i> เลือกนักเรียน
        <label class="ms-auto small d-flex align-items-center gap-1"><input type="checkbox" class="form-check-input" data-check-all=".pick-student"> เลือกทั้งห้อง</label>
    </div>
    <div class="card-body">
        <div class="row g-2 mb-3">
            @foreach ($roster as $s)
                @php($sc = \App\Models\Student::BASE_BEHAVIOR + (int) $s->points_sum)
                <div class="col-6 col-md-4 col-xl-3">
                    <label class="d-flex align-items-center gap-2 border rounded-3 p-2 h-100" style="cursor:pointer">
                        <input type="checkbox" name="student_ids[]" value="{{ $s->id }}" class="form-check-input pick-student m-0">
                        <span class="flex-grow-1 small"><span class="text-muted">{{ $s->number }}.</span> {{ $s->nickname ?: $s->first_name }} {{ mb_substr($s->last_name, 0, 1) }}.</span>
                        <span class="badge bg-{{ $sc >= 80 ? 'success' : ($sc >= 60 ? 'warning' : 'danger') }}">{{ $sc }}</span>
                    </label>
                </div>
            @endforeach
        </div>
        <div class="border-top pt-3">
            <div class="small fw-semibold mb-2">เลือกแล้ว <span data-selected-count>0</span> คน — กดหัวข้อเพื่อบันทึกทันที</div>
            <div class="d-flex flex-wrap gap-2 mb-2">
                @foreach ($rules as $r)
                    <button name="behavior_rule_id" value="{{ $r->id }}" class="btn btn-sm {{ $r->points > 0 ? 'btn-outline-success' : 'btn-outline-danger' }}">{{ $r->name }} <b>{{ $r->points > 0 ? '+' : '' }}{{ $r->points }}</b></button>
                @endforeach
            </div>
            <input name="note" class="form-control form-control-sm" placeholder="หมายเหตุเพิ่มเติม (ไม่บังคับ)" style="max-width:420px">
        </div>
    </div>
</form>
@endif

<div class="card">
    <div class="card-header"><i class="bi bi-clock-history"></i> บันทึกล่าสุด</div>
    <div class="table-responsive">
        <table class="table table-cards align-middle">
            <thead><tr><th>วันที่</th><th>นักเรียน</th><th>เรื่อง</th><th class="text-center">คะแนน</th><th>ผู้บันทึก</th><th></th></tr></thead>
            <tbody>
            @forelse ($records as $r)
                <tr>
                    <td class="small">{{ thai_date($r->date) }}</td>
                    <td><a href="{{ route('students.show', $r->student) }}" class="text-body fw-semibold">{{ $r->student->fullName() }}</a> <span class="small text-muted">{{ $r->student->classroom?->name() }}</span></td>
                    <td>{{ $r->title }} @if($r->note)<div class="small text-muted">{{ $r->note }}</div>@endif</td>
                    <td class="text-center"><span class="badge {{ $r->points > 0 ? 'bg-success' : 'bg-danger' }}">{{ $r->points > 0 ? '+' : '' }}{{ $r->points }}</span></td>
                    <td class="small">{{ $r->recorder?->name }}</td>
                    <td>
                        @if (auth()->user()->isAdmin() || $r->recorded_by === auth()->id())
                            <form method="POST" action="{{ route('behavior.destroy', $r) }}" data-confirm="ลบรายการนี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-muted"><i class="bi bi-trash"></i></button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><i class="bi bi-award"></i>ยังไม่มีบันทึก</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $records->links() }}</div>
@endsection
