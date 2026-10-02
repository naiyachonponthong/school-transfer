{{-- รับเข้า / ปรับยอด (ใช้ร่วมหน้ารายการและหน้าบัญชีวัสดุ) --}}
<div class="modal fade" id="move" tabindex="-1">
    <div class="modal-dialog"><form method="POST" class="modal-content" id="moveForm">
        @csrf
        <div class="modal-header"><h5 class="modal-title">รับเข้า / ปรับยอด: <span data-f="name"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="btn-group w-100 mb-3">
                <input type="radio" class="btn-check" name="type" value="in" id="mt-in" checked><label class="btn btn-outline-success" for="mt-in"><i class="bi bi-box-arrow-in-down"></i> รับเข้า (ซื้อ/บริจาค)</label>
                <input type="radio" class="btn-check" name="type" value="adjust" id="mt-adj"><label class="btn btn-outline-secondary" for="mt-adj"><i class="bi bi-sliders"></i> ปรับยอดตามตรวจนับ</label>
            </div>
            <div class="row g-3">
                <div class="col-6"><label class="form-label">จำนวน (<span data-f="unit"></span>)</label><input type="number" name="quantity" class="form-control form-control-lg" required><div class="form-text">คงเหลือตอนนี้ <span data-f="stock"></span> · ปรับยอดใส่ติดลบได้</div></div>
                <div class="col-6" data-in-only><label class="form-label">ราคาต่อหน่วยล่าสุด</label><input type="number" step="0.01" min="0" name="unit_price" class="form-control form-control-lg"></div>
                <div class="col-12"><label class="form-label">หมายเหตุ</label><input name="note" class="form-control" placeholder="เช่น ซื้อจากร้าน ก. ใบส่งของเลขที่ 123"></div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">บันทึก</button></div>
    </form></div>
</div>
@push('scripts')
<script>
(() => {
    const modal = document.getElementById('move');
    if (!modal) return;
    const f = document.getElementById('moveForm');
    modal.addEventListener('show.bs.modal', (e) => {
        const b = e.relatedTarget.dataset;
        f.action = b.moveUrl; f.reset();
        f.querySelector('[data-in-only]').hidden = false;
        f.querySelector('[data-f=name]').textContent = b.moveName;
        f.querySelector('[data-f=unit]').textContent = b.moveUnit;
        f.querySelector('[data-f=stock]').textContent = Number(b.moveStock).toLocaleString() + ' ' + b.moveUnit;
        f.unit_price.value = Number(b.movePrice) > 0 ? b.movePrice : '';
    });
    f.addEventListener('change', () => { f.querySelector('[data-in-only]').hidden = f.type.value !== 'in'; f.quantity.min = f.type.value === 'in' ? 1 : ''; });
})();
</script>
@endpush
