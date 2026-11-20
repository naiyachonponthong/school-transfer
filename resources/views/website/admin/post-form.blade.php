@extends('layouts.app')
@section('title', $post->exists ? 'แก้ไขข่าว' : 'เขียนข่าวใหม่')

@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/editor.css') }}?v={{ @filemtime(public_path('assets/css/editor.css')) }}">
@endpush

@php
    use App\Models\WebPost;
    $status = old('status', $post->published_at === null ? ($post->exists ? 'draft' : 'now') : ($post->isPublished() ? 'now' : 'schedule'));
    $body = old('body', $post->exists ? $post->bodyHtml() : '');
    $tags = old('tags', $post->tags ?? []);
@endphp

@section('content')
<div class="page-head">
    <div>
        <h1>{{ $post->exists ? 'แก้ไขข่าว' : 'เขียนข่าวใหม่' }}</h1>
        <div class="sub"><a href="{{ route('website.admin', ['tab' => 'posts']) }}"><i class="bi bi-arrow-left"></i> กลับไปรายการข่าว</a></div>
    </div>
    @if ($post->exists)<a href="{{ route('web.post', $post) }}" target="_blank" class="btn btn-light border"><i class="bi bi-eye"></i>ดูบนเว็บ</a>@endif
</div>

<form method="POST" action="{{ $post->exists ? route('website.posts.update', $post) : route('website.posts.store') }}" enctype="multipart/form-data" class="post-editor" id="postForm">
    @csrf
    @if ($post->exists) @method('PUT') @endif
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4"><div class="card-body">
                <label class="form-label fw-semibold" for="pTitle">หัวข้อข่าว <span class="text-danger">*</span></label>
                <input id="pTitle" name="title" class="form-control title-input" maxlength="255" required value="{{ old('title', $post->title) }}" placeholder="หัวข้อข่าว / ประกาศ">
            </div></div>

            <div class="card mb-4"><div class="card-body">
                <label class="form-label fw-semibold" for="pExcerpt">สรุปสั้น</label>
                <textarea id="pExcerpt" name="excerpt" rows="2" class="form-control" maxlength="300" placeholder="แสดงบนการ์ดข่าว และเป็นคำอธิบายเวลาแชร์ลิงก์ (เว้นว่าง = ตัดจากเนื้อหา)">{{ old('excerpt', $post->excerpt) }}</textarea>
                <div class="form-text text-end"><span data-count-for="pExcerpt">0</span>/300</div>
            </div></div>

            <div class="card mb-4">
                <div class="card-title-row">เนื้อหาข่าว</div>
                <div data-rich-editor data-upload-url="{{ route('website.posts.upload') }}" data-label="เนื้อหาข่าว">
                    <textarea name="body">{{ $body }}</textarea>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-title-row"><i class="bi bi-images"></i>ภาพกิจกรรมและไฟล์แนบ</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">ภาพกิจกรรม (เลือกได้หลายรูป)</label><input type="file" name="images[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple><div class="form-text">แสดงเป็นแกลเลอรีท้ายข่าว รูปละไม่เกิน 8 MB ระบบย่อให้เอง</div></div>
                        <div class="col-md-6"><label class="form-label">ไฟล์แนบ (PDF, Word, Excel)</label><input type="file" name="files[]" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.jpg,.jpeg,.png"><div class="form-text">เช่น ประกาศจัดซื้อจัดจ้าง ไฟล์ละไม่เกิน 20 MB</div></div>
                        @if ($post->images)
                            <div class="col-12 d-flex flex-wrap gap-2">
                                @foreach ($post->images as $img)
                                    <label class="existing-thumb"><img src="{{ asset('storage/'.$img) }}" alt=""><span><input type="checkbox" class="form-check-input" name="remove_images[]" value="{{ $img }}"> ลบ</span></label>
                                @endforeach
                            </div>
                        @endif
                        @if ($post->attachments)
                            <div class="col-12">
                                @foreach ($post->attachments as $f)
                                    <label class="d-flex gap-2 align-items-center small mb-1"><input type="checkbox" class="form-check-input" name="remove_files[]" value="{{ $f['path'] }}"> ลบ <i class="bi bi-paperclip"></i> {{ $f['name'] }}</label>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="side-sticky">
                <div class="card mb-4">
                    <div class="card-title-row">เผยแพร่</div>
                    <div class="card-body">
                        <label class="form-label fw-semibold" for="pStatus">สถานะ</label>
                        <select id="pStatus" name="status" class="form-select mb-3" data-status>
                            <option value="draft" @selected($status === 'draft')>ฉบับร่าง</option>
                            <option value="now" @selected($status === 'now')>เผยแพร่ทันที</option>
                            <option value="schedule" @selected($status === 'schedule')>ตั้งเวลาเผยแพร่</option>
                        </select>
                        <div data-schedule @if ($status !== 'schedule') hidden @endif>
                            <label class="form-label fw-semibold" for="pAt">วันเวลาเผยแพร่</label>
                            <input id="pAt" type="datetime-local" name="publish_at" class="form-control mb-3" value="{{ old('publish_at', $post->published_at?->format('Y-m-d\TH:i')) }}">
                        </div>
                        <label class="form-check mb-2"><input type="checkbox" class="form-check-input" name="pinned" value="1" @checked(old('pinned', $post->pinned))> <i class="bi bi-pin-angle-fill text-primary"></i> ปักหมุดไว้บนสุด</label>
                        <label class="form-check"><input type="checkbox" class="form-check-input" name="urgent" value="1" @checked(old('urgent', $post->urgent))> <i class="bi bi-exclamation-triangle-fill text-danger"></i> ข่าวด่วน</label>
                    </div>
                    <div class="card-body border-top d-flex gap-2">
                        <button class="btn btn-primary flex-grow-1"><i class="bi bi-check-lg"></i>บันทึก</button>
                        <a href="{{ route('website.admin', ['tab' => 'posts']) }}" class="btn btn-light border">ยกเลิก</a>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-title-row">หมวดหมู่</div>
                    <div class="card-body">
                        <select name="category" class="form-select" aria-label="หมวดหมู่">
                            @foreach (WebPost::CATEGORIES as $k => [$l])<option value="{{ $k }}" @selected(old('category', $post->category) === $k)>{{ $l }}</option>@endforeach
                        </select>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-title-row">แท็ก</div>
                    <div class="card-body" data-tags>
                        <div class="d-flex flex-wrap gap-1 mb-2" data-tag-list>
                            @foreach ($tags as $t)<span class="tag-chip sel">#{{ $t }}<input type="hidden" name="tags[]" value="{{ $t }}"><button type="button" class="x" aria-label="เอาแท็ก {{ $t }} ออก">&times;</button></span>@endforeach
                        </div>
                        <div class="input-group input-group-sm">
                            <input class="form-control" data-tag-input placeholder="พิมพ์แท็ก คั่นด้วย , แล้วกด Enter" aria-label="เพิ่มแท็ก" maxlength="40">
                            <button type="button" class="btn btn-light border" data-tag-add aria-label="เพิ่มแท็ก"><i class="bi bi-plus-lg"></i></button>
                        </div>
                        @if ($usedTags)
                            <div class="d-flex justify-content-between small text-muted mt-3 mb-1"><span>แท็กที่เคยใช้</span>@if (count($usedTags) > 12)<button type="button" class="btn btn-link btn-sm p-0" data-tag-more>ดูทั้งหมด ({{ count($usedTags) }})</button>@endif</div>
                            <div class="d-flex flex-wrap gap-1 tag-used" data-tag-used>
                                @foreach ($usedTags as $t => $n)<button type="button" class="tag-chip" data-tag="{{ $t }}" title="ใช้ใน {{ $n }} ข่าว">#{{ $t }}</button>@endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-title-row">ภาพปก</div>
                    <div class="card-body">
                        <label class="cover-preview {{ $post->coverUrl() ? 'has' : '' }} mb-2" data-cover-preview style="cursor:pointer;{{ $post->coverUrl() ? 'background-image:url('.$post->coverUrl().')' : '' }}">
                            <i class="bi bi-image"></i>
                            <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" hidden data-cover-input>
                        </label>
                        <div class="form-text">กดที่กรอบเพื่อเลือกรูป แนะนำแนวนอน 16:9 ไม่ใส่ = ใช้รูปแรกของภาพกิจกรรม</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script src="{{ asset('assets/js/editor.js') }}?v={{ @filemtime(public_path('assets/js/editor.js')) }}"></script>
<script>
(() => {
  const form = document.getElementById('postForm');
  // สถานะ > ช่องตั้งเวลา
  const st = form.querySelector('[data-status]'), box = form.querySelector('[data-schedule]');
  st.addEventListener('change', () => { box.hidden = st.value !== 'schedule'; });
  // นับตัวอักษรสรุปสั้น
  const ex = document.getElementById('pExcerpt'), cnt = form.querySelector('[data-count-for=pExcerpt]');
  const count = () => { cnt.textContent = ex.value.length; }; ex.addEventListener('input', count); count();
  // ภาพปก: แสดงตัวอย่างทันที
  const cover = form.querySelector('[data-cover-input]'), prev = form.querySelector('[data-cover-preview]');
  cover.addEventListener('change', () => { const f = cover.files[0]; if (!f) return; prev.style.backgroundImage = `url(${URL.createObjectURL(f)})`; prev.classList.add('has'); });

  // แท็ก
  const list = form.querySelector('[data-tag-list]'), input = form.querySelector('[data-tag-input]');
  const has = (t) => [...list.querySelectorAll('input')].some((i) => i.value === t);
  const mark = () => form.querySelectorAll('[data-tag]').forEach((b) => b.classList.toggle('sel', has(b.dataset.tag)));
  const add = (raw) => raw.split(/[,\n]/).map((t) => t.trim().replace(/^#+/, '').slice(0, 40)).filter(Boolean).forEach((t) => {
    if (has(t) || list.children.length >= 15) return;
    const chip = document.createElement('span');
    chip.className = 'tag-chip sel';
    chip.append('#' + t);
    chip.insertAdjacentHTML('beforeend', '<input type="hidden" name="tags[]"><button type="button" class="x" aria-label="เอาแท็กออก">&times;</button>');
    chip.querySelector('input').value = t;
    list.appendChild(chip);
  });
  const commit = () => { add(input.value); input.value = ''; mark(); };
  input.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); commit(); } });
  input.addEventListener('blur', commit);
  form.querySelector('[data-tag-add]').addEventListener('click', commit);
  list.addEventListener('click', (e) => { if (e.target.closest('.x')) { e.target.closest('.tag-chip').remove(); mark(); } });
  form.querySelectorAll('[data-tag]').forEach((b) => b.addEventListener('click', () => {
    if (has(b.dataset.tag)) [...list.querySelectorAll('input')].find((i) => i.value === b.dataset.tag).closest('.tag-chip').remove(); else add(b.dataset.tag);
    mark();
  }));
  form.querySelector('[data-tag-more]')?.addEventListener('click', (e) => { form.querySelector('[data-tag-used]').classList.toggle('open'); e.target.textContent = e.target.textContent.startsWith('ดูทั้งหมด') ? 'ย่อ' : 'ดูทั้งหมด'; });
  mark();

  // เตือนก่อนออกจากหน้าถ้ายังไม่บันทึก
  let dirty = false;
  form.addEventListener('input', () => { dirty = true; });
  form.addEventListener('submit', () => { dirty = false; });
  addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
@endpush
