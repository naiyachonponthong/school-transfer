{{-- ช่องของฟอร์มกิจกรรม: ชื่อ + งบแยกประเภทเงินของปีงบประมาณของโครงการ ($a = กิจกรรมเดิม หรือว่าง) --}}
@if ($sources->isEmpty())
    <div class="col-12 small text-muted">ยังไม่มีประเภทเงินของปีงบประมาณ {{ $p->fiscal_year }} ให้เพิ่มที่<a href="{{ route('budget.index', ['year' => $p->fiscal_year]) }}">หน้างบประมาณ</a>ก่อน</div>
@else
    <div class="col-12"><label class="form-label">ชื่อกิจกรรม</label><input name="name" value="{{ $a?->name }}" class="form-control" maxlength="255" required></div>
    <div class="col-12"><label class="form-label">รายละเอียด <span class="text-muted small">(ไม่บังคับ)</span></label><input name="detail" value="{{ $a?->detail }}" class="form-control" maxlength="2000"></div>
    <div class="col-12 fw-semibold small mb-0">งบของกิจกรรมแยกประเภทเงิน (บาท)</div>
    @foreach ($sources as $s)
        @php($current = $a ? (float) ($a->budgets->firstWhere('budget_source_id', $s->id)?->amount ?? 0) : 0)
        <div class="col-md-6">
            <label class="form-label small mb-1" for="b{{ $a?->id ?? 'n' }}-{{ $s->id }}">{{ $s->name }}</label>
            <input type="number" name="budgets[{{ $s->id }}]" id="b{{ $a?->id ?? 'n' }}-{{ $s->id }}" value="{{ $current ?: '' }}" class="form-control" min="0" step="0.01" inputmode="decimal" placeholder="0">
            <div class="form-text">จัดสรรได้อีก {{ baht(max(0, $sourceLeft[$s->id] + $current)) }}</div>
        </div>
    @endforeach
    <div class="col-12 small text-muted">เว้นว่างหรือ 0 = ไม่ใช้เงินประเภทนั้น · ลดต่ำกว่ายอดที่ตัดแล้วและรอพิจารณาไม่ได้</div>
@endif
