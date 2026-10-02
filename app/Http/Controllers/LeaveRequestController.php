<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\Student;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status', 'pending');

        $leaves = LeaveRequest::with(['student.classroom', 'requester', 'reviewer'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when(! $user->isAdmin() && ! $request->boolean('all'), function ($q) use ($user) {
                $q->whereHas('student', fn ($s) => $s->whereIn('classroom_id', $user->myClassrooms()->pluck('id')));
            })
            ->latest()->paginate(30)->withQueryString();

        $pendingCount = LeaveRequest::where('status', 'pending')->count();

        return view('leaves.index', compact('leaves', 'status', 'pendingCount'));
    }

    public function show(LeaveRequest $leave)
    {
        $leave->load(['student.classroom', 'requester', 'reviewer']);

        return view('leaves.show', compact('leave'));
    }

    /** ครูบันทึกใบลาแทนผู้ปกครอง (เช่น โทรมาแจ้ง) — อนุมัติทันที */
    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'type' => ['required', Rule::in(array_keys(LeaveRequest::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $leave = LeaveRequest::create($data + ['requested_by' => $request->user()->id]);
        $leave->approve($request->user(), 'บันทึกโดยครู');

        return back()->with('success', 'บันทึกการลาของ '.Student::find($data['student_id'])->fullName().' แล้ว');
    }

    public function approve(Request $request, LeaveRequest $leave)
    {
        if ($leave->status !== 'pending') {
            return back()->with('warning', 'ใบลานี้ได้รับการพิจารณาแล้ว');
        }

        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $leave->approve($request->user(), $data['note'] ?? null);
        if ($leave->requested_by && $leave->requester?->isParent()) {
            Notifier::users([$leave->requester], "✅ ใบ{$leave->typeLabel()}ของน้อง".($leave->student->nickname ?: $leave->student->first_name).' วันที่ '.thai_date($leave->start_date).' ได้รับอนุมัติแล้ว');
        }

        return back()->with('success', "อนุมัติใบลาของ {$leave->student->fullName()} แล้ว และลงเช็คชื่อให้อัตโนมัติ");
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        if ($leave->status !== 'pending') {
            return back()->with('warning', 'ใบลานี้ได้รับการพิจารณาแล้ว');
        }

        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $leave->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_note' => $data['note'] ?? null,
        ]);

        if ($leave->requester?->isParent()) {
            Notifier::users([$leave->requester], "❌ ใบ{$leave->typeLabel()}ของน้อง".($leave->student->nickname ?: $leave->student->first_name).' ไม่ได้รับอนุมัติ'.($leave->review_note ? ': '.$leave->review_note : ''));
        }

        return back()->with('success', 'ไม่อนุมัติใบลาแล้ว');
    }
}
