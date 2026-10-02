@extends('layouts.app')
@section('title', 'การบ้าน')

@section('content')
<x-page-banner title="การบ้าน / งาน" subtitle="สั่งงานแล้วผู้ปกครองได้รับแจ้งทาง LINE และส่งงานแทนลูกได้ในแอป" eyebrow="LEARNING TOGETHER">
    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#newHw" @disabled($courses->isEmpty())><i class="bi bi-plus-lg"></i> สั่งงาน</button>
</x-page-banner>

@if ($courses->isEmpty())
    <div class="alert alert-info">คุณยังไม่มีรายวิชาที่สอนในภาคเรียนนี้</div>
@endif

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>งาน</th><th>วิชา / ห้อง</th><th>กำหนดส่ง</th><th class="text-center">ส่งแล้ว</th><th class="text-center">ตรวจแล้ว</th></tr></thead>
            <tbody>
            @forelse ($assignments as $a)
                @php($n = $a->course->classroom->students()->count())
                <tr data-href="{{ route('homework.show', $a) }}" style="cursor:pointer">
                    <td><div class="fw-semibold">{{ $a->title }}</div>@if($a->assessment_id)<span class="badge bg-light text-dark border small">ผูกช่องคะแนน</span>@endif</td>
                    <td class="small">{{ $a->course->subject->name }} · {{ $a->course->classroom->name() }}</td>
                    <td class="small {{ $a->isClosed() ? 'text-muted' : 'text-primary fw-semibold' }}">{{ $a->due_at ? thai_datetime($a->due_at) : '-' }}</td>
                    <td class="text-center">
                        <div class="fw-semibold">{{ $a->submitted_count }}/{{ $n }}</div>
                        <div class="behavior-meter mx-auto" style="height:5px;width:70px"><span style="width:{{ $n ? $a->submitted_count / $n * 100 : 0 }}%;background:var(--sb-primary)"></span></div>
                    </td>
                    <td class="text-center">{{ $a->graded_count }}</td>
                </tr>
            @empty
                <tr><td colspan="5"><div class="empty"><i class="bi bi-journal-text"></i>ยังไม่มีงาน</div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $assignments->links() }}</div>

<div class="modal fade" id="newHw" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('homework.store') }}" enctype="multipart/form-data" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">สั่งงานใหม่</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-2">
            <div class="col-12">
                <label class="form-label">รายวิชา / ห้อง</label>
                <select name="course_id" class="form-select" id="hwCourse" required>
                    @foreach ($courses as $c)<option value="{{ $c->id }}">{{ $c->subject->name }} · {{ $c->classroom->name() }}</option>@endforeach
                </select>
            </div>
            <div class="col-12"><label class="form-label">ชื่องาน</label><input name="title" class="form-control" required placeholder="เช่น แบบฝึกหัดบทที่ 4 ข้อ 1-10"></div>
            <div class="col-12"><label class="form-label">รายละเอียด</label><textarea name="description" rows="3" class="form-control"></textarea></div>
            <div class="col-7"><label class="form-label">กำหนดส่ง</label><input type="datetime-local" name="due_at" value="{{ today()->addDays(3)->setTime(16, 0)->format('Y-m-d\TH:i') }}" class="form-control"></div>
            <div class="col-5"><label class="form-label">คะแนนเต็ม</label><input type="number" step="0.5" name="max_score" value="10" class="form-control"></div>
            <div class="col-12">
                <label class="form-label">ส่งคะแนนเข้าช่องคะแนน (ไม่บังคับ)</label>
                <select name="assessment_id" class="form-select" id="hwAssessment">
                    <option value="">- ไม่ผูก -</option>
                    @foreach ($courses as $c)
                        @foreach ($c->assessments as $as)<option value="{{ $as->id }}" data-course="{{ $c->id }}">{{ $as->name }} ({{ rtrim(rtrim(number_format($as->max_score, 2), '0'), '.') }})</option>@endforeach
                    @endforeach
                </select>
            </div>
            <div class="col-12"><label class="form-label">ไฟล์ประกอบ (ใบงาน/รูป)</label><input type="file" name="attachment" class="form-control"></div>
            <div class="col-12"><label class="form-check"><input type="checkbox" name="notify" value="1" class="form-check-input" checked> แจ้งผู้ปกครองทาง LINE</label></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">สั่งงาน</button></div>
    </form></div>
</div>
@endsection

@push('scripts')
<script>
// แสดงเฉพาะช่องคะแนนของรายวิชาที่เลือก
document.addEventListener('DOMContentLoaded', () => {
    const c = document.getElementById('hwCourse'), a = document.getElementById('hwAssessment');
    if (!c) return;
    const apply = () => { [...a.options].forEach((o) => { if (o.dataset.course) o.hidden = o.dataset.course !== c.value; }); if (a.selectedOptions[0]?.hidden) a.value = ''; };
    c.addEventListener('change', apply); apply();
});
</script>
@endpush
