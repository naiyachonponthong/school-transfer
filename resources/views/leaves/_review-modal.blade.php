<div class="modal fade" id="leaveReviewModal" tabindex="-1" aria-labelledby="leaveReviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content" id="leaveReviewForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="leaveReviewTitle">ยืนยันการพิจารณาใบลา</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <div class="modal-body">
                <div class="fw-semibold" id="leaveReviewName"></div>
                <div class="small text-muted mt-1" id="leaveReviewPeriod"></div>
                <p class="mt-3 mb-0" id="leaveReviewMessage"></p>
                <div class="mt-3 d-none" id="leaveReviewNoteWrap">
                    <label for="leaveReviewNote" class="form-label">เหตุผลที่ไม่อนุมัติ (ไม่บังคับ)</label>
                    <textarea class="form-control" id="leaveReviewNote" name="note" rows="3" maxlength="1000" placeholder="ระบุเหตุผลเพื่อแจ้งผู้ปกครอง"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn" id="leaveReviewSubmit"></button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('leaveReviewModal').addEventListener('show.bs.modal', function (event) {
    const trigger = event.relatedTarget;
    const approve = trigger.dataset.leaveDecision === 'approve';
    const form = document.getElementById('leaveReviewForm');
    const submit = document.getElementById('leaveReviewSubmit');
    const noteWrap = document.getElementById('leaveReviewNoteWrap');
    const note = document.getElementById('leaveReviewNote');

    form.action = trigger.dataset.leaveAction;
    document.getElementById('leaveReviewTitle').textContent = approve ? 'ยืนยันการอนุมัติใบลา' : 'ยืนยันการไม่อนุมัติใบลา';
    document.getElementById('leaveReviewName').textContent = trigger.dataset.leaveName;
    document.getElementById('leaveReviewPeriod').textContent = trigger.dataset.leavePeriod;
    document.getElementById('leaveReviewMessage').textContent = approve
        ? 'ระบบจะบันทึกสถานะลาในวันเรียนที่ระบุ และแจ้งผลให้ผู้ปกครองทราบ ยืนยันการอนุมัติหรือไม่?'
        : 'ระบบจะแจ้งผลให้ผู้ปกครองทราบ ยืนยันการไม่อนุมัติหรือไม่?';
    noteWrap.classList.toggle('d-none', approve);
    note.disabled = approve;
    note.value = '';
    submit.className = approve ? 'btn btn-success' : 'btn btn-danger';
    submit.textContent = approve ? 'ยืนยันอนุมัติ' : 'ยืนยันไม่อนุมัติ';
});
</script>
@endpush
