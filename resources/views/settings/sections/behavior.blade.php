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
