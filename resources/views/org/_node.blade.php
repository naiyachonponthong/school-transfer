{{-- กล่องหน่วยงานหนึ่งกล่องในผัง พร้อมหน่วยย่อย (เรียกซ้ำ) --}}
@php
    $children = $departments->where('parent_id', $d->id);
    $people = $d->members->reject(fn ($u) => $u->id === $d->head_id);
@endphp
<li>
    <div class="org-box kind-{{ $d->kind }}">
        <div class="d-flex align-items-start gap-2">
            <div class="min-w-0 flex-grow-1">
                <div class="fw-semibold">{{ $d->name }}</div>
                <div class="small text-muted">{{ $d->kindLabel() }}{{ $d->code ? ' · '.$d->code : '' }}</div>
            </div>
            @if ($canManage)<button class="btn btn-sm btn-light border d-print-none" data-bs-toggle="modal" data-bs-target="#editDept{{ $d->id }}" title="แก้ไข" aria-label="แก้ไข {{ $d->name }}"><i class="bi bi-pencil"></i></button>@endif
        </div>
        <div class="small mt-1"><i class="bi bi-person-badge"></i> {!! $d->head ? e($d->head->name) : '<span class="text-muted">ยังไม่ระบุหัวหน้า</span>' !!}</div>
        @if ($people->isNotEmpty())
            <details class="small mt-1">
                <summary><span class="badge bg-light text-body border">{{ $counts[$d->id] ?? 0 }} คน</span> <span class="text-muted">ในหน่วยนี้ {{ $people->count() }}</span></summary>
                <div class="text-muted mt-1">{{ $people->pluck('name')->implode(' · ') }}</div>
            </details>
        @else
            <div class="small mt-1"><span class="badge bg-light text-body border">{{ $counts[$d->id] ?? 0 }} คน</span></div>
        @endif
    </div>
    @if ($children->isNotEmpty())
        <ul>
            @foreach ($children as $child)
                @include('org._node', ['d' => $child])
            @endforeach
        </ul>
    @endif
</li>
