<form method="POST" action="{{ route('settings.update') }}">
    @csrf
    <input type="hidden" name="section" value="facility">
    <div class="set-head"><h3>งานพัสดุและอาคารสถานที่</h3><p>ครูที่จัดการครุภัณฑ์ ตรวจสอบพัสดุ และรับเรื่องแจ้งซ่อมได้ (นอกจากผู้ดูแลระบบ)</p></div>
    @php($managers = \App\Models\User::facilityManagerIds())
    <label class="form-label" for="facilityManagers">ครูงานพัสดุ / อาคารสถานที่</label>
    <select name="facility_manager_ids[]" id="facilityManagers" class="form-select" multiple size="8">
        @foreach ($staff as $u)<option value="{{ $u->id }}" @selected(in_array($u->id, $managers, true))>{{ $u->name }}{{ $u->position ? ' · '.$u->position : '' }}</option>@endforeach
    </select>
    <div class="form-text">กด Ctrl ค้างเพื่อเลือกหลายคน คนที่เลือกจะได้รับแจ้งเตือน LINE เมื่อมีการแจ้งซ่อมใหม่</div>
    <div class="set-block">
        <div class="set-block-title"><i class="bi bi-123"></i>รูปแบบเลขอัตโนมัติ</div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('assets.numbering') }}" class="btn btn-light border"><i class="bi bi-upc"></i>เลขครุภัณฑ์</a>
            <a href="{{ route('supplies.numbering') }}" class="btn btn-light border"><i class="bi bi-upc"></i>รหัสวัสดุ</a>
        </div>
    </div>
    <div class="set-actions"><button class="btn btn-primary"><i class="bi bi-save"></i>บันทึก</button></div>
</form>
