@extends('layouts.site')

@section('content')
@php
    $videoId = \App\Support\Website::youtubeId(school('website_video'));
    $fb = school('website_facebook');
    $map = \App\Support\Website::mapEmbed();
    $monthShort = fn ($d) => \App\Support\Thai::MONTHS_SHORT[$d->month];
@endphp

{{-- แถบ 1: สไลด์ / แบนเนอร์เต็มความกว้าง --}}
@if ($slides->isNotEmpty())
    <section class="band-hero has-slides">
        <div id="siteCarousel" class="carousel slide carousel-fade site-carousel" data-bs-ride="carousel" data-bs-interval="6000">
            @if ($slides->count() > 1)
                <div class="carousel-indicators">
                    @foreach ($slides as $i => $s)<button type="button" data-bs-target="#siteCarousel" data-bs-slide-to="{{ $i }}" class="{{ $i === 0 ? 'active' : '' }}" aria-label="สไลด์ {{ $i + 1 }}"></button>@endforeach
                </div>
            @endif
            <div class="carousel-inner">
                @foreach ($slides as $i => $s)
                    <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                        @if ($s->url)<a href="{{ $s->url }}">@endif
                        <img src="{{ $s->imageUrl() }}" alt="{{ $s->title ?: school('school_name') }}" @if ($i > 0) loading="lazy" @endif>
                        @if ($s->title || $s->caption)
                            <div class="slide-caption"><div class="site-wrap">@if ($s->title)<h2>{{ $s->title }}</h2>@endif @if ($s->caption)<p>{{ $s->caption }}</p>@endif</div></div>
                        @endif
                        @if ($s->url)</a>@endif
                    </div>
                @endforeach
            </div>
            @if ($slides->count() > 1)
                <button class="carousel-control-prev" type="button" data-bs-target="#siteCarousel" data-bs-slide="prev" aria-label="ก่อนหน้า"><span class="carousel-control-prev-icon"></span></button>
                <button class="carousel-control-next" type="button" data-bs-target="#siteCarousel" data-bs-slide="next" aria-label="ถัดไป"><span class="carousel-control-next-icon"></span></button>
            @endif
        </div>
    </section>
@else
    <section class="band-hero">
        <div class="site-wrap band-hero-inner">
            <div class="band-hero-copy">
                <span class="eyebrow">ยินดีต้อนรับสู่</span>
                <h1>{{ school('school_name') }}</h1>
                <p>{{ school('website_tagline') ?: 'ร่วมกันสร้างการเรียนรู้ที่มีคุณภาพ เพื่อนักเรียนทุกคน' }}</p>
                <div class="d-flex gap-2 flex-wrap mt-4">
                    @if ($admissionOpen)<a href="{{ route('apply') }}" class="btn btn-primary btn-lg"><i class="bi bi-person-plus"></i>สมัครเรียนออนไลน์</a>@endif
                    <a href="{{ route('web.news') }}" class="btn btn-light btn-lg"><i class="bi bi-newspaper"></i>ข่าวล่าสุด</a>
                </div>
            </div>
            <img class="band-hero-art" src="{{ \App\Support\Artwork::url('dashboard') }}" alt="" aria-hidden="true">
        </div>
    </section>
@endif

{{-- แถบ 2: ปุ่มบริการ ซ้อนขอบล่างของแบนเนอร์ --}}
<section class="band band-tint band-services">
    <div class="site-wrap">
        <nav class="site-services" aria-label="บริการ">
            <a href="{{ route('login') }}" class="site-service"><span class="ico"><i class="bi bi-person-workspace"></i></span><b>ครูและบุคลากร</b><small>เข้าสู่ระบบ</small></a>
            <a href="{{ route('login') }}" class="site-service"><span class="ico teal"><i class="bi bi-people"></i></span><b>ผู้ปกครอง</b><small>ผลการเรียน ค่าเทอม</small></a>
            <a href="{{ route('login') }}" class="site-service"><span class="ico blue"><i class="bi bi-backpack"></i></span><b>นักเรียน</b><small>ตารางเรียน การบ้าน</small></a>
            <a href="{{ route('apply') }}" class="site-service"><span class="ico"><i class="bi bi-person-plus"></i></span><b>สมัครเรียน</b><small>{{ $admissionOpen ? 'เปิดรับสมัครอยู่' : 'ติดตามประกาศ' }}</small></a>
            <a href="{{ route('web.news') }}" class="site-service"><span class="ico teal"><i class="bi bi-newspaper"></i></span><b>ข่าวสาร</b><small>ประชาสัมพันธ์ กิจกรรม</small></a>
            <a href="{{ route('web.contact') }}" class="site-service"><span class="ico slate"><i class="bi bi-telephone"></i></span><b>ติดต่อเรา</b><small>แผนที่ เบอร์โทร</small></a>
        </nav>
        <div class="site-stats">
            <div class="site-stat"><span class="ico"><i class="bi bi-backpack"></i></span><div><b>{{ number_format($stats['students']) }}</b><small>นักเรียน</small></div></div>
            <div class="site-stat"><span class="ico"><i class="bi bi-person-workspace"></i></span><div><b>{{ number_format($stats['teachers']) }}</b><small>ครูและบุคลากร</small></div></div>
            <div class="site-stat"><span class="ico"><i class="bi bi-door-open"></i></span><div><b>{{ number_format($stats['classrooms']) }}</b><small>ห้องเรียน</small></div></div>
        </div>
    </div>
</section>

{{-- แถบ 3: สารผู้อำนวยการ + ข่าวประชาสัมพันธ์ ในกล่องสีอ่อน --}}
@if (school('website_greeting') || $news->isNotEmpty())
<section class="band">
    <div class="site-wrap band-split {{ school('website_greeting') && $news->isNotEmpty() ? '' : 'single' }}">
        @if (school('website_greeting'))
            <aside class="director">
                @if (school('website_director_photo'))
                    <img class="director-photo" src="{{ asset('storage/'.school('website_director_photo')) }}" alt="{{ school('director_name') }}">
                @else
                    <div class="director-photo empty"><i class="bi bi-person"></i></div>
                @endif
                <span class="eyebrow">สารจากผู้อำนวยการ</span>
                <blockquote>{!! \App\Support\Website::text(school('website_greeting')) !!}</blockquote>
                <div class="director-name">{{ school('director_name') ?: 'ผู้อำนวยการ' }}</div>
                <small class="text-muted">ผู้อำนวยการ{{ school('school_name') }}</small>
            </aside>
        @endif
        @if ($news->isNotEmpty())
            <div class="panel-tint">
                <div class="panel-head"><h2>ข่าวประชาสัมพันธ์</h2></div>
                <div class="news-list two">
                    @foreach ($news as $p)
                        <a href="{{ route('web.post', $p) }}" class="news-row">
                            <span class="thumb">@if ($p->coverUrl())<img src="{{ $p->coverUrl() }}" alt="" loading="lazy">@else<i class="bi bi-megaphone"></i>@endif</span>
                            <span class="body"><b>@if ($p->pinned)<i class="bi bi-pin-angle-fill"></i> @endif @if ($p->urgent)<span class="news-urgent">ด่วน</span>@endif{{ $p->title }}</b><small>{{ thai_date($p->published_at) }}</small>@if ($p->body)<span>{{ $p->excerpt(90) }}</span>@endif</span>
                        </a>
                    @endforeach
                </div>
                <div class="text-end mt-3"><a href="{{ route('web.news.category', 'news') }}" class="btn btn-primary">ดูข่าวเพิ่มเติม <i class="bi bi-arrow-right"></i></a></div>
            </div>
        @endif
    </div>
</section>
@endif

{{-- แถบ 4: แนะนำโรงเรียน วิดีโอ หน้าเด่น และ Facebook บนพื้นสีเทาอ่อน --}}
@if ($videoId || school('website_intro') || $featurePages->isNotEmpty() || $fb)
<section class="band band-soft">
    <div class="site-wrap band-intro">
        <div class="intro-main">
            @if ($videoId)
                <div class="video-frame"><iframe src="https://www.youtube-nocookie.com/embed/{{ $videoId }}" title="วิดีโอแนะนำโรงเรียน" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>
            @endif
            @if (school('website_intro'))
                <h2 class="band-title mt-3">ข้อมูลแนะนำโรงเรียน</h2>
                <p class="intro-text">{!! \App\Support\Website::text(school('website_intro')) !!}</p>
                @if ($first = \App\Support\Website::menuPages()->first())<a href="{{ route('web.page', $first->slug) }}" class="btn btn-primary">รายละเอียด <i class="bi bi-arrow-right"></i></a>@endif
            @endif
        </div>
        @if ($featurePages->isNotEmpty())
            <div class="intro-cards">
                @foreach ($featurePages as $fp)
                    <a href="{{ route('web.page', $fp->slug) }}" class="feature-card">
                        <span class="img"><img src="{{ $fp->coverUrl() }}" alt="" loading="lazy"><b>{{ $fp->title }}</b></span>
                        <span class="txt">{{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $fp->body)), 110) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
        @if ($fb)
            <div class="intro-fb">
                <h2 class="band-title"><i class="bi bi-facebook"></i> Facebook</h2>
                <iframe class="fb-frame" title="Facebook ของโรงเรียน" loading="lazy" src="https://www.facebook.com/plugins/page.php?href={{ urlencode($fb) }}&tabs=timeline&width=340&height=460&small_header=true&hide_cover=false&show_facepile=false" allow="encrypted-media"></iframe>
            </div>
        @endif
    </div>
</section>
@endif

{{-- แถบ 5: ข่าวกิจกรรม | ข่าวจัดซื้อจัดจ้าง | แถบข้าง (รับสมัคร ปฏิทิน บริการ) --}}
<section class="band">
    <div class="site-wrap band-three">
        <div>
            <div class="band-title-row"><h2 class="band-title">ข่าวกิจกรรม</h2></div>
            @forelse ($activities as $p)
                <a href="{{ route('web.post', $p) }}" class="news-row line">
                    <span class="thumb">@if ($p->coverUrl())<img src="{{ $p->coverUrl() }}" alt="" loading="lazy">@else<i class="bi bi-camera"></i>@endif</span>
                    <span class="body"><b>{{ $p->title }}</b><small>{{ thai_date($p->published_at, true) }}</small>@if ($p->body)<span>{{ $p->excerpt(80) }}</span>@endif</span>
                </a>
            @empty
                <p class="text-muted">ยังไม่มีข่าวกิจกรรม</p>
            @endforelse
            @if ($activities->isNotEmpty())<div class="text-end mt-3"><a href="{{ route('web.news.category', 'activity') }}" class="btn btn-primary btn-sm">ดูข่าวทั้งหมด</a></div>@endif
        </div>
        <div>
            <div class="band-title-row"><h2 class="band-title">ข่าวจัดซื้อจัดจ้าง</h2></div>
            @forelse ($procurements as $p)
                <a href="{{ route('web.post', $p) }}" class="news-row line">
                    <span class="date-box"><b>{{ $p->published_at->day }}</b><small>{{ $monthShort($p->published_at) }}</small></span>
                    <span class="body"><b>{{ $p->title }}</b><small>{{ thai_date($p->published_at, true) }}@if ($p->attachments) · <i class="bi bi-paperclip"></i> {{ count($p->attachments) }} ไฟล์@endif</small></span>
                </a>
            @empty
                <p class="text-muted">ยังไม่มีข่าวจัดซื้อจัดจ้าง</p>
            @endforelse
            @if ($procurements->isNotEmpty())<div class="text-end mt-3"><a href="{{ route('web.news.category', 'procurement') }}" class="btn btn-primary btn-sm">ดูข่าวทั้งหมด</a></div>@endif
        </div>
        <aside class="side-tint">
            @if ($admissions->isNotEmpty())
                <h3>ข่าวรับสมัคร</h3>
                <ul>@foreach ($admissions as $p)<li><a href="{{ route('web.post', $p) }}">{{ $p->title }}</a></li>@endforeach</ul>
            @endif
            <h3>ปฏิทินกิจกรรม</h3>
            @forelse ($events as $e)
                <div class="mini-event"><span class="date-box"><b>{{ $e->start_date->day }}</b><small>{{ $monthShort($e->start_date) }}</small></span><span>{{ $e->title }}</span></div>
            @empty
                <p class="small text-muted mb-2">ยังไม่มีกิจกรรมที่กำลังจะมาถึง</p>
            @endforelse
            <h3>บริการของโรงเรียน</h3>
            <ul>
                <li><a href="{{ route('login') }}">ระบบสารสนเทศโรงเรียน</a></li>
                <li><a href="{{ route('apply') }}">สมัครเรียนออนไลน์</a></li>
                @foreach ($services as $l)<li><a href="{{ $l->url }}" target="_blank" rel="noopener">{{ $l->title }}</a></li>@endforeach
            </ul>
        </aside>
    </div>
</section>

{{-- แถบ ITA --}}
@if ($ita)
<section class="band">
    <div class="site-wrap">
        <a href="{{ route('web.ita', $ita->year) }}" class="ita-banner">
            <i class="bi bi-patch-check-fill"></i>
            <span><b>ITA การประเมินคุณธรรมและความโปร่งใส ปีงบประมาณ {{ $ita->year }}</b><small>การเปิดเผยข้อมูลสาธารณะ (OIT) ของโรงเรียน</small></span>
            <span class="btn btn-light btn-sm ms-auto">ดูข้อมูล <i class="bi bi-arrow-right"></i></span>
        </a>
    </div>
</section>
@endif

{{-- แถบ 6: หน่วยงานที่เกี่ยวข้อง --}}
@if ($agencies->isNotEmpty())
<section class="band band-tint">
    <div class="site-wrap">
        <h2 class="band-title text-center mb-4">หน่วยงานที่เกี่ยวข้อง</h2>
        <div class="agency-row">
            @foreach ($agencies as $l)
                <a href="{{ $l->url }}" class="agency" target="_blank" rel="noopener">@if ($l->logoUrl())<img src="{{ $l->logoUrl() }}" alt="">@else<i class="bi bi-{{ $l->icon ?: 'building' }}"></i>@endif<span>{{ $l->title }}</span></a>
            @endforeach
        </div>
    </div>
</section>
@endif

{{-- แถบ 7: ติดต่อ + แผนที่ --}}
<section class="band band-contact">
    <div class="site-wrap band-contact-inner">
        <div class="contact-map">
            @if ($map)<iframe src="{{ $map }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="แผนที่โรงเรียน"></iframe>@else<div class="map-empty"><i class="bi bi-geo-alt"></i></div>@endif
        </div>
        <div class="contact-info">
            <span class="eyebrow">ติดต่อเรา</span>
            <h2>{{ school('school_name') }}</h2>
            @if (school('school_address'))<p><i class="bi bi-geo-alt"></i> {{ school('school_address') }}</p>@endif
            @if (school('school_phone'))<p><i class="bi bi-telephone"></i> <a href="tel:{{ preg_replace('/[^0-9+]/', '', school('school_phone')) }}">{{ school('school_phone') }}</a></p>@endif
            @if (school('website_email'))<p><i class="bi bi-envelope"></i> <a href="mailto:{{ school('website_email') }}">{{ school('website_email') }}</a></p>@endif
            @if (school('website_hours'))<p><i class="bi bi-clock"></i> {{ school('website_hours') }}</p>@endif
            <a href="{{ route('web.contact') }}" class="btn btn-primary btn-lg mt-2"><i class="bi bi-send"></i>ส่งข้อความถึงโรงเรียน</a>
        </div>
    </div>
</section>
@endsection
