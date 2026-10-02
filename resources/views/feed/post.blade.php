@php
    $me = auth()->user();
    $summary = $post->reactionSummary($me->id);
    $who = $post->student ?? $post->author;
    $whoPhoto = $post->student ? $post->student->photoUrl() : $post->author?->avatarUrl();
    $reactionIcons = [
        '👍' => ['icon' => 'bi-hand-thumbs-up-fill', 'label' => 'ถูกใจ', 'class' => 'react-like'],
        '❤️' => ['icon' => 'bi-heart-fill', 'label' => 'รักเลย', 'class' => 'react-love'],
        '👏' => ['icon' => 'bi-award-fill', 'label' => 'ชื่นชม', 'class' => 'react-applause'],
        '🎉' => ['icon' => 'bi-stars', 'label' => 'ยินดี', 'class' => 'react-celebrate'],
    ];
@endphp
<article class="feed-post" data-post="{{ $post->id }}">
    <div class="fp-head">
        <span class="sb-avatar">@if($whoPhoto)<img src="{{ $whoPhoto }}" alt="">@else{{ $who?->initials() ?? 'ร' }}@endif</span>
        <div class="meta">
            @if ($post->type === 'achievement' && $post->student)
                <b>{{ $post->student->fullName() }}</b> {{ $post->headline ?? 'ได้รับประกาศเกียรติคุณ' }}
                <span class="feed-tag">{{ $post->student->classroom?->name() }}</span>
            @elseif ($post->type === 'announcement')
                <b>{{ $post->author?->name ?? school('school_short') }}</b> ประกาศ
            @else
                <b>{{ $post->author?->name ?? school('school_short') }}</b>
                @if ($post->student) กับ <b>{{ $post->student->fullName() }}</b>@endif
            @endif
            <div class="when">
                {{ \App\Support\Thai::ago($post->created_at) }}
                @if ($post->audience !== 'all') · <i class="bi bi-people"></i> {{ $post->audience === 'classroom' ? 'ห้อง '.$post->classroom?->name() : \App\Models\Announcement::AUDIENCES[$post->audience] }}@endif
            </div>
        </div>
        @if ($post->canDelete($me))
            <div class="dropdown">
                <button class="icon-btn" style="width:32px;height:32px" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><form method="POST" action="{{ route('feed.destroy', $post) }}" data-confirm="ลบโพสต์นี้?">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>ลบโพสต์</button></form></li>
                </ul>
            </div>
        @endif
    </div>

    <div class="fp-body">
        @if ($post->type === 'achievement')
            <div class="fp-award {{ $post->icon === 'bi-star-fill' ? 'gold' : '' }}">
                <div class="fp-award-art">
                    <img src="{{ asset('assets/img/school-award.png') }}" alt="" aria-hidden="true" width="1254" height="1254" loading="lazy">
                    @if ($post->student)<span class="sb-avatar who">@if($post->student->photoUrl())<img src="{{ $post->student->photoUrl() }}" alt="">@else{{ $post->student->initials() }}@endif</span>@endif
                </div>
                <div class="fp-award-copy">
                    <span class="fp-award-eyebrow"><i class="bi bi-sparkles" aria-hidden="true"></i> เรื่องน่ายินดีของโรงเรียน</span>
                    @if ($post->title)<div class="title">{{ $post->title }}</div>@endif
                    @if ($post->body)<div class="desc">{{ $post->body }}</div>@endif
                </div>
            </div>
        @elseif ($post->type === 'announcement' && $post->announcement)
            <a href="{{ route('announcements.show', $post->announcement) }}" class="fp-ann">
                <div class="fw-bold"><i class="bi bi-megaphone-fill text-primary me-1"></i> {{ $post->title }}</div>
                <div class="small text-muted mt-1" style="white-space:pre-line">{{ \Illuminate\Support\Str::limit($post->body, 200) }}</div>
                <div class="small mt-2 text-primary fw-semibold">อ่านต่อ →</div>
            </a>
        @else
            @if ($post->title)<div class="fw-bold mb-1">{{ $post->title }}</div>@endif
            @if ($post->body)<div class="fp-text">{{ $post->body }}</div>@endif
        @endif
        @if ($post->imageUrl())
            <div class="fp-image"><img src="{{ $post->imageUrl() }}" alt="" loading="lazy"></div>
        @endif
    </div>

    <div class="fp-actions">
        @foreach (\App\Models\FeedPost::REACTIONS as $emoji)
            @php($r = $summary[$emoji] ?? ['count' => 0, 'mine' => false])
            @php($reaction = $reactionIcons[$emoji])
            <button type="button" class="react-btn {{ $reaction['class'] }} {{ $r['mine'] ? 'mine' : '' }}" data-react="{{ $emoji }}" data-url="{{ route('feed.react', $post) }}" title="{{ $reaction['label'] }}" aria-label="{{ $reaction['label'] }}" aria-pressed="{{ $r['mine'] ? 'true' : 'false' }}">
                <i class="bi {{ $reaction['icon'] }}" aria-hidden="true"></i> <span class="react-label">{{ $reaction['label'] }}</span> <span class="n">{{ $r['count'] ?: '' }}</span>
            </button>
        @endforeach
        @if ($post->type === 'achievement')<span class="small text-muted ms-1"><i class="bi bi-hand-thumbs-up"></i> ส่งกำลังใจให้กัน</span>@endif
    </div>
</article>
