<form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="section" value="website">
    <div class="set-head"><h3>เว็บไซต์โรงเรียน</h3><p>หน้าเว็บสาธารณะของโรงเรียน คนทั่วไปที่เปิดโดเมนจะเห็นหน้านี้ ครูและผู้ปกครองที่เข้าสู่ระบบแล้วยังเห็นแดชบอร์ดตามเดิม</p></div>

    <div class="license-status {{ $settings['website_enabled'] === '1' ? 'ok' : 'warn' }} mb-3">
        <span class="ico"><i class="bi bi-globe2"></i></span>
        <div class="flex-grow-1">
            <label class="form-check form-switch fw-bold fs-6 mb-1"><input type="checkbox" class="form-check-input" name="website_enabled" value="1" @checked($settings['website_enabled'] === '1')> เปิดเว็บไซต์ให้คนทั่วไปเห็น</label>
            <div class="small text-muted">ปิดอยู่ = หน้าแรกเป็นหน้าเข้าสู่ระบบเหมือนเดิม ผู้จัดการเว็บไซต์ยังดูตัวอย่างได้ที่ <a href="{{ route('web.home') }}" target="_blank">{{ route('web.home') }}</a></div>
        </div>
        <a href="{{ route('website.admin') }}" class="btn btn-light border"><i class="bi bi-newspaper"></i>จัดการข่าวและเนื้อหา</a>
    </div>

    <div class="row g-3">
        <div class="col-12"><label class="form-label">คำขวัญ / ข้อความใต้ชื่อโรงเรียน</label><input name="website_tagline" value="{{ old('website_tagline', $settings['website_tagline']) }}" class="form-control" maxlength="200" placeholder="เช่น เรียนดี มีวินัย ใฝ่คุณธรรม"></div>
    </div>

    <div class="set-block">
        <div class="set-block-title"><i class="bi bi-info-circle"></i>แนะนำโรงเรียน (หน้าแรก)</div>
        <div class="row g-3">
            <div class="col-md-7"><label class="form-label">ข้อความแนะนำเบื้องต้น</label><textarea name="website_intro" rows="5" class="form-control" maxlength="3000" placeholder="เว้นว่าง = ไม่แสดงส่วนนี้">{{ old('website_intro', $settings['website_intro']) }}</textarea></div>
            <div class="col-md-5"><label class="form-label">วิดีโอแนะนำโรงเรียน (YouTube)</label><input name="website_video" value="{{ old('website_video', $settings['website_video']) }}" class="form-control @error('website_video') is-invalid @enderror" placeholder="https://youtu.be/...">@error('website_video')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">หน้าเนื้อหาที่ใส่รูปประกอบ จะขึ้นเป็นการ์ดข้างวิดีโอ (เช่น หลักสูตร)</div></div>
        </div>
    </div>

    <div class="set-block">
        <div class="set-block-title"><i class="bi bi-person-badge"></i>สารจากผู้อำนวยการ</div>
        <div class="row g-3">
            <div class="col-md-8"><textarea name="website_greeting" rows="6" class="form-control" maxlength="5000" placeholder="เว้นว่าง = ไม่แสดงส่วนนี้บนหน้าแรก">{{ old('website_greeting', $settings['website_greeting']) }}</textarea>
                <div class="form-text">ชื่อผู้อำนวยการใช้จาก ข้อมูลโรงเรียน ({{ $settings['director_name'] ?: 'ยังไม่ได้กรอก' }})</div></div>
            <div class="col-md-4">
                <label class="form-label">รูปผู้อำนวยการ</label>
                @if ($settings['website_director_photo'])<img src="{{ asset('storage/'.$settings['website_director_photo']) }}" alt="" class="d-block mb-2 rounded-3" style="width:120px;height:140px;object-fit:cover">@endif
                <input type="file" name="website_director_photo" class="form-control @error('website_director_photo') is-invalid @enderror" accept="image/jpeg,image/png,image/webp">
                @error('website_director_photo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>

    <div class="set-block">
        <div class="set-block-title"><i class="bi bi-share"></i>ช่องทางติดต่อและแผนที่</div>
        <div class="row g-3">
            <div class="col-md-6"><label class="form-label">เวลาทำการ</label><input name="website_hours" value="{{ old('website_hours', $settings['website_hours']) }}" class="form-control" maxlength="150"></div>
            <div class="col-md-6"><label class="form-label">อีเมลโรงเรียน</label><input name="website_email" value="{{ old('website_email', $settings['website_email']) }}" class="form-control @error('website_email') is-invalid @enderror">@error('website_email')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">Facebook</label><input name="website_facebook" value="{{ old('website_facebook', $settings['website_facebook']) }}" class="form-control @error('website_facebook') is-invalid @enderror" placeholder="https://facebook.com/...">@error('website_facebook')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">YouTube</label><input name="website_youtube" value="{{ old('website_youtube', $settings['website_youtube']) }}" class="form-control @error('website_youtube') is-invalid @enderror" placeholder="https://youtube.com/@...">@error('website_youtube')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-6"><label class="form-label">ลิงก์ LINE</label><input name="website_line" value="{{ old('website_line', $settings['website_line']) }}" class="form-control @error('website_line') is-invalid @enderror" placeholder="เว้นว่าง = ใช้ LINE OA ที่ตั้งไว้">@error('website_line')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label">แผนที่ Google Maps</label><input name="website_map_url" value="{{ old('website_map_url', $settings['website_map_url']) }}" class="form-control @error('website_map_url') is-invalid @enderror" placeholder="วางลิงก์จาก Google Maps">@error('website_map_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">ใส่ลิงก์อะไรก็ได้เพื่อเปิดแสดงแผนที่ ระบบปักหมุดจากพิกัดโรงเรียน (หัวข้อ สแกนหน้าประตูและ GPS) หรือชื่อโรงเรียนให้เอง ถ้าเป็นลิงก์ฝังแผนที่ (embed) จะใช้ตามนั้น</div></div>
        </div>
    </div>
    <p class="small text-muted mt-3 mb-0">ภาพพื้นหลังท้ายเว็บไซต์ เปลี่ยนได้ที่ หน้าตาและระบบ &gt; รูปภาพระบบ</p>
    <div class="set-actions"><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึกเว็บไซต์</button></div>
</form>
