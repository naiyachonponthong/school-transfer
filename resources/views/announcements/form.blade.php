@extends('layouts.app')
@section('title', $announcement->exists ? 'แก้ไขประกาศ' : 'สร้างประกาศ')

@section('content')
@php($admin = auth()->user()->isAdmin())
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="page-head"><h1>{{ $announcement->exists ? 'แก้ไขประกาศ' : 'สร้างประกาศ' }}</h1></div>
        <form method="POST" action="{{ $announcement->exists ? route('announcements.update', $announcement) : route('announcements.store') }}" class="card">
            @csrf @if ($announcement->exists) @method('PUT') @endif
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">หัวข้อ</label>
                    <input name="title" value="{{ old('title', $announcement->title) }}" class="form-control form-control-lg" required autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">รายละเอียด</label>
                    <textarea name="body" rows="8" class="form-control" required>{{ old('body', $announcement->body) }}</textarea>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">ส่งถึง</label>
                        <select name="audience" class="form-select">
                            @foreach (\App\Models\Announcement::AUDIENCES as $k => $v)
                                @if ($admin || in_array($k, ['classroom', 'staff']))
                                    <option value="{{ $k }}" @selected(old('audience', $announcement->audience) === $k)>{{ $v }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6" data-show-when="audience=classroom">
                        <label class="form-label">ห้องเรียน</label>
                        <select name="classroom_id" class="form-select">
                            <option value="">- เลือก -</option>
                            @foreach ($classrooms as $c)<option value="{{ $c->id }}" @selected(old('classroom_id', $announcement->classroom_id) == $c->id)>{{ $c->name() }}</option>@endforeach
                        </select>
                        <div class="small text-muted mt-1">ผู้ปกครองของนักเรียนในห้องนี้จะเห็นประกาศ</div>
                    </div>
                </div>
                @if ($admin)
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="pinned" value="1" id="pinned" @checked(old('pinned', $announcement->pinned))>
                        <label class="form-check-label" for="pinned">ปักหมุดไว้ด้านบน</label>
                    </div>
                @endif
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <button class="btn btn-primary"><i class="bi bi-send"></i> {{ $announcement->exists ? 'บันทึก' : 'เผยแพร่' }}</button>
                <a href="{{ route('announcements.index') }}" class="btn btn-light border">ยกเลิก</a>
            </div>
        </form>
    </div>
</div>
@endsection
