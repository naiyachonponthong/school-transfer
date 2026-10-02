@extends('layouts.app')
@section('title', 'กระดาษคำตอบ · '.$exam->title)

@section('content')
@include('exams._nav')

@php
    $rooms = $exam->courses->pluck('classroom')->filter()->sortBy(fn ($c) => [$c->level_order, $c->room])->values();
    // กระดาษมีช่องเลขประจำตัว 5 หลัก — รหัสยาวกว่านั้นระบายไม่ได้ ต้องระบุเจ้าของแผ่นตอนตรวจทาน
    $longCodes = $students->filter(fn ($s) => strlen(ltrim(preg_replace('/\D/', '', (string) $s['code']), '0')) > 5)->count();
@endphp
@if ($longCodes)
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> นักเรียน {{ $longCodes }} คนมีเลขประจำตัวเกิน 5 หลัก กระดาษระบายได้แค่ 5 หลักท้าย — ระบบจะส่งแผ่นของคนกลุ่มนี้เข้าคิวตรวจทานให้ระบุเจ้าของ</div>
@endif
<div class="row g-3">
    <div class="col-lg-4">
        <form class="card" id="shForm" data-n="{{ $exam->n_items }}" data-groups='@json(\App\Models\Exam::SEAT_GROUPS)'
              data-students='@json($students)' data-school="{{ school('school_name') }}"
              data-logo="{{ school('logo') ? asset('storage/'.school('logo')) : '' }}" onsubmit="return false">
            <div class="card-header"><i class="bi bi-sliders"></i> รูปแบบ</div>
            <div class="card-body">
                <label class="form-label">แบบที่พิมพ์</label>
                <div class="d-grid gap-2 mb-3">
                    <label class="border rounded-3 p-2 d-flex gap-2 align-items-start">
                        <input type="radio" name="mode" value="roster" class="form-check-input mt-1" checked>
                        <span><b>รายบุคคล</b> <span class="badge bg-success-subtle text-success-emphasis">แนะนำ</span><br><small class="text-muted">พิมพ์ชื่อ + ระบายเลขประจำตัวและเลขที่ให้แล้ว นักเรียนระบายแค่คำตอบ ลดการระบายรหัสผิด</small></span>
                    </label>
                    <label class="border rounded-3 p-2 d-flex gap-2 align-items-start">
                        <input type="radio" name="mode" value="blank" class="form-check-input mt-1">
                        <span><b>แผ่นเปล่า</b><br><small class="text-muted">นักเรียนเขียนชื่อ ระบายเลขประจำตัว 5 หลักและเลขที่เอง</small></span>
                    </label>
                </div>
                <div data-show="roster" class="mb-3">
                    <label class="form-label">ห้อง</label>
                    <select name="room" class="form-select">
                        <option value="">ทุกห้อง ({{ $students->count() }} แผ่น)</option>
                        @foreach ($rooms as $r)<option value="{{ $r->id }}">{{ $r->name() }} ({{ $students->where('classroom_id', $r->id)->count() }} แผ่น)</option>@endforeach
                    </select>
                </div>
                <div data-show="blank" class="mb-3 d-none">
                    <label class="form-label">จำนวนแผ่น</label>
                    <input type="number" name="copies" min="1" max="400" value="{{ max(1, $students->count()) }}" class="form-control">
                </div>
            </div>
            <div class="card-header border-top"><i class="bi bi-pencil-square"></i> พิมพ์ลงหัวกระดาษ <span class="ms-auto small text-muted fw-normal">เว้นว่าง = ให้นักเรียนเขียน</span></div>
            <div class="card-body row g-2">
                <div class="col-12"><label class="form-label">รายวิชา</label><input name="subject" class="form-control" value="{{ $exam->subject->code }} {{ $exam->subject->name }}"></div>
                <div class="col-7"><label class="form-label">วิชา (ในกรอบ)</label><input name="subject_short" class="form-control" value="{{ $exam->title }}"></div>
                <div class="col-5"><label class="form-label">วันสอบ</label><input name="exam_date" class="form-control" value="{{ $exam->exam_date ? thai_date($exam->exam_date) : '' }}"></div>
                <div class="col-12"><label class="form-check"><input type="checkbox" name="show_school" class="form-check-input" checked> พิมพ์ชื่อโรงเรียนและตรา</label></div>
            </div>
            <div class="card-body border-top d-grid gap-2">
                <button class="btn btn-primary btn-lg" type="button" id="shPrint"><i class="bi bi-printer"></i> พิมพ์ / บันทึก PDF</button>
                <div class="small text-muted"><i class="bi bi-exclamation-circle"></i> ตั้งเครื่องพิมพ์: A4 · ขนาด <b>100% (ขนาดจริง)</b> · ไม่ย่อให้พอดีหน้า · ปิดหัว/ท้ายกระดาษ</div>
                <div class="small text-muted"><i class="bi bi-info-circle"></i> ห้ามเขียนทับ/เย็บ/เจาะ บริเวณสี่เหลี่ยมดำ 4 มุม ขีดดำขอบขวา และแถบ 10 ช่องด้านล่าง — เครื่องใช้อ่านตำแหน่ง</div>
            </div>
        </form>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><i class="bi bi-eye"></i> ตัวอย่าง <span class="ms-auto small text-muted fw-normal" id="shInfo"></span></div>
            <div class="sheet-preview" id="shPreview"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('exams._scripts', ['files' => ['sheet-layout.js', 'sheet.js']])
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('shForm'), preview = document.getElementById('shPreview'), info = document.getElementById('shInfo');
    const students = JSON.parse(form.dataset.students), n = Number(form.dataset.n), groups = JSON.parse(form.dataset.groups);
    const read = () => {
        const f = new FormData(form), mode = f.get('mode');
        form.querySelectorAll('[data-show]').forEach((el) => el.classList.toggle('d-none', el.dataset.show !== mode));
        const school = f.get('show_school');
        const base = { n, groups, subject: f.get('subject').trim(), subject_short: f.get('subject_short').trim(), exam_date: f.get('exam_date').trim(),
            school: school ? form.dataset.school : '', logo: school ? form.dataset.logo : '' };
        if (mode === 'blank') {
            const count = Math.max(1, Math.min(400, Number(f.get('copies')) || 1));
            return { count, first: base, each: () => base };
        }
        const list = students.filter((s) => !f.get('room') || String(s.classroom_id) === f.get('room'));
        const mk = (s) => Object.assign({}, base, { classroom: s.classroom, footer: s.classroom, student: s });
        return { count: list.length, first: list[0] ? mk(list[0]) : null, each: (i) => mk(list[i]) };
    };
    const render = () => {
        const s = read();
        if (!s.first) { preview.innerHTML = '<div class="empty"><i class="bi bi-people"></i>ห้องนี้ยังไม่มีนักเรียน</div>'; info.textContent = ''; return; }
        preview.innerHTML = SGSheet.svg(s.first);
        info.textContent = s.count + ' แผ่น';
    };
    let t;
    form.addEventListener('input', () => { clearTimeout(t); t = setTimeout(render, 200); });
    form.addEventListener('change', render);
    document.getElementById('shPrint').addEventListener('click', () => {
        const s = read(), pages = [];
        for (let i = 0; i < s.count; i++) pages.push(SGSheet.svg(s.each(i)));
        if (pages.length) SGSheet.print(pages);
    });
    render();
});
</script>
@endpush
