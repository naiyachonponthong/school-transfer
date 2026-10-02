@if ($events->isNotEmpty())
    <div class="card {{ $class ?? '' }}">
        <div class="card-body pb-2">
            <div class="card-title-sm"><i class="bi bi-calendar-event text-primary"></i> กิจกรรมที่จะถึง <a href="{{ route('calendar') }}" class="ms-auto small fw-normal">ปฏิทิน</a></div>
            @foreach ($events as $e)
                <div class="d-flex gap-2 py-2 border-top align-items-center">
                    <div class="text-center rounded-3 tint-{{ $e->typeColor() }}" style="width:44px;padding:2px 0;flex-shrink:0">
                        <div class="fw-bold lh-1">{{ $e->start_date->day }}</div>
                        <div style="font-size:.68rem">{{ \App\Support\Thai::MONTHS_SHORT[$e->start_date->month] }}</div>
                    </div>
                    <div class="small min-w-0">
                        <div class="fw-semibold text-truncate">{{ $e->title }}</div>
                        <div class="text-muted">{{ $e->typeLabel() }}@if(! $e->end_date->eq($e->start_date)) · ถึง {{ thai_date($e->end_date) }}@endif</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
