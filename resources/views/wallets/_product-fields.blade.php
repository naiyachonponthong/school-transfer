<div class="col-md-8"><label class="form-label">ชื่อสินค้า</label><input name="name" value="{{ $product?->name }}" class="form-control" maxlength="255" required placeholder="เช่น ข้าวราดแกง 1 อย่าง"></div>
<div class="col-md-4"><label class="form-label">ราคาขาย (บาท)</label><input type="number" name="price" value="{{ $product ? (float) $product->price : '' }}" class="form-control" min="0.01" max="99999" step="0.01" inputmode="decimal" required></div>
<div class="col-md-4"><label class="form-label">หมวด</label><input name="category" value="{{ $product?->category }}" class="form-control" maxlength="60" placeholder="เช่น อาหาร"></div>
<div class="col-md-4"><label class="form-label">หน่วย</label><input name="unit" value="{{ $product?->unit }}" class="form-control" maxlength="20" placeholder="เช่น จาน ขวด ชิ้น"></div>
<div class="col-md-4"><label class="form-label">ลำดับ</label><input type="number" name="sort" value="{{ $product?->sort ?? 0 }}" class="form-control" min="0" max="9999"></div>
<div class="col-md-4"><label class="form-label">บาร์โค้ด</label><input name="barcode" value="{{ $product?->barcode }}" class="form-control font-monospace" maxlength="40" placeholder="สแกนหรือพิมพ์"><div class="form-text">สแกนบาร์โค้ดนี้ที่หน้าจอขายเพื่อเพิ่มสินค้า</div></div>
<div class="col-md-4"><label class="form-label">ต้นทุนต่อหน่วย (บาท)</label><input type="number" name="cost" value="{{ $product?->cost !== null ? (float) $product->cost : '' }}" class="form-control" min="0" max="99999" step="0.01" inputmode="decimal"><div class="form-text">ไว้ดูกำไรขั้นต้นในรายงาน</div></div>
<div class="col-md-4"><label class="form-label">สต็อกคงเหลือ</label><input type="number" name="stock" value="{{ $product?->stock }}" class="form-control" min="0" max="999999" step="1" inputmode="numeric" placeholder="เว้นว่าง = ไม่นับ"><div class="form-text">ขายแล้วตัดให้เอง หมดแล้วขายไม่ได้</div></div>
<div class="col-12"><label class="form-label">รายละเอียด</label><textarea name="description" rows="2" class="form-control" maxlength="1000" placeholder="เช่น ส่วนประกอบ ขนาด หมายเหตุสำหรับผู้แพ้อาหาร">{{ $product?->description }}</textarea></div>
<div class="col-12">
    <label class="form-label">รูปสินค้า</label>
    <div class="d-flex align-items-center gap-3">
        @if ($product?->imageUrl())<img src="{{ $product->imageUrl() }}" alt="" class="rounded-3 border" style="width:72px;height:72px;object-fit:cover">@endif
        <div class="flex-grow-1">
            <input type="file" name="image" accept="image/*" class="form-control">
            @if ($product?->image)<label class="form-check mt-1 small"><input type="checkbox" class="form-check-input" name="remove_image" value="1"> ลบรูปนี้</label>@endif
        </div>
    </div>
</div>
@if ($product)
    <div class="col-12"><div class="form-check form-switch">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="productActive{{ $product->id }}" @checked($product->is_active)>
        <label class="form-check-label" for="productActive{{ $product->id }}">ขายอยู่ (ปิดเมื่องดขายชั่วคราว)</label>
    </div></div>
@endif
