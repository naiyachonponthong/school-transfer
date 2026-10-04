<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * โครงสร้างองค์กร (หน่วยงานและการสังกัดของบุคลากร) และทะเบียนติดต่อของบุคลากร
 * ทุกคนที่เป็นบุคลากรดูได้ · ผู้มีสิทธิ์ staff.manage แก้โครงสร้างและจัดคนเข้าหน่วยได้
 */
class OrgController extends Controller
{
    private function staff(): Collection
    {
        return User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->with('departments')->orderBy('name')->get();
    }

    /* ---------------- ทะเบียนติดต่อ ---------------- */

    public function directory(Request $request)
    {
        $departments = Department::orderBy('sort')->orderBy('id')->get();
        $q = trim((string) $request->query('q'));
        $dept = $departments->firstWhere('id', (int) $request->query('department'));
        $within = $dept ? Department::descendantIds($dept->id, $departments) : null;

        $staff = $this->staff()
            ->when($q !== '', fn (Collection $c) => $c->filter(fn (User $u) => collect([$u->name, $u->position, $u->staff_code, $u->hide_phone ? null : $u->phone])
                ->contains(fn ($v) => $v !== null && mb_stripos((string) $v, $q) !== false)))
            ->when($within, fn (Collection $c) => $c->filter(fn (User $u) => $u->departments->pluck('id')->intersect($within)->isNotEmpty()))
            ->values();

        return view('staff.directory', ['staff' => $staff, 'departments' => self::flatten($departments), 'q' => $q, 'dept' => $dept]);
    }

    /* ---------------- โครงสร้างองค์กร ---------------- */

    public function index(Request $request)
    {
        $departments = Department::with(['head', 'members'])->orderBy('sort')->orderBy('id')->get();
        $staff = $this->staff();
        // จำนวนคน (ไม่นับซ้ำ) ของแต่ละหน่วยรวมหน่วยย่อย
        $counts = $departments->mapWithKeys(fn (Department $d) => [$d->id => $departments
            ->whereIn('id', Department::descendantIds($d->id, $departments))
            ->flatMap(fn (Department $x) => $x->members->pluck('id')->push($x->head_id))->filter()->unique()->count()]);

        return view('org.index', [
            'departments' => $departments,
            'flat' => self::flatten($departments),
            'roots' => $departments->whereNull('parent_id')->values(),
            'counts' => $counts,
            'staff' => $staff,
            'unassigned' => $staff->filter(fn (User $u) => $u->departments->isEmpty() && ! $departments->contains('head_id', $u->id))->values(),
            'canManage' => $request->user()->hasPermission('staff.manage'),
            'table' => $request->query('view') === 'table',
        ]);
    }

    /**
     * เรียงหน่วยงานตามลำดับในผัง พร้อมระดับความลึก (ใช้ในรายการเลือกและมุมมองตาราง)
     *
     * @return Collection<int, array{dept: Department, depth: int}>
     */
    private static function flatten(Collection $all, ?int $parent = null, int $depth = 0): Collection
    {
        return $all->where('parent_id', $parent)->flatMap(fn (Department $d) => collect([['dept' => $d, 'depth' => $depth]])
            ->concat(self::flatten($all, $d->id, $depth + 1)))->values();
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'kind' => ['required', Rule::in(array_keys(Department::KINDS))],
            'parent_id' => ['nullable', 'integer', 'exists:departments,id'],
            'head_id' => ['nullable', 'integer', Rule::exists('users', 'id')->whereIn('role', ['admin', 'teacher'])],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [], ['name' => 'ชื่อหน่วยงาน', 'code' => 'รหัสย่อ', 'kind' => 'ประเภท', 'parent_id' => 'หน่วยงานแม่', 'head_id' => 'หัวหน้า']);
        $data['sort'] = (int) ($data['sort'] ?? 0);

        // ย้ายไปอยู่ใต้ตัวเองหรือหน่วยย่อยของตัวเองไม่ได้ (ผังจะวนเป็นวง)
        if ($department && ! empty($data['parent_id'])
            && in_array((int) $data['parent_id'], Department::descendantIds($department->id, Department::all()), true)) {
            abort(back()->withErrors(['parent_id' => 'เลือกหน่วยงานแม่เป็นตัวเองหรือหน่วยย่อยของตัวเองไม่ได้'])->withInput());
        }

        return $data;
    }

    public function store(Request $request)
    {
        $department = Department::create($this->validated($request));
        Audit::log('user.org', $department, "เพิ่มหน่วยงาน {$department->name}");

        return back()->with('success', "เพิ่มหน่วยงาน {$department->name} แล้ว");
    }

    public function update(Request $request, Department $department)
    {
        $department->update($this->validated($request, $department));
        if ($request->has('members_sent')) {
            $this->syncMembers($department, collect($request->input('members', []))->map(fn ($v) => (int) $v)->all());
        }
        Audit::log('user.org', $department, "แก้ไขหน่วยงาน {$department->name}");

        return back()->with('success', "บันทึกหน่วยงาน {$department->name} แล้ว");
    }

    /** สมาชิกของหน่วย: คนที่ยังไม่มีหน่วยหลัก ให้หน่วยนี้เป็นหน่วยหลัก */
    private function syncMembers(Department $department, array $ids): void
    {
        $ids = User::whereIn('role', ['admin', 'teacher'])->whereIn('id', $ids)->pluck('id')->all();
        $department->members()->sync($ids);
        foreach ($ids as $id) {
            self::ensurePrimary($id);
        }
    }

    /** ทุกคนที่มีสังกัดต้องมีหน่วยหลักหนึ่งหน่วยเสมอ */
    public static function ensurePrimary(int $userId, ?int $prefer = null): void
    {
        $rows = \DB::table('department_user')->where('user_id', $userId)->orderBy('id')->get();
        if ($rows->isEmpty()) {
            return;
        }
        $primary = $prefer && $rows->contains('department_id', $prefer)
            ? $prefer
            : ($rows->firstWhere('is_primary', true)->department_id ?? $rows->first()->department_id);
        \DB::table('department_user')->where('user_id', $userId)->update(['is_primary' => false]);
        \DB::table('department_user')->where('user_id', $userId)->where('department_id', $primary)->update(['is_primary' => true]);
    }

    /** ลบหน่วยงาน: หน่วยย่อยเลื่อนขึ้นไปอยู่ใต้หน่วยแม่ สมาชิกหลุดจากหน่วยนี้ */
    public function destroy(Department $department)
    {
        $members = $department->members()->pluck('users.id');
        Department::where('parent_id', $department->id)->update(['parent_id' => $department->parent_id]);
        Audit::log('user.org', $department, "ลบหน่วยงาน {$department->name}");
        $department->delete();
        $members->each(fn ($id) => self::ensurePrimary($id));

        return back()->with('success', 'ลบหน่วยงานแล้ว');
    }

    public function preset()
    {
        if (Department::exists()) {
            return back()->with('warning', 'มีหน่วยงานอยู่แล้ว จึงไม่สร้างโครงสร้างมาตรฐานทับ');
        }
        Department::createPreset();
        Audit::log('user.org', null, 'สร้างโครงสร้างองค์กรมาตรฐาน');

        return back()->with('success', 'สร้างโครงสร้างมาตรฐานแล้ว แก้ชื่อ เพิ่ม หรือลบหน่วยงานได้ตามจริง');
    }

    /** ออกรหัสบุคลากรให้คนที่ยังไม่มี (ตัวเลขเรียงต่อกัน ไม่ซ้ำกับรหัสนักเรียน) */
    public function generateCodes()
    {
        $taken = User::whereNotNull('staff_code')->pluck('staff_code')->merge(Student::pluck('student_code'))->flip();
        $next = 90001;
        $count = 0;
        foreach (User::whereIn('role', ['admin', 'teacher'])->whereNull('staff_code')->orderBy('id')->get() as $user) {
            while ($taken->has((string) $next)) {
                $next++;
            }
            $user->forceFill(['staff_code' => (string) $next])->save();
            $taken->put((string) $next, true);
            $count++;
        }
        Audit::log('user.org', null, "ออกรหัสบุคลากรอัตโนมัติ {$count} คน");

        return back()->with('success', $count ? "ออกรหัสบุคลากรให้ {$count} คนแล้ว" : 'ทุกคนมีรหัสบุคลากรแล้ว');
    }
}
