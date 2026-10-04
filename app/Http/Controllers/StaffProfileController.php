<?php

namespace App\Http\Controllers;

use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\StaffTraining;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** ทะเบียนประวัติบุคลากร ใบอนุญาตประกอบวิชาชีพ และชั่วโมงอบรม */
class StaffProfileController extends Controller
{
    private function authorizeView(Request $request, User $user): void
    {
        abort_unless($user->isStaff(), 404);
        abort_unless($request->user()->id === $user->id || $request->user()->hasPermission('staff.manage'), 403);
    }

    /** ทะเบียนทั้งโรงเรียน (ฝ่ายบุคคล) */
    public function index(Request $request)
    {
        $year = (int) $request->query('year', now()->year);
        $staff = User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->with('departments')->orderBy('name')->get();
        $profiles = StaffProfile::whereIn('user_id', $staff->pluck('id'))->get()->keyBy('user_id');
        $hours = StaffTraining::whereIn('user_id', $staff->pluck('id'))->whereYear('date', $year)
            ->selectRaw('user_id, sum(hours) as h')->groupBy('user_id')->pluck('h', 'user_id');

        return view('staff.index', compact('staff', 'profiles', 'hours', 'year'));
    }

    public function show(Request $request, User $user)
    {
        $this->authorizeView($request, $user);

        return view('staff.show', [
            'user' => $user->load('departments'),
            'departments' => $this->departmentRows(),
            'profile' => StaffProfile::firstOrNew(['user_id' => $user->id]),
            'trainings' => StaffTraining::where('user_id', $user->id)->orderByDesc('date')->get(),
            'canManage' => $request->user()->hasPermission('staff.manage'),
        ]);
    }

    /** หน่วยงานเรียงตามผัง พร้อมระดับความลึก */
    private function departmentRows(?int $parent = null, int $depth = 0, ?\Illuminate\Support\Collection $all = null): \Illuminate\Support\Collection
    {
        $all ??= \App\Models\Department::orderBy('sort')->orderBy('id')->get();

        return $all->where('parent_id', $parent)->flatMap(fn ($d) => collect([['dept' => $d, 'depth' => $depth]])
            ->concat($this->departmentRows($d->id, $depth + 1, $all)))->values();
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeView($request, $user);
        $data = $request->validate([
            'citizen_id' => ['nullable', 'digits:13'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'rank' => ['nullable', 'string', 'max:60'],
            'hired_on' => ['nullable', 'date', 'before_or_equal:today'],
            'education' => ['nullable', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:255'],
            'license_no' => ['nullable', 'string', 'max:40'],
            'license_expires_on' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
        ], [], ['citizen_id' => 'เลขประจำตัวประชาชน']);

        $profile = StaffProfile::firstOrNew(['user_id' => $user->id]);
        // วันหมดอายุเปลี่ยน = เริ่มรอบเตือนใหม่
        if (($data['license_expires_on'] ?? null) !== $profile->license_expires_on?->toDateString()) {
            $data['license_reminded_on'] = null;
        }
        $profile->fill($data)->save();
        // เลขบัตรและที่อยู่ไม่ลงประวัติ เก็บแค่ว่าใครแก้ของใคร
        Audit::log('user.profile', $user, "แก้ประวัติบุคลากรของ {$user->name}");

        return back()->with('success', 'บันทึกประวัติแล้ว');
    }

    /** สังกัด รหัสบุคลากร และการแสดงเบอร์โทร: เจ้าตัวเลือกซ่อนเบอร์ได้ ที่เหลือเป็นงานของฝ่ายบุคคล */
    public function updateOrg(Request $request, User $user)
    {
        $this->authorizeView($request, $user);
        $user->forceFill(['hide_phone' => $request->boolean('hide_phone')])->save();

        if ($request->user()->hasPermission('staff.manage')) {
            $data = $request->validate([
                'staff_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('users', 'staff_code')->ignore($user->id),
                    Rule::notIn(Student::pluck('student_code')->all())],
                'departments' => ['nullable', 'array'],
                'departments.*' => ['integer', 'exists:departments,id'],
                'primary_department' => ['nullable', 'integer'],
            ], ['staff_code.not_in' => 'รหัสบุคลากรซ้ำกับรหัสนักเรียน', 'staff_code.regex' => 'รหัสบุคลากรใช้ได้เฉพาะตัวอักษรอังกฤษ ตัวเลข - และ _'], ['staff_code' => 'รหัสบุคลากร']);
            $user->forceFill(['staff_code' => $data['staff_code'] ?? null])->save();
            $user->departments()->sync($data['departments'] ?? []);
            OrgController::ensurePrimary($user->id, isset($data['primary_department']) ? (int) $data['primary_department'] : null);
            Audit::log('user.profile', $user, "แก้สังกัด/รหัสบุคลากรของ {$user->name}");
        }

        return back()->with('success', 'บันทึกสังกัดและการติดต่อแล้ว');
    }

    public function storeTraining(Request $request, User $user)
    {
        $this->authorizeView($request, $user);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'hours' => ['required', 'numeric', 'min:0', 'max:999'],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:6144'],
        ], [], ['title' => 'หัวข้อ', 'hours' => 'จำนวนชั่วโมง']);
        if ($request->hasFile('file')) {
            $data['file'] = $request->file('file')->store('trainings', 'local');
        }
        StaffTraining::create($data + ['user_id' => $user->id]);

        return back()->with('success', 'เพิ่มประวัติอบรมแล้ว');
    }

    public function destroyTraining(Request $request, StaffTraining $training)
    {
        $this->authorizeView($request, $training->user);
        $training->delete();

        return back()->with('success', 'ลบรายการแล้ว');
    }
}
