<div class="mb-3">
    <label class="form-label" for="password">รหัสผ่านใหม่</label>
    <input id="password" type="password" name="password" class="form-control form-control-lg @error('password') is-invalid @enderror" autocomplete="new-password" required minlength="8">
    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">อย่างน้อย 8 ตัว มีทั้งตัวอักษรและตัวเลข ห้ามใช้ชื่อผู้ใช้หรือเบอร์โทร</div>
</div>
<div class="mb-4">
    <label class="form-label" for="password_confirmation">พิมพ์รหัสผ่านใหม่อีกครั้ง</label>
    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control form-control-lg" autocomplete="new-password" required>
</div>
