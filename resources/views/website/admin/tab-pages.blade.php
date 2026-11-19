<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
    <p class="text-muted mb-0">หน้าที่เผยแพร่และติ๊ก "ขึ้นเมนู" จะอยู่ในเมนู เกี่ยวกับโรงเรียน</p>
    <button class="btn btn-primary" data-edit="{{ json_encode(['_action' => route('website.pages.store'), '_title' => 'เพิ่มหน้า', 'in_menu' => true, 'is_published' => true, 'sort' => ($pages->max('sort') ?? 0) + 1]) }}" data-target="#pageModal"><i class="bi bi-plus-lg"></i>เพิ่มหน้า</button>
</div>
@if ($pages->isEmpty())
    <div class="text-center text-muted py-5"><i class="bi bi-file-earmark-richtext fs-1 d-block mb-2"></i>ยังไม่มีหน้าเนื้อหา</div>
@else
    <div class="list-group list-group-flush">
        @foreach ($pages as $p)
            <div class="list-group-item d-flex gap-3 align-items-center px-0">
                <span class="web-thumb"><i class="bi bi-file-earmark-text fs-4"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold">{{ $p->title }}</div>
                    <div class="small text-muted">/page/{{ $p->slug }} · {!! $p->is_published ? '<span class="text-success">เผยแพร่</span>' : '<span class="text-secondary">ยังไม่เผยแพร่</span>' !!}{{ $p->in_menu ? ' · ขึ้นเมนู' : '' }}{{ trim((string) $p->body) === '' ? ' · ยังไม่มีเนื้อหา' : '' }}</div>
                </div>
                <a href="{{ route('web.page', $p->slug) }}" target="_blank" class="btn btn-sm btn-light border" title="ดูบนเว็บ"><i class="bi bi-eye"></i></a>
                <button class="btn btn-sm btn-soft" data-edit="{{ json_encode(['_action' => route('website.pages.update', $p->id), '_title' => 'แก้ไขหน้า', 'title' => $p->title, 'slug' => $p->slug, 'body' => $p->body, 'sort' => $p->sort, 'in_menu' => $p->in_menu, 'is_published' => $p->is_published]) }}" data-edit-id="{{ $p->id }}" data-target="#pageModal" title="แก้ไข"><i class="bi bi-pencil"></i></button>
                <form method="POST" action="{{ route('website.pages.destroy', $p->id) }}" data-confirm="ลบหน้า {{ $p->title }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>
            </div>
        @endforeach
    </div>
@endif

<div class="modal fade" id="pageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="POST" action="{{ route('website.pages.store') }}" enctype="multipart/form-data" class="modal-content" data-store-url="{{ route('website.pages.store') }}">
            @csrf <input type="hidden" name="_method" value="POST"><input type="hidden" name="_modal" value="pageModal">
            <div class="modal-header"><h5 class="modal-title">เพิ่มหน้า</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-7"><label class="form-label">ชื่อหน้า</label><input name="title" class="form-control" maxlength="150" required></div>
                    <div class="col-md-5"><label class="form-label">ชื่อในลิงก์ (ภาษาอังกฤษ)</label><input name="slug" class="form-control font-monospace" maxlength="80" placeholder="เช่น history"><div class="form-text">เว้นว่าง = ตั้งให้เอง</div></div>
                    <div class="col-12"><label class="form-label">เนื้อหา</label><textarea name="body" rows="12" class="form-control"></textarea></div>
                    <div class="col-md-6"><label class="form-label">รูปประกอบ</label><input type="file" name="cover" class="form-control" accept="image/jpeg,image/png,image/webp"></div>
                    <div class="col-md-2"><label class="form-label">ลำดับ</label><input type="number" name="sort" class="form-control" min="0" max="999"></div>
                    <div class="col-md-4 d-flex flex-column justify-content-end gap-1">
                        <label class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input" name="is_published" value="1"> เผยแพร่</label>
                        <label class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input" name="in_menu" value="1"> ขึ้นเมนู เกี่ยวกับโรงเรียน</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึก</button></div>
        </form>
    </div>
</div>
