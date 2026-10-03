@extends('layouts.app')
@section('title', 'รายชื่อผู้เรียน '.$course->subject->name)

@section('content')
<div class="page-head">
    <div>
        <h1>รายชื่อผู้เรียน {{ $course->subject->name }}</h1>
        <div class="sub">{{ $course->subject->code }} · {{ $course->term->label() }} · ห้องหลัก {{ $course->classroom->name() }} · ไม่ติ๊กใครเลย = เรียนทั้งห้อง {{ $course->classroom->name() }}</div>
    </div>
    <div class="actions"><a href="{{ route('courses.index') }}" class="btn btn-light border"><i class="bi bi-arrow-left"></i> รายวิชา</a></div>
</div>

<form method="POST" action="{{ route('courses.members.update', $course) }}">
    @csrf @method('PUT')
    <div class="card">
        <div class="card-body small text-muted border-bottom">
            ใช้กับวิชาเลือกหรือชุมนุมที่รับนักเรียนบางคน หรือรับข้ามห้อง นักเรียนที่ติ๊กจะเห็นรายวิชานี้ในสมุดคะแนน เช็คชื่อรายคาบ การบ้าน และสมุดพก แทนรายชื่อทั้งห้อง
            · ตอนนี้ <b>{{ $memberIds->isEmpty() ? 'เรียนทั้งห้อง' : 'เลือกไว้ '.$memberIds->count().' คน' }}</b>
        </div>
        @foreach ($classrooms as $c)
            @continue($c->students->isEmpty())
            <details class="border-bottom px-3 py-2" @if ($c->id === $course->classroom_id || $c->students->pluck('id')->intersect($memberIds->keys())->isNotEmpty()) open @endif>
                <summary class="fw-semibold" style="cursor:pointer">{{ $c->name() }} <span class="text-muted fw-normal small">{{ $c->students->count() }} คน</span></summary>
                <div class="d-flex flex-wrap gap-3 mt-2">
                    @foreach ($c->students as $s)
                        <label class="small text-nowrap"><input type="checkbox" class="form-check-input" name="student_ids[]" value="{{ $s->id }}" @checked(isset($memberIds[$s->id]))> {{ $s->number }} {{ $s->fullName() }}</label>
                    @endforeach
                </div>
            </details>
        @endforeach
        <div class="card-footer bg-transparent text-end">
            @if ($course->locked)
                <span class="text-muted small"><i class="bi bi-lock-fill"></i> รายวิชาล็อกแล้ว</span>
            @else
                <button class="btn btn-primary"><i class="bi bi-check-lg"></i> บันทึกรายชื่อ</button>
            @endif
        </div>
    </div>
</form>
@endsection
