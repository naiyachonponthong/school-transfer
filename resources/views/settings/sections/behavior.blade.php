<form method="POST" action="{{ route('settings.update') }}" class="mb-4">
    @csrf
    <input type="hidden" name="section" value="behavior">
    <div class="set-head"><h3>หักคะแนนอัตโนมัติจากการมาสาย / ขาดเรียน</h3><p>นับจากการเช็กชื่อรายวัน (ครูเช็ก สแกนหน้าประตู ใบลาที่อนุมัติ) นับใหม่ทุกภาคเรียน ใส่ 0 = ไม่หัก แก้ขาดเป็นลาแล้วไม่ครบรอบ ระบบคืนคะแนนให้เอง</p></div>
    @php($term = \App\Models\Term::current())
    @if (! $term?->start_date || ! $term?->end_date)
        <div class="alert alert-warning small d-flex gap-2"><i class="bi bi-exclamation-triangle-fill"></i><div>ภาคเรียนปัจจุบันยังไม่ได้ตั้งวันเริ่ม/สิ้นสุด ระบบจึงยังนับไม่ได้ ตั้งได้ที่เมนู ปีการศึกษา</div></div>
    @endif
    <div class="row g-3">
        @foreach (\App\Support\AutoBehavior::KINDS as $kind => $label)
            <div class="col-md-6">
                <div class="p-3 rounded-3 h-100" style="background:#fafbfc">
                    <div class="fw-semibold mb-2"><i class="bi bi-{{ $kind === 'late' ? 'alarm' : 'person-x' }}"></i> {{ $label }}</div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span>ทุก</span><input type="number" name="behavior_auto_{{ $kind }}_every" value="{{ old('behavior_auto_'.$kind.'_every', $settings['behavior_auto_'.$kind.'_every']) }}" min="0" max="50" class="form-control" style="width:80px" aria-label="{{ $label }}กี่ครั้ง">
                        <span>ครั้ง หัก</span><input type="number" name="behavior_auto_{{ $kind }}_points" value="{{ old('behavior_auto_'.$kind.'_points', $settings['behavior_auto_'.$kind.'_points']) }}" min="0" max="100" class="form-control" style="width:80px" aria-label="หักกี่คะแนน">
                        <span>คะแนน</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="set-actions"><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึกเกณฑ์ และคำนวณภาคเรียนนี้ใหม่</button></div>
</form>

<div class="set-head"><h3>หัวข้อคะแนนความประพฤติ</h3><p>ปุ่มลัดที่ครูกดบันทึกพฤติกรรมได้ในคลิกเดียว คะแนนบวก = เพิ่ม ลบ = หัก</p></div>
<div class="row g-2">
    @foreach ($rules as $r)
        <div class="col-lg-6">
            <form method="POST" action="{{ route('settings.rules.update', $r) }}" class="d-flex gap-2 align-items-center p-2 rounded-3" style="background:#fafbfc">
                @csrf @method('PUT')
                <input type="checkbox" name="is_active" value="1" class="form-check-input m-0 ms-1" @checked($r->is_active) title="เปิดใช้" aria-label="เปิดใช้ {{ $r->name }}">
                <input name="name" value="{{ $r->name }}" class="form-control form-control-sm" aria-label="ชื่อหัวข้อ">
                <input name="points" type="number" value="{{ $r->points }}" class="form-control form-control-sm {{ $r->points > 0 ? 'text-success' : 'text-danger' }}" style="width:80px" aria-label="คะแนน">
                <button class="btn btn-sm btn-soft" title="บันทึก"><i class="bi bi-check-lg"></i></button>
                <button form="rdel{{ $r->id }}" class="btn btn-sm btn-light border text-danger" title="ลบ"><i class="bi bi-trash"></i></button>
            </form>
            <form id="rdel{{ $r->id }}" method="POST" action="{{ route('settings.rules.destroy', $r) }}" data-confirm="ลบหัวข้อนี้?">@csrf @method('DELETE')</form>
        </div>
    @endforeach
</div>
<div class="set-block">
    <div class="set-block-title"><i class="bi bi-plus-circle"></i>เพิ่มหัวข้อใหม่</div>
    <form method="POST" action="{{ route('settings.rules.store') }}" class="d-flex gap-2 flex-wrap">
        @csrf
        <input name="name" class="form-control" style="max-width:360px" placeholder="เช่น ช่วยงานโรงเรียน" required>
        <input name="points" type="number" class="form-control" style="width:110px" placeholder="+5 / -5" required>
        <button class="btn btn-primary"><i class="bi bi-plus-lg"></i>เพิ่มหัวข้อ</button>
    </form>
</div>
