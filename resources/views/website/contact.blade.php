@extends('layouts.site')
@section('title', 'ติดต่อเรา')

@section('content')
<div class="site-wrap">
    @include('website.partials.pagehead', ['title' => 'ติดต่อเรา', 'crumbs' => ['ติดต่อเรา' => null]])

    <div class="row g-3 mt-1">
        <div class="col-lg-5">
            <div class="site-card p-4 h-100">
                <h2 class="fs-5 fw-bold mb-3">{{ school('school_name') }}</h2>
                @if (school('school_address'))<p class="d-flex gap-2"><i class="bi bi-geo-alt text-primary"></i><span>{{ school('school_address') }}</span></p>@endif
                @if (school('school_phone'))<p class="d-flex gap-2"><i class="bi bi-telephone text-primary"></i><a href="tel:{{ preg_replace('/[^0-9+]/', '', school('school_phone')) }}">{{ school('school_phone') }}</a></p>@endif
                @if (school('website_hours'))<p class="d-flex gap-2"><i class="bi bi-clock text-primary"></i><span>{{ school('website_hours') }}</span></p>@endif
                @if (school('website_facebook'))<p class="d-flex gap-2"><i class="bi bi-facebook text-primary"></i><a href="{{ school('website_facebook') }}" target="_blank" rel="noopener">Facebook ของโรงเรียน</a></p>@endif
                @if ($map = \App\Support\Website::mapEmbed())
                    <iframe class="site-map mt-2" src="{{ $map }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="แผนที่โรงเรียน"></iframe>
                @endif
            </div>
        </div>
        <div class="col-lg-7">
            <form method="POST" action="{{ route('web.contact.send') }}" class="site-card p-4" novalidate>
                @csrf
                <h2 class="fs-5 fw-bold mb-1">ส่งข้อความถึงโรงเรียน</h2>
                <p class="text-muted small">เจ้าหน้าที่จะติดต่อกลับทางอีเมลหรือเบอร์โทรที่ให้ไว้</p>
                <input type="hidden" name="started" value="{{ $started }}">
                <div class="d-none" aria-hidden="true"><label>เว็บไซต์ <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label" for="c-name">ชื่อ</label><input id="c-name" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" maxlength="120" required>@error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-md-6"><label class="form-label" for="c-contact">อีเมลหรือเบอร์โทร</label><input id="c-contact" name="contact" value="{{ old('contact') }}" class="form-control @error('contact') is-invalid @enderror" maxlength="150" required>@error('contact')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <div class="col-12"><label class="form-label" for="c-subject">เรื่อง</label><input id="c-subject" name="subject" value="{{ old('subject') }}" class="form-control" maxlength="150"></div>
                    <div class="col-12"><label class="form-label" for="c-message">ข้อความ</label><textarea id="c-message" name="message" rows="6" class="form-control @error('message') is-invalid @enderror" maxlength="3000" required>{{ old('message') }}</textarea>@error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                </div>
                <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary btn-lg"><i class="bi bi-send"></i>ส่งข้อความ</button></div>
            </form>
        </div>
    </div>
</div>
@endsection
