@php
    use App\Models\WebPost;
    use Illuminate\Support\Facades\Storage;
    $isOld = old('_modal') === 'postModal';
@endphp
<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
    <form method="GET" class="d-flex gap-2 flex-wrap">
        <input type="hidden" name="tab" value="posts">
        <select name="category" class="form-select" style="width:auto" onchange="this.form.submit()" aria-label="หมวด">
            <option value="">ทุกหมวด</option>
            @foreach (WebPost::CATEGORIES as $k => [$l])<option value="{{ $k }}" @selected(request('category') === $k)>{{ $l }}</option>@endforeach
        </select>
        <input type="search" name="q" value="{{ request('q') }}" class="form-control" placeholder="ค้นหาหัวข้อ" style="width:200px" aria-label="ค้นหา">
    </form>
    <button class="btn btn-primary" data-edit="{{ json_encode(['_action' => route('website.posts.store'), '_title' => 'เพิ่มข่าว', 'category' => request('category') ?: 'news', 'status' => 'now']) }}" data-target="#postModal"><i class="bi bi-plus-lg"></i>เพิ่มข่าว</button>
</div>

@if ($posts->isEmpty())
    <div class="text-center text-muted py-5"><i class="bi bi-newspaper fs-1 d-block mb-2"></i>ยังไม่มีข่าว กด เพิ่มข่าว เพื่อเริ่ม</div>
@else
    <div class="list-group list-group-flush">
        @foreach ($posts as $p)
            @php
                $state = $p->published_at === null ? ['ฉบับร่าง', 'secondary'] : ($p->isPublished() ? ['เผยแพร่แล้ว', 'success'] : ['ตั้งเวลา '.thai_datetime($p->published_at), 'warning']);
                $edit = [
                    '_action' => route('website.posts.update', $p), '_title' => 'แก้ไขข่าว',
                    'category' => $p->category, 'title' => $p->title, 'body' => $p->body, 'pinned' => $p->pinned,
                    'status' => $p->published_at === null ? 'draft' : ($p->isPublished() ? 'now' : 'schedule'),
                    'publish_at' => $p->published_at?->format('Y-m-d\TH:i'),
                    '_images' => collect($p->images ?? [])->map(fn ($i) => ['path' => $i, 'url' => asset('storage/'.(string) $i)])->all(),
                    '_files' => $p->attachments ?? [],
                ];
            @endphp
            <div class="list-group-item d-flex gap-3 align-items-center px-0">
                @if ($p->coverUrl())<img src="{{ $p->coverUrl() }}" class="web-thumb" alt="">@else<span class="web-thumb"><i class="bi bi-{{ WebPost::CATEGORIES[$p->category][1] }} fs-4"></i></span>@endif
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate">@if ($p->pinned)<i class="bi bi-pin-angle-fill text-primary"></i> @endif{{ $p->title }}</div>
                    <div class="small text-muted">{{ $p->categoryLabel() }} · <span class="text-{{ $state[1] }}">{{ $state[0] }}</span>@if ($p->isPublished()) · {{ thai_date($p->published_at) }} · อ่าน {{ number_format($p->views) }}@endif
                        @if ($p->images) · <i class="bi bi-images"></i> {{ count($p->images) }}@endif @if ($p->attachments) · <i class="bi bi-paperclip"></i> {{ count($p->attachments) }}@endif</div>
                </div>
                <a href="{{ route('web.post', $p) }}" target="_blank" class="btn btn-sm btn-light border" title="ดูบนเว็บ"><i class="bi bi-eye"></i></a>
                <button class="btn btn-sm btn-soft" data-edit="{{ json_encode($edit) }}" data-edit-id="{{ $p->id }}" data-target="#postModal" title="แก้ไข"><i class="bi bi-pencil"></i></button>
                <form method="POST" action="{{ route('website.posts.destroy', $p) }}" data-confirm="ลบข่าว {{ $p->title }}? รูปและไฟล์แนบจะถูกลบด้วย">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>
            </div>
        @endforeach
    </div>
    <div class="mt-3">{{ $posts->links() }}</div>
@endif

<div class="modal fade" id="postModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="POST" action="{{ $isOld ? old('_action_url', route('website.posts.store')) : route('website.posts.store') }}" enctype="multipart/form-data" class="modal-content" data-store-url="{{ route('website.posts.store') }}">
            @csrf
            <input type="hidden" name="_method" value="{{ $isOld ? old('_method', 'POST') : 'POST' }}">
            <input type="hidden" name="_modal" value="postModal">
            <input type="hidden" name="_action_url" value="{{ $isOld ? old('_action_url') : route('website.posts.store') }}">
            <div class="modal-header"><h5 class="modal-title">เพิ่มข่าว</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body">
                @if ($isOld && $errors->any())<div class="alert alert-danger small"><i class="bi bi-x-circle-fill"></i> {{ $errors->first() }}</div>@endif
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">หัวข้อ</label><input name="title" class="form-control" maxlength="255" required value="{{ $isOld ? old('title') : '' }}"></div>
                    <div class="col-md-4"><label class="form-label">หมวด</label><select name="category" class="form-select">@foreach (WebPost::CATEGORIES as $k => [$l])<option value="{{ $k }}" @selected($isOld && old('category') === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label">เนื้อหา</label><textarea name="body" rows="9" class="form-control" placeholder="พิมพ์เนื้อหา ขึ้นบรรทัดใหม่ได้ ลิงก์ที่ขึ้นต้นด้วย https:// จะกดได้เอง">{{ $isOld ? old('body') : '' }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">รูปปก</label><input type="file" name="cover" class="form-control" accept="image/jpeg,image/png,image/webp"><div class="form-text">ไม่ใส่ = ใช้รูปแรกของภาพกิจกรรม</div></div>
                    <div class="col-md-6"><label class="form-label">ภาพกิจกรรม (เลือกได้หลายรูป)</label><input type="file" name="images[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple><div class="form-text">รูปละไม่เกิน 8 MB ระบบย่อให้เอง</div></div>
                    <div class="col-12" data-existing="images"></div>
                    <div class="col-md-6"><label class="form-label">ไฟล์แนบ (PDF, Word, Excel)</label><input type="file" name="files[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.jpg,.jpeg,.png"><div class="form-text">เช่น ประกาศจัดซื้อจัดจ้าง ไฟล์ละไม่เกิน 20 MB</div></div>
                    <div class="col-md-6" data-existing="files"></div>
                    <div class="col-md-6">
                        <label class="form-label">การเผยแพร่</label>
                        <select name="status" class="form-select" data-status>
                            <option value="now">เผยแพร่ทันที</option>
                            <option value="schedule">ตั้งเวลาเผยแพร่</option>
                            <option value="draft">บันทึกเป็นฉบับร่าง</option>
                        </select>
                    </div>
                    <div class="col-md-6" data-schedule hidden><label class="form-label">วันเวลาเผยแพร่</label><input type="datetime-local" name="publish_at" class="form-control"></div>
                    <div class="col-12"><label class="form-check form-switch"><input type="checkbox" class="form-check-input" name="pinned" value="1"> ปักหมุดไว้บนสุด</label></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึก</button></div>
        </form>
    </div>
</div>
@push('scripts')
<script>
(function () {
  const m = document.getElementById('postModal'), st = m.querySelector('[data-status]'), box = m.querySelector('[data-schedule]');
  const sync = () => { box.hidden = st.value !== 'schedule'; };
  st.addEventListener('change', sync); m.addEventListener('website:fill', sync); sync();
})();
</script>
@endpush
