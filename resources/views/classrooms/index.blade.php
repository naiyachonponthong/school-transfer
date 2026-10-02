@extends('layouts.app')
@section('title', 'ห้องเรียน')

@section('content')
<div class="page-head">
    <div><h1>ห้องเรียน ปีการศึกษา {{ $year }}</h1><div class="sub">{{ $classrooms->count() }} ห้อง · นักเรียน {{ $classrooms->sum('students_count') }} คน</div></div>
    <div class="actions">
        <form method="GET"><select name="year" class="form-select" data-autosubmit>
            @foreach ($years as $y)<option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>@endforeach
            <option value="{{ $years->max() + 1 }}">{{ $years->max() + 1 }} (ใหม่)</option>
        </select></form>
        <button class="btn btn-light border" data-bs-toggle="modal" data-bs-target="#promote"><i class="bi bi-arrow-up-circle"></i> เลื่อนชั้นขึ้นปีใหม่</button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoom"><i class="bi bi-plus-lg"></i> เพิ่มห้อง</button>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-cards align-middle">
            <thead><tr><th>ห้อง</th><th class="text-center">นักเรียน</th><th class="text-center">ชาย/หญิง</th><th>ครูประจำชั้น</th><th>ครูประจำชั้นร่วม</th><th></th></tr></thead>
            <tbody>
            @forelse ($classrooms as $c)
                @php($f = 'cr'.$c->id)
                <tr>
                    <td class="text-nowrap">
                        <div class="d-flex gap-1 align-items-center">
                            <input form="{{ $f }}" name="level" value="{{ $c->level }}" class="form-control form-control-sm" style="width:70px" list="levels">
                            /<input form="{{ $f }}" name="room" value="{{ $c->room }}" type="number" min="1" class="form-control form-control-sm" style="width:60px">
                        </div>
                    </td>
                    <td class="text-center"><a href="{{ route('students.index', ['classroom' => $c->id]) }}">{{ $c->students_count }}</a></td>
                    <td class="text-center small text-muted">{{ $c->boys_count }} / {{ $c->girls_count }}</td>
                    <td><select form="{{ $f }}" name="homeroom_teacher_id" class="form-select form-select-sm"><option value="">-</option>@foreach ($teachers as $t)<option value="{{ $t->id }}" @selected($c->homeroom_teacher_id === $t->id)>{{ $t->name }}</option>@endforeach</select></td>
                    <td><select form="{{ $f }}" name="co_teacher_id" class="form-select form-select-sm"><option value="">-</option>@foreach ($teachers as $t)<option value="{{ $t->id }}" @selected($c->co_teacher_id === $t->id)>{{ $t->name }}</option>@endforeach</select></td>
                    <td class="text-nowrap text-end">
                        <form id="{{ $f }}" method="POST" action="{{ route('classrooms.update', $c) }}" class="d-inline">
                            @csrf @method('PUT')
                            <button class="btn btn-sm btn-light border" title="บันทึก"><i class="bi bi-check-lg"></i></button>
                        </form>
                            @if ($c->students_count === 0)
                                <form method="POST" action="{{ route('classrooms.destroy', $c) }}" class="d-inline" data-confirm="ลบห้อง {{ $c->name() }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash"></i></button></form>
                            @endif
                        </td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty"><i class="bi bi-door-closed"></i>ยังไม่มีห้องเรียนในปีนี้</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<datalist id="levels">@foreach (\App\Models\Classroom::LEVELS as $l)<option>{{ $l }}</option>@endforeach</datalist>

<div class="modal fade" id="addRoom" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('classrooms.store') }}" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">เพิ่มห้องเรียน</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-3">
            <div class="col-4"><label class="form-label">ปีการศึกษา</label><input name="year" type="number" value="{{ $year }}" class="form-control" required></div>
            <div class="col-4"><label class="form-label">ชั้น</label><select name="level" class="form-select">@foreach (\App\Models\Classroom::LEVELS as $l)<option>{{ $l }}</option>@endforeach</select></div>
            <div class="col-4"><label class="form-label">จำนวนห้อง</label><input name="rooms" type="number" min="1" max="30" value="1" class="form-control" required></div>
            <div class="col-12 small text-muted">เช่น ม.1 จำนวน 4 ห้อง → สร้าง ม.1/1 ถึง ม.1/4 ให้ทันที</div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">เพิ่ม</button></div>
    </form></div>
</div>

<div class="modal fade" id="promote" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('classrooms.promote') }}" class="modal-content" data-confirm="ยืนยันเลื่อนชั้นนักเรียนทั้งหมดจากปี {{ $year }} ไปปี {{ $year + 1 }}?">
        @csrf
        <input type="hidden" name="from_year" value="{{ $year }}">
        <div class="modal-header"><h5 class="modal-title">เลื่อนชั้น {{ $year }} → {{ $year + 1 }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <p class="small">ระบบจะสร้างห้องปี {{ $year + 1 }} และย้ายนักเรียนขึ้นชั้นถัดไปโดยใช้เลขห้องเดิม (ป.1/2 → ป.2/2) ข้อมูลเช็คชื่อและคะแนนของปีเก่ายังอยู่ครบ</p>
            <label class="form-label">ชั้นที่จบการศึกษา (ไม่เลื่อนต่อ)</label>
            <div class="d-flex flex-wrap gap-3">
                @foreach (['อ.3', 'ป.6', 'ม.3', 'ม.6'] as $l)
                    <label class="small"><input type="checkbox" class="form-check-input" name="graduate_levels[]" value="{{ $l }}" @checked(in_array($l, ['ป.6', 'ม.6']))> {{ $l }}</label>
                @endforeach
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">เลื่อนชั้น</button></div>
    </form></div>
</div>
@endsection
