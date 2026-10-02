@extends('layouts.app')
@section('title', 'นำเข้าครุภัณฑ์')

@section('content')
<div class="page-head"><div><h1>นำเข้าครุภัณฑ์จาก Excel</h1><div class="sub">คัดลอกตารางใน Excel ทั้งหมด (รวมแถวหัวคอลัมน์) แล้ววางในช่องด้านล่าง · เลขครุภัณฑ์ที่มีอยู่แล้วจะอัปเดตข้อมูล</div></div></div>
<div class="row g-3">
    <div class="col-lg-8">
        <form method="POST" action="{{ route('assets.import.store') }}" class="card">
            @csrf
            <div class="card-body">
                <textarea name="data" rows="14" class="form-control font-monospace small" placeholder="เลขครุภัณฑ์&#9;ชื่อครุภัณฑ์&#9;ประเภท&#9;ราคา&#9;วันที่ได้มา&#9;สถานที่" required>{{ old('data') }}</textarea>
                <button class="btn btn-primary mt-3"><i class="bi bi-upload"></i> นำเข้า</button>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-header">หัวคอลัมน์ที่รู้จัก</div><div class="card-body small">
            @foreach ($columns as $key => $names)
                <div class="mb-1"><b>{{ $names[0] }}</b>@if(in_array($key, ['code', 'name'], true)) <span class="text-danger">*</span>@endif <span class="text-muted">{{ implode(', ', array_slice($names, 1, -1)) }}</span></div>
            @endforeach
            <div class="text-muted mt-2">วันที่ใช้ได้ทั้ง 16/5/2569, 16/05/2026 หรือ 2026-05-16 · ราคามีจุลภาคได้ · คอลัมน์อื่นจะถูกข้าม</div>
        </div></div>
    </div>
</div>
@endsection
