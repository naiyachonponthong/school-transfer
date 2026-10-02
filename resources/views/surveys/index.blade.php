@extends('layouts.app')
@section('title', 'แบบประเมิน')

@section('content')
<div class="page-head">
    <div><h1>แบบประเมิน / คัดกรองนักเรียน</h1><div class="sub">ระบบดูแลช่วยเหลือนักเรียน · คิดคะแนนรายด้านและแปลผลให้อัตโนมัติ</div></div>
    @if (auth()->user()->isAdmin())<div class="actions"><a href="{{ route('surveys.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> สร้างแบบประเมิน</a></div>@endif
</div>
<div class="alert alert-info small"><i class="bi bi-info-circle"></i> แบบมาตรฐานที่มีลิขสิทธิ์ เช่น SDQ หรือแบบประเมิน EQ ของกรมสุขภาพจิต โรงเรียนต้องได้รับอนุญาตจากเจ้าของก่อนนำข้อคำถามมาใส่ในระบบ แบบ "ตัวอย่าง" ที่ให้มาเขียนขึ้นเพื่อสาธิตการใช้งานเท่านั้น ไม่ใช่เครื่องมือที่ผ่านการตรวจสอบความเที่ยงตรง</div>

<div class="row g-3">
    @forelse ($surveys as $s)
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 {{ $s->is_active ? '' : 'opacity-50' }}">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex gap-2 mb-2">
                        <span class="app-ico" style="width:44px;height:44px;font-size:1.15rem;flex-shrink:0"><i class="bi bi-clipboard-heart"></i></span>
                        <div><div class="fw-bold">{{ $s->title }}</div><div class="small text-muted">{{ $s->items_count }} ข้อ · {{ \App\Models\Survey::RESPONDENTS[$s->respondent] }}</div></div>
                    </div>
                    <div class="small text-muted flex-grow-1">{{ $s->description }}</div>
                    <div class="small mt-2">ประเมินแล้วภาคนี้ <b>{{ $s->responses_count }}</b> ครั้ง</div>
                    <div class="d-flex gap-2 mt-3">
                        @if ($s->allows('teacher') && $s->is_active)
                            <form method="GET" action="{{ route('surveys.classroom', $s) }}" class="d-flex gap-2 flex-grow-1">
                                <select name="classroom" class="form-select form-select-sm">@foreach ($classrooms as $c)<option value="{{ $c->id }}">{{ $c->name() }}</option>@endforeach</select>
                                <button class="btn btn-sm btn-primary text-nowrap">ประเมิน</button>
                            </form>
                        @elseif ($s->is_active)
                            <a href="{{ route('surveys.classroom', $s) }}" class="btn btn-sm btn-soft flex-grow-1">ดูผล (ผู้ปกครองตอบ)</a>
                        @endif
                        @if (auth()->user()->isAdmin())<a href="{{ route('surveys.edit', $s) }}" class="btn btn-sm btn-light border"><i class="bi bi-pencil"></i></a>@endif
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12"><div class="card"><div class="empty"><i class="bi bi-clipboard"></i>ยังไม่มีแบบประเมิน</div></div></div>
    @endforelse
</div>
@endsection
