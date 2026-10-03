<?php

namespace App\Http\Controllers;

use App\Models\StaffLeave;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** ระบบลางานของครูและบุคลากร: ยื่นใบลา → ผู้บริหารอนุมัติ → ลงเวลาปฏิบัติงานให้อัตโนมัติ */
class StaffLeaveController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        [$fyStart, $fyEnd] = StaffLeave::fiscalRange();

        // วันลาที่ใช้ไปในปีงบประมาณนี้
        $used = StaffLeave::where('user_id', $user->id)->where('status', 'approved')
            ->whereBetween('start_date', [$fyStart, $fyEnd])->get()
            ->groupBy('type')->map(fn ($l) => $l->sum(fn ($x) => $x->days()));

        $pending = collect();
        if ($user->hasPermission('staff.manage')) {
            $pending = StaffLeave::with('user')->where('status', 'pending')->oldest()->get();
        }

        return view('staff-leaves.index', [
            'mine' => StaffLeave::with('reviewer')->where('user_id', $user->id)->latest()->limit(30)->get(),
            'used' => $used,
            'pending' => $pending,
            'history' => $user->hasPermission('staff.manage') ? StaffLeave::with(['user', 'reviewer'])->where('status', '!=', 'pending')->latest('reviewed_at')->limit(20)->get() : collect(),
            'fy' => [$fyStart, $fyEnd],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(StaffLeave::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:6144'],
        ]);
        if ($request->hasFile('attachment')) {
            $data['attachment'] = $request->file('attachment')->store('staff-leaves', 'local');
        }
        $leave = StaffLeave::create($data + ['user_id' => $request->user()->id]);

        Notifier::users(User::where('role', 'admin')->whereNotNull('line_user_id')->get(),
            "📝 {$request->user()->name} ยื่น{$leave->typeLabel()} {$leave->days()} วัน (".thai_date($leave->start_date).')', route('staff-leaves.index'));

        return back()->with('success', 'ยื่นใบลาแล้ว รอผู้บริหารอนุมัติ');
    }

    public function approve(Request $request, StaffLeave $leave)
    {
        $leave->approve($request->user(), $request->input('note'));
        Notifier::users([$leave->user], "✅ {$leave->typeLabel()} วันที่ ".thai_date($leave->start_date).' ได้รับอนุมัติแล้ว');

        return back()->with('success', "อนุมัติการลาของ {$leave->user->name} แล้ว");
    }

    public function reject(Request $request, StaffLeave $leave)
    {
        $leave->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $request->input('note')]);
        Notifier::users([$leave->user], "❌ {$leave->typeLabel()} วันที่ ".thai_date($leave->start_date).' ไม่ได้รับอนุมัติ'.($leave->review_note ? ': '.$leave->review_note : ''));

        return back()->with('success', 'ไม่อนุมัติแล้ว');
    }

    public function destroy(Request $request, StaffLeave $leave)
    {
        abort_unless($leave->user_id === $request->user()->id && $leave->status === 'pending', 403);
        $leave->delete();

        return back()->with('success', 'ยกเลิกใบลาแล้ว');
    }
}
