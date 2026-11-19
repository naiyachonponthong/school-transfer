@extends('layouts.app')
@section('title', 'เว็บไซต์โรงเรียน')

@push('head')
<link rel="stylesheet" href="{{ asset('assets/css/settings.css') }}?v={{ @filemtime(public_path('assets/css/settings.css')) }}">
@endpush

@section('content')
@php($tabs = \App\Http\Controllers\WebsiteAdminController::TABS)
<div class="page-head">
    <div>
        <h1>เว็บไซต์โรงเรียน</h1>
        <div class="sub">
            @if (\App\Support\Website::enabled())
                <span class="text-success"><i class="bi bi-circle-fill" style="font-size:.55rem"></i> เปิดให้คนทั่วไปเห็นแล้ว</span>
            @else
                <span class="text-warning"><i class="bi bi-circle-fill" style="font-size:.55rem"></i> ยังปิดอยู่ (ดูตัวอย่างได้เฉพาะผู้จัดการเว็บไซต์)</span>
            @endif
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if (auth()->user()->hasPermission('settings.manage'))<a href="{{ \App\Support\SettingsMenu::url('website') }}" class="btn btn-light border"><i class="bi bi-gear"></i>ตั้งค่าเว็บไซต์</a>@endif
        <a href="{{ route('web.home') }}" target="_blank" class="btn btn-soft"><i class="bi bi-box-arrow-up-right"></i>ดูเว็บไซต์</a>
    </div>
</div>

<div class="settings-workspace">
    <nav class="settings-subnav mb-4" aria-label="จัดการเว็บไซต์">
        @foreach ($tabs as $key => [$label, $icon])
            <a href="{{ route('website.admin', ['tab' => $key]) }}" class="{{ $tab === $key ? 'active' : '' }}"><i class="bi bi-{{ $icon }}"></i>{{ $label }}@if ($key === 'messages' && $unread)<span class="badge rounded-pill text-bg-danger ms-1">{{ $unread }}</span>@endif</a>
        @endforeach
    </nav>
    @include('website.admin.tab-'.$tab)
</div>
@endsection

@push('scripts')
<script>
// เปิด modal แก้ไข: ปุ่มที่มี data-edit (json) เติมค่าในฟอร์ม และเปลี่ยน action เป็น route แก้ไข
document.querySelectorAll('[data-edit]').forEach((btn) => btn.addEventListener('click', () => {
  const modal = document.querySelector(btn.dataset.target), form = modal.querySelector('form'), data = JSON.parse(btn.dataset.edit);
  form.reset();
  form.action = data._action;
  if (form.elements._action_url) form.elements._action_url.value = data._action;
  form.querySelector('[name=_method]').value = data._action === form.dataset.storeUrl ? 'POST' : 'PUT';
  modal.querySelector('.modal-title').textContent = data._title;
  Object.entries(data).forEach(([k, v]) => {
    const el = form.elements[k];
    if (!el || k.startsWith('_')) return;
    if (el.type === 'checkbox') el.checked = !!v; else if (el instanceof RadioNodeList) { el.value = v; } else el.value = v ?? '';
  });
  modal.querySelectorAll('[data-existing]').forEach((box) => {
    const items = data['_' + box.dataset.existing] || [];
    box.innerHTML = items.length ? items.map((it) => box.dataset.existing === 'images'
      ? `<label class="existing-thumb"><img src="${it.url}" alt=""><span><input type="checkbox" class="form-check-input" name="remove_images[]" value="${it.path}"> ลบ</span></label>`
      : `<label class="d-flex gap-2 align-items-center small mb-1"><input type="checkbox" class="form-check-input" name="remove_files[]" value="${it.path}"> ลบ <i class="bi bi-paperclip"></i> ${it.name}</label>`).join('') : '';
  });
  modal.dispatchEvent(new Event('website:fill'));
  bootstrap.Modal.getOrCreateInstance(modal).show();
}));
// ?edit=ID เปิดแก้ไขรายการนั้นเลย (ปุ่ม "แก้ไขข่าวนี้" จากหน้าเว็บ)
const editId = new URLSearchParams(location.search).get('edit');
if (editId) window.addEventListener('load', () => document.querySelector(`[data-edit-id="${editId}"]`)?.click());
@if ($errors->any() && old('_modal'))
window.addEventListener('load', () => bootstrap.Modal.getOrCreateInstance(document.getElementById(@json(old('_modal')))).show());
@endif
</script>
<style>
.existing-thumb { display: inline-flex; flex-direction: column; gap: .2rem; width: 92px; margin: 0 .4rem .4rem 0; font-size: .78rem; }
.existing-thumb img { width: 92px; height: 70px; object-fit: cover; border-radius: 10px; }
.web-thumb { width: 72px; height: 52px; object-fit: cover; border-radius: 10px; background: #edf0f5; flex-shrink: 0; display: grid; place-items: center; color: var(--sb-primary); }
</style>
@endpush
