@if ($messages->isEmpty())
    <div class="text-center text-muted py-5"><i class="bi bi-envelope fs-1 d-block mb-2"></i>ยังไม่มีข้อความจากหน้า ติดต่อเรา</div>
@else
    <div class="list-group list-group-flush">
        @foreach ($messages as $m)
            <div class="list-group-item px-0 {{ $m->read_at ? '' : 'bg-light' }}">
                <div class="d-flex gap-2 align-items-start">
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold">@unless ($m->read_at)<span class="badge rounded-pill text-bg-danger me-1">ใหม่</span>@endunless{{ $m->subject ?: 'ไม่มีหัวเรื่อง' }}</div>
                        <div class="small text-muted mb-2">{{ $m->name }} · {{ $m->contact }} · {{ thai_datetime($m->created_at) }}</div>
                        <div style="white-space:pre-wrap;overflow-wrap:anywhere">{{ $m->message }}</div>
                    </div>
                    <form method="POST" action="{{ route('website.messages.read', $m) }}">@csrf<button class="btn btn-sm btn-light border" title="{{ $m->read_at ? 'ทำเป็นยังไม่อ่าน' : 'ทำเป็นอ่านแล้ว' }}"><i class="bi bi-{{ $m->read_at ? 'envelope' : 'envelope-open' }}"></i></button></form>
                    <form method="POST" action="{{ route('website.messages.destroy', $m) }}" data-confirm="ลบข้อความนี้?">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" title="ลบ"><i class="bi bi-trash"></i></button></form>
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-3">{{ $messages->links() }}</div>
@endif
