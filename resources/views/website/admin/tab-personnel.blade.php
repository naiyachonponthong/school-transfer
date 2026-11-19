<form method="POST" action="{{ route('website.personnel.save') }}">
    @csrf
    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
        <p class="text-muted mb-0">เลือกบุคลากรที่ยินยอมให้แสดงบนเว็บไซต์ แสดงเฉพาะชื่อ ตำแหน่ง และรูปโปรไฟล์ จัดกลุ่มตามฝ่ายหลักในโครงสร้างองค์กร</p>
        <div class="d-flex gap-2">
            <input type="search" class="form-control" placeholder="ค้นหาชื่อ" id="staffFilter" style="width:180px" aria-label="ค้นหาชื่อ">
            <button type="button" class="btn btn-light border" id="staffAll">เลือกทั้งหมด</button>
        </div>
    </div>
    <div class="row g-2">
        @foreach ($staff as $u)
            <div class="col-md-6 col-xl-4" data-name="{{ $u->name }} {{ $u->position }}">
                <label class="d-flex gap-2 align-items-center p-2 rounded-3 h-100" style="background:#fafbfc;cursor:pointer">
                    <input type="checkbox" class="form-check-input m-0" name="public[]" value="{{ $u->id }}" @checked($u->public_profile)>
                    @if ($u->avatarUrl())<img src="{{ $u->avatarUrl() }}" class="rounded-circle" style="width:38px;height:38px;object-fit:cover" alt="">@else<span class="rounded-circle d-grid" style="width:38px;height:38px;place-items:center;background:var(--sb-primary-50);color:var(--sb-primary)"><i class="bi bi-person"></i></span>@endif
                    <span class="min-w-0"><span class="d-block fw-semibold text-truncate">{{ $u->name }}</span><small class="text-muted">{{ $u->position ?: 'ไม่ระบุตำแหน่ง' }}</small></span>
                </label>
            </div>
        @endforeach
    </div>
    <div class="d-flex justify-content-end mt-4 pt-3 border-top"><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึกทำเนียบบุคลากร</button></div>
</form>
@push('scripts')
<script>
document.getElementById('staffFilter').addEventListener('input', function () {
  const q = this.value.trim().toLowerCase();
  document.querySelectorAll('[data-name]').forEach((el) => { el.hidden = q && !el.dataset.name.toLowerCase().includes(q); });
});
document.getElementById('staffAll').addEventListener('click', () => document.querySelectorAll('[data-name]:not([hidden]) input[type=checkbox]').forEach((c) => { c.checked = true; }));
</script>
@endpush
