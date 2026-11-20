@php
    $total = $year?->items->count() ?? 0;
    $done = $year?->items->where('done', true)->count() ?? 0;
    $pct = $total ? round($done * 100 / $total) : 0;
@endphp
<div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
    <div class="d-flex gap-2 align-items-center flex-wrap">
        @if ($years->isNotEmpty())
            <form method="GET" class="d-flex gap-2">
                <input type="hidden" name="tab" value="ita">
                <select name="year" class="form-select" style="width:auto" onchange="this.form.submit()" aria-label="ปีงบประมาณ">
                    @foreach ($years as $y)<option value="{{ $y->year }}" @selected($year?->id === $y->id)>ปีงบประมาณ {{ $y->year }}</option>@endforeach
                </select>
            </form>
        @endif
        @if ($year)
            <a href="{{ route('web.ita', $year->year) }}" target="_blank" class="btn btn-light border"><i class="bi bi-box-arrow-up-right"></i>ดูหน้า ITA บนเว็บ</a>
        @endif
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#itaYearModal"><i class="bi bi-plus-lg"></i>สร้างปีงบประมาณ</button>
</div>

<div class="alert alert-warning small d-flex gap-2"><i class="bi bi-exclamation-triangle-fill"></i><div>
    หัวข้อ OIT และเลขข้อเปลี่ยนตามคู่มือ ITA ของ ป.ป.ช. แต่ละปี รายการตั้งต้นของระบบใช้เป็นจุดเริ่มต้นเท่านั้น <b>ตรวจและแก้ให้ตรงคู่มือปีงบประมาณนั้นก่อนส่งลิงก์</b>
</div></div>

@if (! $year)
    <div class="text-center text-muted py-5"><i class="bi bi-patch-check fs-1 d-block mb-2"></i>ยังไม่มีข้อมูล ITA กด สร้างปีงบประมาณ เพื่อเริ่ม</div>
@else
    <div class="d-flex flex-wrap gap-3 align-items-center mb-3 p-3 rounded-3" style="background:#fafbfc">
        <div class="flex-grow-1" style="min-width:220px">
            <div class="d-flex justify-content-between small mb-1"><span>หลักฐานครบ {{ $done }} / {{ $total }} หัวข้อ</span><b>{{ $pct }}%</b></div>
            <div class="progress" style="height:10px"><div class="progress-bar bg-success" style="width: {{ $pct }}%"></div></div>
        </div>
        <form method="POST" action="{{ route('website.ita.years.update', $year->id) }}" class="d-flex gap-2 align-items-center">
            @csrf @method('PUT')
            <label class="form-check form-switch mb-0"><input type="checkbox" class="form-check-input" name="is_published" value="1" @checked($year->is_published) onchange="this.form.submit()"> แสดงบนเว็บไซต์</label>
        </form>
        <button class="btn btn-sm btn-soft" data-edit="{{ json_encode(['_action' => route('website.ita.items.store', $year->id), '_title' => 'เพิ่มหัวข้อ', 'sort' => ($year->items->max('sort') ?? 0) + 1]) }}" data-target="#itaItemModal"><i class="bi bi-plus-lg"></i>เพิ่มหัวข้อ</button>
        <form method="POST" action="{{ route('website.ita.years.destroy', $year->id) }}" data-confirm="ลบ ITA ปีงบประมาณ {{ $year->year }} ทั้งหมด? หลักฐานและไฟล์จะถูกลบด้วย">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash"></i>ลบปีนี้</button></form>
    </div>

    @foreach ($year->items->groupBy('group') as $group => $items)
        <h3 class="fs-6 fw-bold mt-4 mb-2">{{ $group ?: 'อื่น ๆ' }}</h3>
        <div class="list-group list-group-flush">
            @foreach ($items as $it)
                <div class="list-group-item px-0">
                    <div class="d-flex gap-2 align-items-start">
                        <form method="POST" action="{{ route('website.ita.items.toggle', $it) }}">@csrf
                            <button class="btn btn-sm {{ $it->done ? 'btn-success' : 'btn-light border' }}" title="{{ $it->done ? 'ครบแล้ว (กดเพื่อปลด)' : 'ยังไม่ครบ (กดเมื่อครบ)' }}" style="width:58px"><i class="bi bi-{{ $it->done ? 'check2-circle' : 'circle' }}"></i> {{ $it->code }}</button>
                        </form>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold">{{ $it->title }}</div>
                            @if ($it->description)<div class="small text-muted">{{ $it->description }}</div>@endif
                            @forelse ($it->evidences as $e)
                                <div class="d-flex gap-2 align-items-center small mt-1">
                                    <i class="bi bi-{{ ['file' => 'file-earmark-pdf text-danger', 'link' => 'link-45deg text-primary', 'post' => 'newspaper text-success'][$e->kind] ?? 'link' }}"></i>
                                    @if ($e->href())<a href="{{ $e->href() }}" target="_blank" class="text-truncate">{{ $e->title }}</a>@else<span class="text-muted text-decoration-line-through">{{ $e->title }} (ข่าวถูกลบแล้ว)</span>@endif
                                    <form method="POST" action="{{ route('website.ita.evidence.destroy', $e) }}" data-confirm="ลบหลักฐานนี้?" class="ms-auto">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-danger p-0" title="ลบหลักฐาน"><i class="bi bi-x-lg"></i></button></form>
                                </div>
                            @empty
                                <div class="small text-muted mt-1">ยังไม่มีหลักฐาน</div>
                            @endforelse
                        </div>
                        <button class="btn btn-sm btn-soft text-nowrap" data-bs-toggle="modal" data-bs-target="#itaEvidenceModal" data-evidence-url="{{ route('website.ita.evidence.store', $it) }}" data-evidence-code="{{ $it->code }} {{ $it->title }}"><i class="bi bi-paperclip"></i>เพิ่มหลักฐาน</button>
                        <button class="btn btn-sm btn-light border" title="แก้หัวข้อ" data-edit="{{ json_encode(['_action' => route('website.ita.items.update', [$year->id, $it->id]), '_title' => 'แก้หัวข้อ '.$it->code, 'code' => $it->code, 'group' => $it->group, 'title' => $it->title, 'description' => $it->description, 'sort' => $it->sort]) }}" data-target="#itaItemModal"><i class="bi bi-pencil"></i></button>
                        <form method="POST" action="{{ route('website.ita.items.destroy', $it) }}" data-confirm="ลบหัวข้อ {{ $it->code }}?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" title="ลบหัวข้อ"><i class="bi bi-trash"></i></button></form>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="modal fade" id="itaItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('website.ita.items.store', $year->id) }}" class="modal-content" data-store-url="{{ route('website.ita.items.store', $year->id) }}">
                @csrf <input type="hidden" name="_method" value="POST">
                <div class="modal-header"><h5 class="modal-title">เพิ่มหัวข้อ</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-4"><label class="form-label">รหัสข้อ</label><input name="code" class="form-control" maxlength="10" placeholder="O1" required></div>
                        <div class="col-8"><label class="form-label">ลำดับ</label><input type="number" name="sort" class="form-control" min="0" max="999"></div>
                        <div class="col-12"><label class="form-label">ตัวชี้วัดย่อย / กลุ่ม</label><input name="group" class="form-control" maxlength="150" list="itaGroups">
                            <datalist id="itaGroups">@foreach ($year->items->pluck('group')->filter()->unique() as $g)<option value="{{ $g }}">@endforeach</datalist></div>
                        <div class="col-12"><label class="form-label">หัวข้อ</label><input name="title" class="form-control" maxlength="255" required></div>
                        <div class="col-12"><label class="form-label">รายละเอียด / สิ่งที่ต้องแสดง</label><textarea name="description" rows="3" class="form-control" maxlength="2000"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึก</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="itaEvidenceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="#" enctype="multipart/form-data" class="modal-content" id="itaEvidenceForm">
                @csrf
                <div class="modal-header"><h5 class="modal-title">เพิ่มหลักฐาน</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
                <div class="modal-body">
                    <div class="small text-muted mb-3" id="itaEvidenceFor"></div>
                    <div class="btn-group w-100 mb-3" role="group">
                        @foreach (\App\Models\ItaEvidence::KINDS as $k => $l)
                            <input type="radio" class="btn-check" name="kind" value="{{ $k }}" id="kind-{{ $k }}" @checked($k === 'file')>
                            <label class="btn btn-outline-secondary" for="kind-{{ $k }}">{{ $l }}</label>
                        @endforeach
                    </div>
                    <div data-kind="file"><label class="form-label">ไฟล์ (PDF, Word, Excel, รูป)</label><input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.zip"><div class="form-text">ไม่เกิน 20 MB</div></div>
                    <div data-kind="link" hidden><label class="form-label">ลิงก์</label><input name="url" class="form-control" placeholder="https://"></div>
                    <div data-kind="post" hidden><label class="form-label">ข่าวบนเว็บไซต์</label>
                        <select name="web_post_id" class="form-select" data-search>
                            <option value="">- เลือกข่าว -</option>
                            @foreach ($posts as $p)<option value="{{ $p->id }}">{{ thai_date($p->published_at) }} · {{ $p->title }}</option>@endforeach
                        </select></div>
                    <div class="mt-3"><label class="form-label">ชื่อที่แสดง</label><input name="title" class="form-control" maxlength="255" placeholder="เว้นว่าง = ใช้ชื่อไฟล์ / หัวข่าว"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary"><i class="bi bi-save"></i>เพิ่มหลักฐาน</button></div>
            </form>
        </div>
    </div>
@endif

<div class="modal fade" id="itaYearModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('website.ita.years.store') }}" class="modal-content">
            @csrf
            <div class="modal-header"><h5 class="modal-title">สร้างปีงบประมาณ</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
            <div class="modal-body">
                <label class="form-label">ปีงบประมาณ (พ.ศ.)</label>
                <input type="number" name="year" class="form-control mb-3" value="{{ $suggestYear }}" min="2560" max="2700" required>
                <label class="form-check mb-2"><input type="radio" class="form-check-input" name="from" value="copy" @checked($years->isNotEmpty()) @disabled($years->isEmpty())> คัดลอกหัวข้อจากปีก่อน (ไม่คัดลอกหลักฐาน)</label>
                <label class="form-check"><input type="radio" class="form-check-input" name="from" value="template" @checked($years->isEmpty())> ใช้รายการตั้งต้นของระบบ ({{ count(\App\Support\ItaTemplate::ITEMS) }} หัวข้อ)</label>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">ยกเลิก</button><button class="btn btn-primary"><i class="bi bi-plus-lg"></i>สร้าง</button></div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
  const m = document.getElementById('itaEvidenceModal');
  if (!m) return;
  const f = document.getElementById('itaEvidenceForm');
  const sync = () => { const k = f.querySelector('[name=kind]:checked').value; f.querySelectorAll('[data-kind]').forEach((b) => { b.hidden = b.dataset.kind !== k; }); };
  f.querySelectorAll('[name=kind]').forEach((r) => r.addEventListener('change', sync));
  m.addEventListener('show.bs.modal', (e) => {
    const b = e.relatedTarget; f.reset(); f.action = b.dataset.evidenceUrl;
    document.getElementById('itaEvidenceFor').textContent = b.dataset.evidenceCode; sync();
  });
})();
</script>
@endpush
