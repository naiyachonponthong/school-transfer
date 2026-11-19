<form method="POST" action="{{ route('settings.update') }}">
    @csrf
    <input type="hidden" name="section" value="payment">
    <div class="set-head"><h3>ช่องทางรับชำระเงิน</h3><p>แสดงในใบแจ้งหนี้และหน้าผู้ปกครอง พร้อมเพย์จะสร้าง QR ให้อัตโนมัติ</p></div>
    <div class="row g-3">
        <div class="col-md-5"><label class="form-label">พร้อมเพย์</label><input name="promptpay_id" value="{{ old('promptpay_id', $settings['promptpay_id']) }}" class="form-control @error('promptpay_id') is-invalid @enderror" placeholder="เบอร์โทร / เลขผู้เสียภาษี"></div>
        <div class="col-md-7"><label class="form-label">บัญชีธนาคาร</label><textarea name="bank_info" rows="3" class="form-control" placeholder="ธนาคาร... เลขที่บัญชี... ชื่อบัญชี...">{{ old('bank_info', $settings['bank_info']) }}</textarea></div>
    </div>
    <div class="set-actions"><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึกช่องทางรับชำระ</button></div>
</form>
