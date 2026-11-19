<form method="POST" action="{{ route('settings.update') }}">
    @csrf
    <input type="hidden" name="section" value="appearance">
    <div class="set-head"><h3>หน้าตา</h3><p>สีหลักใช้กับเมนู ปุ่ม หัวแอปมือถือ และไอคอนทั้งระบบ แนะนำให้ใช้สีประจำโรงเรียน</p></div>
    @php($theme = \App\Support\Theme::color())
    <div class="set-block-title"><i class="bi bi-palette"></i>สีธีมของระบบ</div>
    <div class="swatches mb-3">
        @foreach (\App\Support\Theme::PRESETS as $hex => $name)
            <button type="button" class="swatch" style="background:{{ $hex }}" data-color="{{ $hex }}" title="{{ $name }}" aria-label="สี{{ $name }}"></button>
        @endforeach
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <input type="color" value="{{ $theme }}" class="form-control form-control-color" data-theme-input title="เลือกสีเอง">
        <input name="theme_color" value="{{ old('theme_color', $theme) }}" class="form-control @error('theme_color') is-invalid @enderror" style="width:130px" data-theme-text maxlength="7" aria-label="รหัสสี">
        <div class="d-flex align-items-center gap-2 ms-2" data-theme-preview style="--prev: {{ $theme }}">
            <span class="btn btn-sm text-white" style="background:var(--prev)">ปุ่มหลัก</span>
            <span class="badge rounded-pill" style="background:var(--prev)">ใหม่</span>
            <span class="rounded-3" style="width:34px;height:34px;background:var(--prev);display:inline-block"></span>
        </div>
    </div>
    @error('theme_color')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

    <div class="set-block">
        <div class="set-block-title"><i class="bi bi-c-circle"></i>เครดิตท้ายหน้า</div>
        <input name="brand_credit" value="{{ old('brand_credit', $settings['brand_credit']) }}" class="form-control @error('brand_credit') is-invalid @enderror" maxlength="150" placeholder="เช่น ดูแลระบบโดย งานเทคโนโลยีสารสนเทศ">
        <div class="form-text">แสดงท้ายหน้าเว็บและเอกสารพิมพ์ เว้นว่าง = ไม่แสดง</div>
    </div>
    <div class="set-block">
        <div class="set-block-title"><i class="bi bi-image"></i>โลโก้และรูปภาพ</div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ \App\Support\SettingsMenu::url('school') }}" class="btn btn-light border"><i class="bi bi-building"></i>เปลี่ยนโลโก้ (ข้อมูลโรงเรียน)</a>
            <a href="{{ \App\Support\SettingsMenu::url('images') }}" class="btn btn-light border"><i class="bi bi-images"></i>รูปภาพระบบ</a>
        </div>
    </div>
    <div class="set-actions"><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึกหน้าตา</button></div>
</form>
