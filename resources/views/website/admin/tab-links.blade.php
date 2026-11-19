<div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
    <p class="text-muted mb-0">โลโก้หน่วยงานต้นสังกัด หรือลิงก์บริการ เช่น ระบบ DMC ระบบ e-Budget ขึ้นท้ายหน้าแรก</p>
    <button class="btn btn-primary" data-edit="{{ json_encode(['_action' => route('website.links.store'), '_title' => 'เพิ่มลิงก์', 'kind' => 'agency', 'sort' => ($links->max('sort') ?? 0) + 1]) }}" data-target="#linkModal"><i class="bi bi-plus-lg"></i>เพิ่มลิงก์</button>
</div>
@foreach (\App\Models\WebLink::KINDS as $kind => $kindLabel)
    @php($items = $links->where('kind', $kind))
    <h3 class="fs-6 fw-bold mt-3">{{ $kindLabel }} <span class="text-muted fw-normal">({{ $items->count() }})</span></h3>
    @if ($items->isEmpty())
        <p class="text-muted small">ยังไม่มี</p>
    @else
        <div class="list-group list-group-flush mb-2">
            @foreach ($items as $l)
                <div class="list-group-item d-flex gap-3 align-items-center px-0">
                    @if ($l->logoUrl())<img src="{{ $l->logoUrl() }}" class="web-thumb" style="object-fit:contain;background:#fff" alt="">@else<span class="web-thumb"><i class="bi bi-{{ $l->icon ?: 'link-45deg' }} fs-4"></i></span>@endif
                    <div class="flex-grow-1 min-w-0"><div class="fw-semibold">{{ $l->title }}</div><div class="small text-muted text-truncate">{{ $l->url }}</div></div>
                    <button class="btn btn-sm btn-soft" data-edit="{{ json_encode(['_action' => route('website.links.update', $l), '_title' => 'แก้ไขลิงก์', 'kind' => $l->kind, 'title' => $l->title, 'url' => $l->url, 'icon' => $l->icon, 'sort' => $l->sort]) }}" data-target="#linkModal" title="แก้ไข"><i class="bi bi-pencil"></i></button>
                    <form method="POST" action="{{ route('website.links.destroy', $l) }}" data-confirm="ลบลิงก์ {{ $l->title }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>
                </div>
            @endforeach
        </div>
    @endif
@endforeach

<div class="modal fade" id="linkModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('website.links.store') }}" enctype="multipart/form-data" class="modal-content" data-store-url="{{ route('website.links.store') }}">
            @csrf <input type="hidden" name="_method" value="POST"><input type="hidden" name="_modal" value="linkModal">
            <div class="modal-header"><h5 class="modal-title">เพิ่มลิงก์</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">ประเภท</label><select name="kind" class="form-select">@foreach (\App\Models\WebLink::KINDS as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
                <div class="mb-3"><label class="form-label">ชื่อ</label><input name="title" class="form-control" maxlength="150" required placeholder="เช่น สำนักงานเขตพื้นที่การศึกษา"></div>
                <div class="mb-3"><label class="form-label">ลิงก์</label><input name="url" class="form-control" maxlength="500" required placeholder="https://"></div>
                <div class="mb-3"><label class="form-label">โลโก้ (ไม่บังคับ)</label><input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/webp"></div>
                <div class="row g-3">
                    <div class="col-7"><label class="form-label">ไอคอนแทนโลโก้</label><input name="icon" class="form-control font-monospace" maxlength="40" placeholder="เช่น building"><div class="form-text">ชื่อจาก Bootstrap Icons</div></div>
                    <div class="col-5"><label class="form-label">ลำดับ</label><input type="number" name="sort" class="form-control" min="0" max="999"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึก</button></div>
        </form>
    </div>
</div>
