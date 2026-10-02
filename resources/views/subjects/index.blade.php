@extends('layouts.app')
@section('title', 'รายวิชา')

@section('content')
<div class="page-head">
    <div><h1>รายวิชาในหลักสูตร</h1><div class="sub">{{ $subjects->count() }} รายวิชา</div></div>
    <div class="actions"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subjectNew"><i class="bi bi-plus-lg"></i> เพิ่มรายวิชา</button></div>
</div>
<form class="mb-3" method="GET"><input name="q" value="{{ request('q') }}" class="form-control" style="max-width:320px" placeholder="ค้นหารหัส/ชื่อวิชา"></form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>รหัส</th><th>ชื่อวิชา</th><th>ประเภท</th><th>กลุ่มสาระ</th><th class="text-center">หน่วยกิต</th><th class="text-center">ชั่วโมง</th><th class="text-center">เปิดสอน</th><th></th></tr></thead>
            <tbody>
            @forelse ($subjects as $s)
                <tr>
                    <td class="fw-semibold">{{ $s->code }}</td><td>{{ $s->name }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $s->typeLabel() }}</span>@if($s->activity_kind)<div class="small text-muted">{{ $s->activityKindLabel() }}</div>@endif</td>
                    <td class="small text-muted">{{ $s->group }}</td>
                    <td class="text-center">{{ $s->credit }}</td>
                    <td class="text-center">{{ $s->hours ?? '-' }}</td>
                    <td class="text-center">{{ $s->courses_count }}</td>
                    <td class="text-end"><button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#subject{{ $s->id }}"><i class="bi bi-pencil"></i></button></td>
                </tr>
            @empty
                <tr><td colspan="8"><div class="empty"><i class="bi bi-book"></i>ยังไม่มีรายวิชา</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@foreach ($subjects->push(new \App\Models\Subject(['type' => 'basic', 'credit' => 1])) as $s)
<div class="modal fade" id="subject{{ $s->id ?? 'New' }}" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" action="{{ $s->exists ? route('subjects.update', $s) : route('subjects.store') }}">
            @csrf @if ($s->exists) @method('PUT') @endif
            <div class="modal-header"><h5 class="modal-title">{{ $s->exists ? 'แก้ไขรายวิชา' : 'เพิ่มรายวิชา' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body row g-3">
                <div class="col-4"><label class="form-label">รหัสวิชา</label><input name="code" value="{{ $s->code }}" class="form-control" required placeholder="ท21101"></div>
                <div class="col-8"><label class="form-label">ชื่อวิชา</label><input name="name" value="{{ $s->name }}" class="form-control" required></div>
                <div class="col-6"><label class="form-label">ประเภท</label><select name="type" class="form-select" data-subject-type>@foreach (\App\Models\Subject::TYPES as $k => $v)<option value="{{ $k }}" @selected($s->type === $k)>{{ $v }}</option>@endforeach</select></div>
                <div class="col-3"><label class="form-label">หน่วยกิต</label><input name="credit" type="number" step="0.5" min="0" value="{{ $s->credit }}" class="form-control" required></div>
                <div class="col-3"><label class="form-label">ชั่วโมง</label><input name="hours" type="number" min="0" value="{{ $s->hours }}" class="form-control" placeholder="เช่น 60"></div>
                <div class="col-12" data-activity-kind @if($s->type !== 'activity') hidden @endif><label class="form-label">ประเภทกิจกรรม</label><select name="activity_kind" class="form-select"><option value="">-</option>@foreach (\App\Models\Subject::ACTIVITY_KINDS as $k => $v)<option value="{{ $k }}" @selected($s->activity_kind === $k)>{{ $v }}</option>@endforeach</select></div>
                <div class="col-12 small text-muted">ประถมใช้ "ชั่วโมง" ใน ปพ.1 (หน่วยกิตใส่ 0 ได้) · มัธยมใช้หน่วยกิต · กิจกรรมพัฒนาผู้เรียนใส่หน่วยกิต 0 และระบุชั่วโมง ผลจะเป็น ผ/มผ</div>
                <div class="col-12"><label class="form-label">กลุ่มสาระ</label><select name="group" class="form-select"><option value="">-</option>@foreach (\App\Models\Subject::GROUPS as $g)<option @selected($s->group === $g)>{{ $g }}</option>@endforeach</select></div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div>
        </form>
        @if ($s->exists && $s->courses_count === 0)
            <form method="POST" action="{{ route('subjects.destroy', $s) }}" class="px-3 pb-3" data-confirm="ลบวิชานี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger px-0">ลบรายวิชา</button></form>
        @endif
    </div></div>
</div>
@endforeach
@endsection

@push('scripts')
<script>
document.querySelectorAll('[data-subject-type]').forEach((sel) => sel.addEventListener('change', () => {
    sel.closest('form').querySelector('[data-activity-kind]').hidden = sel.value !== 'activity';
}));
</script>
@endpush
