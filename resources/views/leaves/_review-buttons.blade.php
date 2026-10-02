<div class="d-flex flex-wrap gap-2">
    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#leaveReviewModal"
        data-leave-decision="reject" data-leave-action="{{ route('leaves.reject', $leave) }}"
        data-leave-name="{{ $leave->student->fullName() }}" data-leave-period="{{ thai_date($leave->start_date) }}{{ $leave->days() > 1 ? ' – '.thai_date($leave->end_date) : '' }}">
        <i class="bi bi-x-lg"></i> ไม่อนุมัติ
    </button>
    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#leaveReviewModal"
        data-leave-decision="approve" data-leave-action="{{ route('leaves.approve', $leave) }}"
        data-leave-name="{{ $leave->student->fullName() }}" data-leave-period="{{ thai_date($leave->start_date) }}{{ $leave->days() > 1 ? ' – '.thai_date($leave->end_date) : '' }}">
        <i class="bi bi-check-lg"></i> อนุมัติ
    </button>
</div>
