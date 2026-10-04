@extends('layouts.app')
@section('title', 'ร้าน '.$shop->name)

@section('content')
<div class="page-head">
    <div><h1>{{ $shop->name }}</h1><div class="sub">{{ $shop->location ?: 'ร้านค้าในโรงเรียน' }} · สินค้า {{ $shop->products->count() }} รายการ</div></div>
    <div class="actions">
        <a href="{{ route('wallets.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> กระเป๋าเงิน</a>
        <a href="{{ route('pos.show', $shop) }}" class="btn btn-light border"><i class="bi bi-shop"></i> เปิดหน้าจอขาย</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProduct"><i class="bi bi-plus-lg"></i> เพิ่มสินค้า</button>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <form method="POST" action="{{ route('wallets.shops.update', $shop) }}" class="card">
            @csrf @method('PUT')
            <div class="card-header"><i class="bi bi-shop"></i> ข้อมูลร้านและผู้ขาย</div>
            <div class="card-body row g-3">
                <div class="col-12"><label class="form-label">ชื่อร้าน</label><input name="name" value="{{ old('name', $shop->name) }}" class="form-control" maxlength="255" required></div>
                <div class="col-12"><label class="form-label">ที่ตั้ง</label><input name="location" value="{{ old('location', $shop->location) }}" class="form-control" maxlength="255"></div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="is_active" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="shopActive" @checked($shop->is_active)>
                        <label class="form-check-label" for="shopActive">เปิดขาย</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">ผู้ขายของร้านนี้</label>
                    <div class="border rounded-3 p-2" style="max-height:240px;overflow:auto">
                        @foreach ($staff as $u)
                            <div class="form-check small">
                                <input class="form-check-input" type="checkbox" name="cashiers[]" value="{{ $u->id }}" id="cashier{{ $u->id }}" @checked($shop->cashiers->contains('id', $u->id))>
                                <label class="form-check-label" for="cashier{{ $u->id }}">{{ $u->name }} <span class="text-muted">{{ $u->position }}</span>@unless ($u->hasPermission('pos.use')) <span class="text-warning-emphasis">· ยังไม่มีสิทธิ์ขาย</span>@endunless</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="form-text">คนขายต้องมีบัญชีในระบบและมีตำแหน่ง "ผู้ขาย/ร้านค้า" (กำหนดที่เมนูผู้ใช้งาน) จึงจะเปิดหน้าจอขายได้</div>
                </div>
            </div>
            <div class="card-footer bg-transparent text-end"><button class="btn btn-primary"><i class="bi bi-check-lg"></i> บันทึก</button></div>
        </form>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><i class="bi bi-grid"></i> สินค้า</div>
            <div class="table-responsive"><table class="table align-middle mb-0">
                <thead><tr><th>สินค้า</th><th>หมวด</th><th class="text-end">ราคา</th><th></th></tr></thead>
                <tbody>
                @forelse ($shop->products as $p)
                    <tr class="{{ $p->is_active ? '' : 'text-muted' }}">
                        <td>{{ $p->name }}@if (! $p->is_active) <span class="badge bg-secondary">งดขาย</span>@endif</td>
                        <td class="small">{{ $p->category ?: '-' }}</td>
                        <td class="text-end">{{ baht($p->price) }}</td>
                        <td class="text-end"><button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#editProduct{{ $p->id }}" aria-label="แก้ไข {{ $p->name }}"><i class="bi bi-pencil"></i></button></td>
                    </tr>
                @empty
                    <tr><td colspan="4"><div class="empty py-4"><i class="bi bi-grid"></i>ยังไม่มีสินค้า ร้านนี้ยังขายได้โดยกดจำนวนเงินเอง</div></td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>

@php
    $fields = fn ($p = null) => view('wallets._product-fields', ['product' => $p])->render();
@endphp
<div class="modal fade" id="addProduct" tabindex="-1">
    <div class="modal-dialog"><form method="POST" action="{{ route('wallets.products.store', $shop) }}" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">เพิ่มสินค้า</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
        <div class="modal-body row g-3">{!! $fields() !!}</div>
        <div class="modal-footer"><button class="btn btn-primary">เพิ่มสินค้า</button></div>
    </form></div>
</div>
@foreach ($shop->products as $p)
    <div class="modal fade" id="editProduct{{ $p->id }}" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('wallets.products.update', $p) }}" id="productForm{{ $p->id }}">
                @csrf @method('PUT')
                <div class="modal-header"><h5 class="modal-title">แก้ไข {{ $p->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button></div>
                <div class="modal-body row g-3">{!! $fields($p) !!}</div>
            </form>
            <div class="modal-footer">
                <form method="POST" action="{{ route('wallets.products.destroy', $p) }}" class="me-auto" data-confirm="ลบสินค้า {{ $p->name }}? (ประวัติการขายเดิมยังอยู่)">@csrf @method('DELETE')
                    <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> ลบ</button>
                </form>
                <button class="btn btn-primary" form="productForm{{ $p->id }}">บันทึก</button>
            </div>
        </div></div>
    </div>
@endforeach
@endsection
