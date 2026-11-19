<form method="POST" action="{{ route('settings.images') }}" enctype="multipart/form-data">
    @csrf
    <div class="set-head"><h3>รูปภาพระบบ</h3><p>เลือกภาพจากเครื่อง ดูตัวอย่าง แล้วกดบันทึก รองรับ JPG, PNG และ WebP ไม่เกิน 8 MB ต่อภาพ ภาพพื้นหลังโปร่งใสจะเข้ากับแบนเนอร์ได้ดีที่สุด</p></div>
    <div class="row g-3">
        @foreach (\App\Support\Artwork::SLOTS as $key => [$label, $description])
            <div class="col-md-6"><div class="card h-100"><div class="card-body">
                <h4 class="fs-6 fw-bold mb-1">{{ $label }}</h4><p class="text-muted small">{{ $description }}</p>
                <div class="artwork-preview"><img src="{{ \App\Support\Artwork::url($key) }}" alt="ตัวอย่าง{{ $label }}" id="preview-{{ $key }}" data-default="{{ \App\Support\Artwork::defaultUrl($key) }}"></div>
                <label class="form-label mt-3" for="art-{{ $key }}">เปลี่ยนรูป{{ $label }}</label>
                <input class="form-control artwork-input @error($key) is-invalid @enderror" type="file" id="art-{{ $key }}" name="{{ $key }}" accept="image/jpeg,image/png,image/webp" data-slot="{{ $key }}">
                @error($key)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                @if (filled($settings['artwork_'.$key] ?? ''))
                    <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="reset_{{ $key }}" value="1" id="reset-{{ $key }}"><label class="form-check-label" for="reset-{{ $key }}">กลับไปใช้รูปเริ่มต้น</label></div>
                @endif
            </div></div></div>
        @endforeach
    </div>
    <div class="set-actions"><button class="btn btn-primary"><i class="bi bi-check2"></i>บันทึกรูปภาพ</button></div>
    <p class="text-muted small mt-3 mb-0">โลโก้โรงเรียนเปลี่ยนได้ที่ ข้อมูลโรงเรียน ส่วนรูปนักเรียนและบุคลากรเปลี่ยนในหน้าของแต่ละคน</p>
</form>
@push('scripts')
<script>
document.querySelectorAll('.artwork-input').forEach((input) => {
  const preview = document.getElementById('preview-' + input.dataset.slot), original = preview.src;
  const reset = document.getElementById('reset-' + input.dataset.slot);
  input.addEventListener('change', () => {
    const file = input.files[0];
    if (!file) { preview.src = original; return; }
    if (file.size > 8 * 1024 * 1024) { alert('รูปภาพต้องไม่เกิน 8 MB'); input.value = ''; preview.src = original; return; }
    const reader = new FileReader(); reader.onload = () => { preview.src = reader.result; }; reader.readAsDataURL(file);
    if (reset) reset.checked = false;
  });
  reset?.addEventListener('change', function () { if (this.checked) { input.value = ''; preview.src = preview.dataset.default; } else { preview.src = original; } });
});
</script>
@endpush
