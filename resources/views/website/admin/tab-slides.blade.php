<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
    <p class="text-muted mb-0">รูปแนวนอนขนาด 1600 x 700 พิกเซลขึ้นไปจะชัดที่สุด ไม่มีสไลด์ = หน้าแรกแสดงแบนเนอร์ชื่อโรงเรียนแทน</p>
    <button class="btn btn-primary" data-edit="{{ json_encode(['_action' => route('website.slides.store'), '_title' => 'เพิ่มสไลด์', 'is_active' => true, 'sort' => ($slides->max('sort') ?? 0) + 1]) }}" data-target="#slideModal"><i class="bi bi-plus-lg"></i>เพิ่มสไลด์</button>
</div>
@if ($slides->isEmpty())
    <div class="text-center text-muted py-5"><i class="bi bi-images fs-1 d-block mb-2"></i>ยังไม่มีสไลด์</div>
@else
    <div class="row g-3">
        @foreach ($slides as $s)
            <div class="col-md-6 col-xl-4">
                <div class="card h-100 {{ $s->is_active ? '' : 'opacity-50' }}">
                    <img src="{{ $s->imageUrl() }}" class="card-img-top" style="height:170px;object-fit:cover" alt="">
                    <div class="card-body">
                        <div class="fw-semibold">{{ $s->title ?: 'ไม่มีหัวข้อ' }}</div>
                        <div class="small text-muted">ลำดับ {{ $s->sort }} · {{ $s->is_active ? 'แสดงอยู่' : 'ซ่อน' }}</div>
                        <div class="d-flex gap-2 mt-2">
                            <button class="btn btn-sm btn-soft" data-edit="{{ json_encode(['_action' => route('website.slides.update', $s), '_title' => 'แก้ไขสไลด์', 'title' => $s->title, 'caption' => $s->caption, 'url' => $s->url, 'sort' => $s->sort, 'is_active' => $s->is_active]) }}" data-edit-id="{{ $s->id }}" data-target="#slideModal"><i class="bi bi-pencil"></i>แก้ไข</button>
                            <form method="POST" action="{{ route('website.slides.destroy', $s) }}" data-confirm="ลบสไลด์นี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash"></i></button></form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

<div class="modal fade" id="slideModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('website.slides.store') }}" enctype="multipart/form-data" class="modal-content" data-store-url="{{ route('website.slides.store') }}">
            @csrf <input type="hidden" name="_method" value="POST"><input type="hidden" name="_modal" value="slideModal">
            <div class="modal-header"><h5 class="modal-title">เพิ่มสไลด์</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">รูป</label><input type="file" name="image" class="form-control" accept="image/jpeg,image/png,image/webp"><div class="form-text">ตอนแก้ไข เว้นว่าง = ใช้รูปเดิม</div></div>
                <div class="mb-3"><label class="form-label">หัวข้อบนสไลด์</label><input name="title" class="form-control" maxlength="150"></div>
                <div class="mb-3"><label class="form-label">คำอธิบาย</label><input name="caption" class="form-control" maxlength="255"></div>
                <div class="mb-3"><label class="form-label">ลิงก์เมื่อกด (ไม่บังคับ)</label><input name="url" class="form-control" maxlength="500" placeholder="https://"></div>
                <div class="row g-3">
                    <div class="col-5"><label class="form-label">ลำดับ</label><input type="number" name="sort" class="form-control" min="0" max="999"></div>
                    <div class="col-7 d-flex align-items-end"><label class="form-check form-switch mb-2"><input type="checkbox" class="form-check-input" name="is_active" value="1"> แสดงบนหน้าแรก</label></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึก</button></div>
        </form>
    </div>
</div>
