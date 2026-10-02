<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $subjects = Subject::withCount('courses')
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('code', 'like', "%{$t}%")->orWhere('name', 'like', "%{$t}%")))
            ->orderBy('code')->get();

        return view('subjects.index', compact('subjects'));
    }

    public function store(Request $request)
    {
        Subject::create($this->validated($request));

        return back()->with('success', 'เพิ่มรายวิชาแล้ว');
    }

    public function update(Request $request, Subject $subject)
    {
        $subject->update($this->validated($request, $subject));

        return back()->with('success', 'บันทึกแล้ว');
    }

    public function destroy(Subject $subject)
    {
        abort_if($subject->courses()->exists(), 422, 'วิชานี้ถูกเปิดสอนอยู่ ลบไม่ได้');
        $subject->delete();

        return back()->with('success', 'ลบรายวิชาแล้ว');
    }

    private function validated(Request $request, ?Subject $subject = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('subjects')->ignore($subject?->id)],
            'name' => ['required', 'string', 'max:255'],
            'credit' => ['required', 'numeric', 'min:0', 'max:10'],
            'hours' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'type' => ['required', Rule::in(array_keys(Subject::TYPES))],
            'activity_kind' => ['nullable', 'required_if:type,activity', Rule::in(array_keys(Subject::ACTIVITY_KINDS))],
            'group' => ['nullable', 'string', 'max:100'],
        ], [], ['code' => 'รหัสวิชา', 'hours' => 'เวลาเรียน', 'activity_kind' => 'ประเภทกิจกรรม']);
        if ($data['type'] !== 'activity') {
            $data['activity_kind'] = null;
        }

        return $data;
    }
}
