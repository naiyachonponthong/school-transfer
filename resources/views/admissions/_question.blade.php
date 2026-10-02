{{-- คำถาม 1 ข้อในฟอร์มรับสมัคร · ชื่อช่อง = answers[{id}] · $saved = คำตอบที่บันทึกไว้ในร่าง (ถ้ามี) --}}
@php
    $name = 'answers['.$q['id'].']';
    $key = 'answers.'.$q['id'];
    $value = old($key, $saved['value'] ?? null);
    $hasFile = $q['type'] === 'file' && ! empty($saved['value']['path']);
    $star = $q['required'] ? ' *' : '';
    $invalid = $errors->has($key) || $errors->has($key.'.*') ? ' is-invalid' : '';
    $wide = in_array($q['type'], ['textarea', 'checkbox', 'radio', 'file'], true);
@endphp
<div class="{{ $wide ? 'col-12' : 'col-md-6' }}">
    <label class="form-label" for="{{ $q['id'] }}">{{ $q['label'] }}{{ $star }}</label>
    @switch($q['type'])
        @case('textarea')
            <textarea id="{{ $q['id'] }}" name="{{ $name }}" rows="3" class="form-control{{ $invalid }}">{{ $value }}</textarea>
            @break
        @case('number')
            <input id="{{ $q['id'] }}" type="number" step="any" name="{{ $name }}" value="{{ $value }}" class="form-control{{ $invalid }}">
            @break
        @case('date')
            <input id="{{ $q['id'] }}" type="date" name="{{ $name }}" value="{{ $value }}" class="form-control{{ $invalid }}">
            @break
        @case('select')
            <select id="{{ $q['id'] }}" name="{{ $name }}" class="form-select{{ $invalid }}">
                <option value="">- เลือก -</option>
                @foreach ($q['options'] as $o)<option @selected($value === $o)>{{ $o }}</option>@endforeach
            </select>
            @break
        @case('radio')
            <div class="d-flex flex-wrap gap-3{{ $invalid }}">
                @foreach ($q['options'] as $o)
                    <label class="form-check"><input type="radio" class="form-check-input" name="{{ $name }}" value="{{ $o }}" @checked($value === $o)> {{ $o }}</label>
                @endforeach
            </div>
            @break
        @case('checkbox')
            <div class="d-flex flex-wrap gap-3{{ $invalid }}">
                @foreach ($q['options'] as $o)
                    <label class="form-check"><input type="checkbox" class="form-check-input" name="{{ $name }}[]" value="{{ $o }}" @checked(in_array($o, (array) $value, true))> {{ $o }}</label>
                @endforeach
            </div>
            @break
        @case('file')
            @if ($hasFile)
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2 small">
                    <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-check2"></i> แนบแล้ว</span>
                    <a href="{{ route('apply.file', $q['id']) }}" target="_blank">{{ $saved['value']['name'] ?? 'ไฟล์แนบ' }}</a>
                    <label class="form-check ms-2 mb-0 text-danger"><input type="checkbox" class="form-check-input" name="remove[{{ $q['id'] }}]" value="1"> ลบไฟล์นี้</label>
                </div>
            @endif
            <input id="{{ $q['id'] }}" type="file" name="{{ $name }}" accept="{{ $q['photo'] ? 'image/jpeg,image/png' : 'image/*,.pdf' }}" class="form-control{{ $invalid }}">
            @break
        @default
            <input id="{{ $q['id'] }}" name="{{ $name }}" value="{{ $value }}" class="form-control{{ $invalid }}">
    @endswitch
    @if ($q['help'] || $q['type'] === 'file')
        <div class="form-text">{{ $q['help'] ?: 'รูปหรือ PDF ไม่เกิน 6 MB' }}{{ $hasFile ? ' · เลือกไฟล์ใหม่เพื่อแทนที่' : '' }}</div>
    @endif
    @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    @error($key.'.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
</div>
