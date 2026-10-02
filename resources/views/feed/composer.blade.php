{{-- ช่องโพสต์ข่าว + modal สำหรับครู --}}
@php($me = auth()->user())
@if ($me->isStaff())
    <div class="card-body border-bottom">
        <div class="composer">
            <span class="sb-avatar">@if($me->avatarUrl())<img src="{{ $me->avatarUrl() }}" alt="">@else{{ $me->initials() }}@endif</span>
            <div class="fake-input" data-bs-toggle="modal" data-bs-target="#composeModal">แชร์ข่าว ผลงานนักเรียน หรือกิจกรรม...</div>
            <button class="btn btn-soft d-none d-sm-inline-flex" data-bs-toggle="modal" data-bs-target="#composeModal" data-compose-type="achievement"><i class="bi bi-trophy me-1"></i> ประกาศเกียรติคุณ</button>
        </div>
    </div>

    @push('scripts')
    <div class="modal fade" id="composeModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered"><form method="POST" action="{{ route('feed.store') }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">สร้างโพสต์</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="btn-group w-100 mb-3">
                    <input type="radio" class="btn-check" name="type" value="post" id="ctPost" checked>
                    <label class="btn btn-outline-primary" for="ctPost"><i class="bi bi-newspaper"></i> ข่าวสาร</label>
                    <input type="radio" class="btn-check" name="type" value="achievement" id="ctAch">
                    <label class="btn btn-outline-primary" for="ctAch"><i class="bi bi-trophy"></i> ประกาศเกียรติคุณ</label>
                </div>
                <div class="mb-2" data-show-when="type=achievement">
                    <label class="form-label">ชื่อรางวัล / ผลงาน</label>
                    <input name="title" class="form-control" placeholder="เช่น เหรียญทองคณิตศาสตร์โอลิมปิก ระดับจังหวัด">
                </div>
                <div class="mb-2">
                    <label class="form-label">นักเรียน (ไม่บังคับ)</label>
                    <input name="student_code" class="form-control" list="composeStudents" placeholder="พิมพ์ชื่อหรือรหัสนักเรียน">
                    <datalist id="composeStudents">
                        @foreach (\App\Models\Student::active()->with('classroom')->orderBy('student_code')->get(['id', 'student_code', 'prefix', 'first_name', 'last_name', 'classroom_id']) as $s)
                            <option value="{{ $s->student_code }} {{ $s->fullName() }} ({{ $s->classroom?->name() }})"></option>
                        @endforeach
                    </datalist>
                </div>
                <textarea name="body" rows="4" class="form-control mb-2" placeholder="เล่าเรื่องราว..."></textarea>
                <label class="btn btn-light border btn-sm"><i class="bi bi-image"></i> แนบรูป <input type="file" name="image" accept="image/*" class="d-none" data-preview="#composePreview"></label>
                <img id="composePreview" class="d-none mt-2 rounded-3 w-100" style="max-height:220px;object-fit:cover" alt="">
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <label class="form-label small">ใครเห็นได้</label>
                        <select name="audience" class="form-select form-select-sm">
                            <option value="all">ทุกคน</option><option value="parents">ผู้ปกครอง</option><option value="staff">ครูและบุคลากร</option><option value="classroom">เฉพาะห้อง</option>
                        </select>
                    </div>
                    <div class="col-6" data-show-when="audience=classroom">
                        <label class="form-label small">ห้อง</label>
                        <select name="classroom_id" class="form-select form-select-sm">
                            @foreach (\App\Models\Classroom::currentYear()->ordered()->get() as $c)<option value="{{ $c->id }}">{{ $c->name() }}</option>@endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary px-4"><i class="bi bi-send"></i> โพสต์</button></div>
        </form></div>
    </div>
    @endpush
@endif
