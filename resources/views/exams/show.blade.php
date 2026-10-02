@extends('layouts.app')
@section('title', 'เฉลย · '.$exam->title)

@section('content')
@include('exams._nav')

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card" id="keyCard"
             data-url="{{ route('exams.key', $exam) }}" data-n="{{ $exam->n_items }}"
             data-key='@json($exam->key())' data-cancelled='@json($exam->cancelledItems())'>
            <div class="card-header flex-wrap gap-2">
                <i class="bi bi-key"></i> เฉลย
                <span class="small text-muted fw-normal" id="keyProgress"></span>
                <div class="form-check form-switch ms-auto mb-0 small fw-normal">
                    <input class="form-check-input" type="checkbox" id="multiKey">
                    <label class="form-check-label" for="multiKey">ข้อที่ถูกได้หลายตัวเลือก</label>
                </div>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-2">
                    <i class="bi bi-keyboard"></i> คลิกข้อ 1 แล้วพิมพ์ <kbd>1</kbd>–<kbd>4</kbd> หรือ <kbd>ก</kbd>–<kbd>ง</kbd> ต่อกันได้เลย (แป้นไทย/อังกฤษก็ได้) ·
                    <kbd>X</kbd> ยกเลิกข้อ · <kbd>Backspace</kbd> ลบ · ลูกศรเลื่อน
                </div>
                <div class="key-grid" id="keyGrid" tabindex="0"></div>
            </div>
            <div class="card-footer bg-white d-flex flex-wrap align-items-center gap-2 key-footer">
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted text-nowrap" for="keyPoints">คะแนนต่อข้อ</label>
                    <input type="number" id="keyPoints" class="form-control form-control-sm" style="width:80px" min="0.25" max="100" step="0.25" value="{{ rtrim(rtrim(number_format($exam->points, 2), '0'), '.') }}">
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="small text-muted text-nowrap" for="cancelMode">ข้อที่ยกเลิก</label>
                    <select id="cancelMode" class="form-select form-select-sm w-auto">
                        <option value="give" @selected($exam->cancel_mode === 'give')>ให้คะแนนทุกคน</option>
                        <option value="drop" @selected($exam->cancel_mode === 'drop')>ตัดออกจากคะแนนเต็ม</option>
                    </select>
                </div>
                <span id="keyState" class="small ms-auto"></span>
                <button class="btn btn-primary" id="keySave"><i class="bi bi-save"></i> บันทึกเฉลย</button>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-clipboard"></i> วางเฉลย</div>
            <div class="card-body">
                <textarea id="keyPaste" rows="3" class="form-control mb-2" placeholder="วางแถว KEY จาก EVANA หรือ Excel · 2333112… · 2 3 3 3 1 · ข ค ค ค ก"></textarea>
                <button class="btn btn-light border w-100" id="keyPasteBtn"><i class="bi bi-arrow-down-square"></i> ใส่เฉลยจากข้อความ</button>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-check2-circle"></i> ทดลองตรวจ <span class="small text-muted fw-normal ms-1">ตรวจว่าเฉลยถูก</span></div>
            <div class="card-body">
                <input id="tryAnswers" class="form-control mb-2" placeholder="คำตอบของนักเรียน 1 คน เช่น 2333112…" autocomplete="off">
                <div id="tryResult" class="small text-muted">พิมพ์คำตอบแล้วดูคะแนนและถูก/ผิดรายข้อ (ยังไม่บันทึก)</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-lightning"></i> ขั้นต่อไป</div>
            <div class="list-group list-group-flush">
                <a href="{{ route('exams.sheets', $exam) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center"><i class="bi bi-printer text-primary"></i> พิมพ์กระดาษคำตอบ <i class="bi bi-chevron-right ms-auto text-muted"></i></a>
                <a href="{{ route('exams.scan', $exam) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center"><i class="bi bi-camera text-primary"></i> สแกนด้วยมือถือ / จากรูป <i class="bi bi-chevron-right ms-auto text-muted"></i></a>
                <a href="{{ route('exams.results', $exam) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center"><i class="bi bi-list-check text-primary"></i> ผลตรวจ
                    <span class="ms-auto small text-muted">{{ ($counts['ok'] ?? 0) + ($counts['review'] ?? 0) }} แผ่น</span><i class="bi bi-chevron-right text-muted"></i></a>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="bi bi-gear"></i> ตั้งค่าชุดข้อสอบ</div>
            <form method="POST" action="{{ route('exams.update', $exam) }}" class="card-body row g-2">
                @csrf @method('PUT')
                <div class="col-12"><label class="form-label">ชื่อการสอบ</label><input name="title" value="{{ $exam->title }}" class="form-control" required></div>
                <div class="col-6"><label class="form-label">จำนวนข้อ</label><input type="number" name="n_items" min="1" max="100" value="{{ $exam->n_items }}" class="form-control" @readonly(($counts->sum() ?? 0) > 0)></div>
                <div class="col-6"><label class="form-label">วันสอบ</label><input type="date" name="exam_date" value="{{ $exam->exam_date?->toDateString() }}" class="form-control"></div>
                @if ($exam->isAdmission())
                <div class="col-7"><label class="form-label">วิชาสอบ</label><input name="subject_name" value="{{ $exam->subject_name }}" class="form-control" required></div>
                <div class="col-5"><label class="form-label">น้ำหนัก (×)</label><input type="number" name="weight" min="0.01" step="0.01" max="100" value="{{ rtrim(rtrim(number_format($exam->weight, 2), '0'), '.') }}" class="form-control" required></div>
                <div class="col-12 small text-muted">ผู้เข้าสอบ = ผู้สมัครที่ได้เลขประจำตัวสอบแล้วใน{{ $exam->round->label() }} · คะแนนรวมคิดจาก คะแนนวิชา × น้ำหนัก</div>
                @else
                <div class="col-12">
                    <label class="form-label">ห้องที่สอบ</label>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($courses->concat($exam->courses)->unique('id') as $c)
                            <label class="btn btn-sm btn-light border"><input type="checkbox" name="course_ids[]" value="{{ $c->id }}" class="form-check-input me-1" @checked($exam->courses->contains($c))> {{ $c->classroom->name() }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">ช่องในสมุดคะแนน</label>
                    <select name="assessment_name" class="form-select">
                        <option value="">- ยังไม่เลือก -</option>
                        @foreach ($assessmentNames as $n)<option @selected($exam->assessment_name === $n)>{{ $n }}</option>@endforeach
                    </select>
                    <div class="form-text">ใช้ตอนกด "ส่งเข้าสมุดคะแนน" ในหน้าผลตรวจ</div>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="published" value="1" id="pub" @checked($exam->published)>
                        <label class="form-check-label" for="pub">ให้นักเรียน/ผู้ปกครองเห็นคะแนนสอบนี้</label>
                    </div>
                </div>
                @endif
                <div class="col-12 d-flex gap-2">
                    <button class="btn btn-primary">บันทึก</button>
                </div>
            </form>
            <div class="card-footer bg-white d-flex gap-2">
                <form method="POST" action="{{ route('exams.duplicate', $exam) }}">@csrf<button class="btn btn-sm btn-link px-0"><i class="bi bi-copy"></i> คัดลอกเป็นชุดใหม่</button></form>
                <form method="POST" action="{{ route('exams.destroy', $exam) }}" class="ms-auto" data-confirm="ลบชุดข้อสอบนี้พร้อมผลตรวจทุกแผ่น? กู้คืนไม่ได้">@csrf @method('DELETE')
                    <button class="btn btn-sm btn-link text-danger px-0"><i class="bi bi-trash"></i> ลบ</button></form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@include('exams._scripts', ['files' => ['common.js', 'key.js']])
@endpush
