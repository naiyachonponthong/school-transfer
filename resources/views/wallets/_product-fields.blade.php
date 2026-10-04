<div class="col-12"><label class="form-label">ชื่อสินค้า</label><input name="name" value="{{ $product?->name }}" class="form-control" maxlength="255" required placeholder="เช่น ข้าวราดแกง 1 อย่าง"></div>
<div class="col-md-5"><label class="form-label">ราคา (บาท)</label><input type="number" name="price" value="{{ $product ? (float) $product->price : '' }}" class="form-control" min="0.01" max="99999" step="0.01" inputmode="decimal" required></div>
<div class="col-md-4"><label class="form-label">หมวด</label><input name="category" value="{{ $product?->category }}" class="form-control" maxlength="60" placeholder="เช่น อาหาร"></div>
<div class="col-md-3"><label class="form-label">ลำดับ</label><input type="number" name="sort" value="{{ $product?->sort ?? 0 }}" class="form-control" min="0" max="9999"></div>
@if ($product)
    <div class="col-12"><div class="form-check form-switch">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="productActive{{ $product->id }}" @checked($product->is_active)>
        <label class="form-check-label" for="productActive{{ $product->id }}">ขายอยู่ (ปิดเมื่อของหมดหรืองดขาย)</label>
    </div></div>
@endif
