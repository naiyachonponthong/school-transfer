@extends('layouts.app')
@section('title', 'ข้อความ')

@push('head')
<style>
    .chat { display: grid; grid-template-columns: 330px minmax(0, 1fr); height: calc(100vh - 62px - 2.8rem); min-height: 480px; }
    .chat-list { border-right: 1px solid #f0f1f4; overflow-y: auto; }
    .chat-item { display: flex; gap: .7rem; padding: .75rem 1rem; color: var(--sb-text); border-bottom: 1px solid #f5f6f8; }
    .chat-item:hover { background: #fafbfc; text-decoration: none; color: var(--sb-text); }
    .chat-item.active { background: var(--sb-primary-50); }
    .chat-item .unread { background: var(--sb-primary); color: #fff; border-radius: 999px; font-size: .7rem; padding: 0 .45rem; font-weight: 700; align-self: center; }
    .thread { display: flex; flex-direction: column; min-width: 0; }
    .thread-head { padding: .75rem 1rem; border-bottom: 1px solid #f0f1f4; display: flex; align-items: center; gap: .7rem; }
    .thread-body { flex: 1; overflow-y: auto; padding: 1rem; background: #fafbfc; display: flex; flex-direction: column; gap: .35rem; }
    .bubble-row { display: flex; gap: .5rem; align-items: flex-end; max-width: 78%; }
    .bubble-row.mine { align-self: flex-end; flex-direction: row-reverse; }
    .bubble { background: #fff; border-radius: 16px 16px 16px 4px; padding: .5rem .8rem; box-shadow: var(--sb-shadow); white-space: pre-line; word-break: break-word; }
    .bubble-row.mine .bubble { background: var(--sb-primary); color: #fff; border-radius: 16px 16px 4px 16px; }
    .bubble img { max-width: 240px; border-radius: 10px; display: block; margin-top: .25rem; }
    .bubble-time { font-size: .68rem; color: var(--sb-muted); white-space: nowrap; }
    .day-sep { align-self: center; font-size: .72rem; color: var(--sb-muted); background: #eef0f3; border-radius: 999px; padding: .1rem .7rem; margin: .5rem 0; }
    .composer-bar { border-top: 1px solid #f0f1f4; padding: .6rem; display: flex; gap: .5rem; align-items: flex-end; background: #fff; }
    .composer-bar textarea { resize: none; border-radius: 18px; max-height: 120px; }
    @media (max-width: 991.98px) {
        .chat { grid-template-columns: 1fr; height: calc(100vh - 58px - 6rem); }
        .chat.has-current .chat-list { display: none; }
        .chat:not(.has-current) .thread { display: none; }
    }
</style>
@endpush

@section('content')
@php
    $me = auth()->user();
@endphp
<div class="card overflow-hidden">
    <div class="chat {{ $current ? 'has-current' : '' }}">
        <div class="chat-list">
            <div class="d-flex align-items-center p-3 border-bottom">
                <div class="fw-bold fs-5">ข้อความ</div>
                <button class="btn btn-primary btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#newChat"><i class="bi bi-pencil-square"></i> แชทใหม่</button>
            </div>
            @forelse ($conversations as $c)
                @php
                    $others = $c->others($me);
                    $unread = $c->unreadFor($me);
                @endphp
                <a href="{{ route('chat.index', ['c' => $c->id]) }}" class="chat-item {{ $current?->id === $c->id ? 'active' : '' }}">
                    <span class="sb-avatar">@if($others->first()?->avatarUrl())<img src="{{ $others->first()->avatarUrl() }}" alt="">@else{{ $others->first()?->initials() }}@endif</span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex"><span class="fw-semibold text-truncate">{{ $others->pluck('name')->implode(', ') }}</span>
                            <span class="ms-auto small text-muted ps-2">{{ $c->last_message_at ? \App\Support\Thai::ago($c->last_message_at) : '' }}</span></div>
                        <div class="small text-primary text-truncate">{{ $c->student?->fullName() }} · {{ $c->student?->classroom?->name() }}</div>
                        <div class="small text-muted text-truncate">@if($c->latestMessage){{ $c->latestMessage->user_id === $me->id ? 'คุณ: ' : '' }}@if($c->latestMessage->body){{ $c->latestMessage->body }}@else<i class="bi bi-image" aria-hidden="true"></i> รูปภาพ@endif @else เริ่มการสนทนา @endif</div>
                    </div>
                    @if ($unread)<span class="unread">{{ $unread }}</span>@endif
                </a>
            @empty
                <div class="empty"><i class="bi bi-chat-dots"></i>ยังไม่มีข้อความ<br><span class="small">กด "แชทใหม่" เพื่อเริ่มคุยกับ{{ $me->isParent() ? 'ครู' : 'ผู้ปกครอง' }}</span></div>
            @endforelse
        </div>

        <div class="thread">
            @if ($current)
                @php($others = $current->others($me))
                <div class="thread-head">
                    <a href="{{ route('chat.index') }}" class="icon-btn d-lg-none"><i class="bi bi-chevron-left"></i></a>
                    <span class="sb-avatar">{{ $others->first()?->initials() }}</span>
                    <div class="min-w-0">
                        <div class="fw-semibold text-truncate">{{ $others->pluck('name')->implode(', ') }}</div>
                        <div class="small text-muted">เรื่องของ {{ $current->student?->fullName() }} · {{ $current->student?->classroom?->name() }}</div>
                    </div>
                    @if ($me->isStaff() && $current->student)
                        <a href="{{ route('students.show', $current->student) }}" class="btn btn-sm btn-light border ms-auto">ข้อมูลนักเรียน</a>
                    @endif
                </div>
                <div class="thread-body" id="threadBody" data-poll="{{ route('chat.poll', $current) }}" data-last="{{ $messages->last()?->id ?? 0 }}">
                    @php($lastDate = null)
                    @foreach ($messages as $m)
                        @php($d = thai_date($m->created_at))
                        @if ($d !== $lastDate)<div class="day-sep">{{ $d }}</div>@php($lastDate = $d)@endif
                        <div class="bubble-row {{ $m->user_id === $me->id ? 'mine' : '' }}">
                            @if ($m->user_id !== $me->id)<span class="sb-avatar xs">{{ $m->user?->initials() }}</span>@endif
                            <div class="bubble">{{ $m->body }}@if($m->attachmentUrl())<a href="{{ $m->attachmentUrl() }}" target="_blank"><img src="{{ $m->attachmentUrl() }}" alt=""></a>@endif</div>
                            <span class="bubble-time">{{ $m->created_at->format('H:i') }}</span>
                        </div>
                    @endforeach
                </div>
                <form class="composer-bar" id="chatForm" method="POST" action="{{ route('chat.send', $current) }}" enctype="multipart/form-data">
                    @csrf
                    <label class="icon-btn mb-0" title="แนบรูป"><i class="bi bi-image"></i><input type="file" name="image" accept="image/*" class="d-none"></label>
                    <textarea name="body" rows="1" class="form-control" placeholder="พิมพ์ข้อความ... (Enter ส่ง, Shift+Enter ขึ้นบรรทัด)"></textarea>
                    <button class="btn btn-primary rounded-circle" style="width:42px;height:42px;padding:0"><i class="bi bi-send-fill"></i></button>
                </form>
            @else
                <div class="empty my-auto"><i class="bi bi-chat-square-heart"></i>เลือกการสนทนาทางซ้าย<br><span class="small">ข้อความใหม่จะแจ้งเตือนทาง LINE ด้วย (ถ้าเชื่อมไว้)</span></div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="newChat" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable"><form method="POST" action="{{ route('chat.start') }}" class="modal-content">
        @csrf
        <div class="modal-header"><h5 class="modal-title">เริ่มแชทใหม่</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            @if ($me->isParent())
                @forelse ($contacts as $group)
                    <div class="fw-semibold mb-1">น้อง{{ $group['student']->nickname ?: $group['student']->first_name }} ({{ $group['student']->classroom?->name() }})</div>
                    @foreach ($group['teachers'] as $t)
                        <label class="d-flex align-items-center gap-2 border rounded-3 p-2 mb-2" style="cursor:pointer">
                            <input type="radio" class="form-check-input m-0" name="pick" value="{{ $group['student']->id }}:{{ $t['id'] }}" required
                                   onchange="this.form.student_id.value=this.value.split(':')[0];this.form.teacher_id.value=this.value.split(':')[1]">
                            <span class="sb-avatar sm">{{ mb_substr(preg_replace('/^(นางสาว|นาย|นาง|Mr\.)\s*/u', '', $t['name']), 0, 1) }}</span>
                            <span>{{ $t['name'] }}<br><span class="small text-muted">{{ $t['role'] }}</span></span>
                        </label>
                    @endforeach
                @empty
                    <div class="text-muted">ยังไม่มีข้อมูลบุตรหลาน</div>
                @endforelse
                <input type="hidden" name="student_id"><input type="hidden" name="teacher_id">
            @else
                <label class="form-label">นักเรียน (ข้อความจะส่งถึงผู้ปกครองทุกคนของนักเรียนคนนี้)</label>
                <select name="student_id" class="form-select" required>
                    <option value="">- เลือก -</option>
                    @foreach ($contacts['students'] as $s)<option value="{{ $s->id }}">{{ $s->classroom?->name() }} #{{ $s->number }} {{ $s->fullName() }}{{ $s->nickname ? ' ('.$s->nickname.')' : '' }}</option>@endforeach
                </select>
            @endif
        </div>
        <div class="modal-footer"><button class="btn btn-primary">เริ่มแชท</button></div>
    </form></div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const body = document.getElementById('threadBody');
    const form = document.getElementById('chatForm');
    if (!body || !form) return;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const scroll = () => { body.scrollTop = body.scrollHeight; };
    const seen = new Set([...body.querySelectorAll('[data-id]')].map((e) => e.dataset.id));
    const add = (m) => {
        if (seen.has(String(m.id))) return;
        seen.add(String(m.id));
        body.insertAdjacentHTML('beforeend', `<div class="bubble-row ${m.mine ? 'mine' : ''}" data-id="${m.id}">${m.mine ? '' : `<span class="sb-avatar xs">${esc(m.initials)}</span>`}<div class="bubble">${esc(m.body)}${m.image ? `<a href="${esc(m.image)}" target="_blank"><img src="${esc(m.image)}"></a>` : ''}</div><span class="bubble-time">${esc(m.time)}</span></div>`);
        body.dataset.last = Math.max(Number(body.dataset.last), m.id);
        scroll();
    };
    scroll();

    const poll = async () => {
        try {
            const res = await fetch(body.dataset.poll + '?after=' + body.dataset.last, { headers: { Accept: 'application/json' } });
            if (res.ok) (await res.json()).messages.forEach(add);
        } catch (e) {}
    };
    setInterval(() => { if (!document.hidden) poll(); }, 4000);

    const ta = form.querySelector('textarea');
    ta.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
    ta.addEventListener('input', () => { ta.style.height = 'auto'; ta.style.height = Math.min(120, ta.scrollHeight) + 'px'; });
    form.querySelector('input[type=file]').addEventListener('change', () => form.requestSubmit());
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        if (!fd.get('body').trim() && !(fd.get('image') && fd.get('image').size)) return;
        const btn = form.querySelector('button'); btn.disabled = true;
        try {
            const res = await fetch(form.action, { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
            if (res.ok) { add((await res.json()).message); form.reset(); ta.style.height = 'auto'; }
            else alert('ส่งไม่สำเร็จ');
        } finally { btn.disabled = false; ta.focus(); }
    });
});
</script>
@endpush
