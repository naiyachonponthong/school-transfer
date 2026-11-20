@php
    use App\Models\WebPost;
    use Illuminate\Support\Facades\Storage;
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
    <a href="{{ route('website.posts.create', array_filter(['category' => request('category')])) }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i>เขียนข่าว</a>
</div>

@if ($posts->isEmpty())
    <div class="text-center text-muted py-5"><i class="bi bi-newspaper fs-1 d-block mb-2"></i>ยังไม่มีข่าว กด เขียนข่าว เพื่อเริ่ม</div>
@else
    <div class="list-group list-group-flush">
        @foreach ($posts as $p)
            @php
                $state = $p->published_at === null ? ['ฉบับร่าง', 'secondary'] : ($p->isPublished() ? ['เผยแพร่แล้ว', 'success'] : ['ตั้งเวลา '.thai_datetime($p->published_at), 'warning']);
            @endphp
            <div class="list-group-item d-flex gap-3 align-items-center px-0">
                @if ($p->coverUrl())<img src="{{ $p->coverUrl() }}" class="web-thumb" alt="">@else<span class="web-thumb"><i class="bi bi-{{ WebPost::CATEGORIES[$p->category][1] }} fs-4"></i></span>@endif
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate">@if ($p->pinned)<i class="bi bi-pin-angle-fill text-primary"></i> @endif @if ($p->urgent)<span class="badge text-bg-danger">ด่วน</span> @endif<a href="{{ route('website.posts.edit', $p) }}" class="text-reset">{{ $p->title }}</a></div>
                    <div class="small text-muted">{{ $p->categoryLabel() }} · <span class="text-{{ $state[1] }}">{{ $state[0] }}</span>@if ($p->isPublished()) · {{ thai_date($p->published_at) }} · อ่าน {{ number_format($p->views) }}@endif
                        @if ($p->images) · <i class="bi bi-images"></i> {{ count($p->images) }}@endif @if ($p->attachments) · <i class="bi bi-paperclip"></i> {{ count($p->attachments) }}@endif @foreach ($p->tags ?? [] as $t)<span class="text-primary">#{{ $t }}</span> @endforeach</div>
                </div>
                <a href="{{ route('web.post', $p) }}" target="_blank" class="btn btn-sm btn-light border" title="ดูบนเว็บ"><i class="bi bi-eye"></i></a>
                <a href="{{ route('website.posts.edit', $p) }}" class="btn btn-sm btn-soft" title="แก้ไข"><i class="bi bi-pencil"></i></a>
                <form method="POST" action="{{ route('website.posts.destroy', $p) }}" data-confirm="ลบข่าว {{ $p->title }}? รูปและไฟล์แนบจะถูกลบด้วย">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>
            </div>
        @endforeach
    </div>
    <div class="mt-3">{{ $posts->links() }}</div>
@endif
