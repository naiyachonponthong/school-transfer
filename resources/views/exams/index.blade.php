@extends('layouts.app')
@section('title', 'ตรวจข้อสอบ')

@section('content')
<div class="page-head">
    <div>
        <h1>ตรวจข้อสอบ</h1>
        <div class="sub">ข้อสอบปรนัย 4 ตัวเลือก · พิมพ์กระดาษคำตอบ แล้วใช้กล้องมือถือสแกน รู้คะแนนทันที · วิเคราะห์ข้อสอบแบบ EVANA</div>
    </div>
    <div class="actions">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newExam" @disabled($courses->isEmpty())><i class="bi bi-plus-lg"></i> สร้างชุดข้อสอบ</button>
    </div>
</div>

@if ($courses->isEmpty())
    <div class="alert alert-info">คุณยังไม่มีรายวิชาที่สอนในภาคเรียนนี้ — ชุดข้อสอบต้องผูกกับรายวิชา เพื่อใช้รายชื่อนักเรียนและส่งคะแนนเข้าสมุดคะแนนได้</div>
@endif

@if ($exams->isEmpty())
    <div class="card">
        <div class="card-body">
            <div class="row g-3 text-center">
                @foreach ([['bi-key', 'ใส่เฉลย', 'คลิก พิมพ์ หรือวางแถว KEY จาก EVANA/Excel'], ['bi-printer', 'พิมพ์กระดาษคำตอบ', 'แผ่นเปล่า หรือระบายรหัสนักเรียนให้แล้วทั้งห้อง'], ['bi-camera', 'สแกนด้วยมือถือ', 'วางกระดาษให้เห็น 4 มุม ระบบถ่ายและตรวจเอง'], ['bi-journal-check', 'ส่งเข้าสมุดคะแนน', 'คะแนนไปอยู่ในช่องคะแนนของทุกห้องในคลิกเดียว']] as $i => [$icon, $t, $d])
                    <div class="col-6 col-lg-3">
                        <div class="stat-icon tint-primary mx-auto mb-2"><i class="bi {{ $icon }}"></i></div>
                        <div class="fw-semibold">{{ $i + 1 }}. {{ $t }}</div>
                        <div class="small text-muted">{{ $d }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@else
<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>ชุดข้อสอบ</th><th>ห้อง</th><th class="text-center">ข้อ</th><th class="text-center">สแกนแล้ว</th><th class="text-center">รอตรวจทาน</th><th class="d-none d-md-table-cell">วันสอบ</th></tr></thead>
            <tbody>
            @foreach ($exams as $e)
                <tr data-href="{{ route($e->sheets_count ? 'exams.results' : 'exams.show', $e) }}" style="cursor:pointer">
                    <td>
                        <a href="{{ route('exams.show', $e) }}" class="fw-semibold text-body text-decoration-none">{{ $e->title }}</a>
                        <div class="small text-muted">{{ $e->subject->code }} {{ $e->subject->name }}</div>
                        @unless ($e->keyReady())<span class="badge bg-warning-subtle text-warning-emphasis">ยังใส่เฉลยไม่ครบ</span>@endunless
                    </td>
                    <td class="small">{{ $e->courses->map(fn ($c) => $c->classroom?->name())->filter()->implode(', ') }}</td>
                    <td class="text-center">{{ $e->n_items }}</td>
                    <td class="text-center fw-semibold">{{ $e->sheets_count }}</td>
                    <td class="text-center">@if ($e->review_count)<span class="badge bg-danger">{{ $e->review_count }}</span>@else<span class="text-muted">-</span>@endif</td>
                    <td class="small d-none d-md-table-cell">{{ $e->exam_date ? thai_date($e->exam_date) : '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $exams->links() }}</div>
@endif

<div class="modal fade" id="newExam" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('exams.store') }}" class="modal-content" id="examForm">
        @csrf
        <div class="modal-header"><h5 class="modal-title">สร้างชุดข้อสอบ</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body row g-2">
            <div class="col-12">
                <label class="form-label">รายวิชา</label>
                <select class="form-select" id="exSubject" required>
                    @foreach ($courses->groupBy('subject_id') as $sid => $list)
                        <option value="{{ $sid }}">{{ $list->first()->subject->code }} {{ $list->first()->subject->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label">ห้องที่สอบ <span class="small text-muted fw-normal">(ใช้ชุดเดียวหลายห้องได้ — วิเคราะห์ข้อสอบรวมทุกห้อง)</span></label>
                <div class="d-flex flex-wrap gap-2" id="exRooms">
                    @foreach ($courses as $c)
                        <label class="btn btn-sm btn-light border" data-subject="{{ $c->subject_id }}">
                            <input type="checkbox" name="course_ids[]" value="{{ $c->id }}" class="form-check-input me-1" checked> {{ $c->classroom->name() }}
                        </label>
                    @endforeach
                </div>
            </div>
            <div class="col-12"><label class="form-label">ชื่อการสอบ</label><input name="title" class="form-control" required value="สอบกลางภาค" list="examTitles">
                <datalist id="examTitles"><option>สอบกลางภาค</option><option>สอบปลายภาค</option><option>สอบย่อย</option><option>สอบก่อนเรียน</option><option>สอบหลังเรียน</option></datalist>
            </div>
            <div class="col-6"><label class="form-label">จำนวนข้อ</label><input type="number" name="n_items" min="1" max="100" value="40" class="form-control" required></div>
            <div class="col-6"><label class="form-label">วันสอบ</label><input type="date" name="exam_date" value="{{ today()->toDateString() }}" class="form-control"></div>
            <div class="col-12 small text-muted"><i class="bi bi-info-circle"></i> 4 ตัวเลือก (ก–ง) · สูงสุด 100 ข้อ · กระดาษ A4 แผ่นเดียว · ใช้กระดาษ ScanGrade ที่พิมพ์ไว้แล้วได้</div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">สร้างแล้วใส่เฉลย</button></div>
    </form></div>
</div>
@endsection

@push('scripts')
<script>
// ห้องที่สอบ: แสดงเฉพาะห้องที่เรียนวิชาที่เลือก (ชุดข้อสอบหนึ่งชุด = วิชาเดียว)
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('exSubject');
    if (!sel) return;
    const apply = () => document.querySelectorAll('#exRooms [data-subject]').forEach((l) => {
        const on = l.dataset.subject === sel.value;
        l.classList.toggle('d-none', !on);
        l.querySelector('input').disabled = !on;
    });
    sel.addEventListener('change', apply); apply();
});
</script>
@endpush
