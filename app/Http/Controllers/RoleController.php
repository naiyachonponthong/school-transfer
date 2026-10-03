<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Support\Audit;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** ตำแหน่งงานและสิทธิ์: ตารางติ๊ก ตำแหน่ง × สิทธิ์ */
class RoleController extends Controller
{
    public function index()
    {
        return view('roles.index', [
            'roles' => Role::withCount('users')->orderByDesc('is_system')->orderBy('id')->get(),
            'groups' => Permissions::GROUPS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('roles')]], [], ['name' => 'ชื่อตำแหน่ง']);
        $role = Role::create(['key' => 'r'.Str::lower(Str::random(8)), 'name' => $data['name'], 'permissions' => []]);
        Audit::log('user.role', $role, "เพิ่มตำแหน่งงาน {$role->name}");

        return back()->with('success', "เพิ่มตำแหน่ง {$data['name']} แล้ว ติ๊กสิทธิ์แล้วกดบันทึก");
    }

    /** บันทึกสิทธิ์ของทุกตำแหน่งในครั้งเดียว */
    public function update(Request $request)
    {
        $data = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => [Rule::in(Permissions::keys())],
        ]);

        foreach (Role::all() as $role) {
            $new = array_values($data['permissions'][$role->id] ?? []);
            $old = $role->permissions ?? [];
            if (array_diff($new, $old) || array_diff($old, $new)) {
                Audit::log('user.role', $role, "แก้สิทธิ์ของตำแหน่ง {$role->name}", ['permissions' => [$old, $new]]);
                $role->update(['permissions' => $new]);
            }
        }

        return back()->with('success', 'บันทึกสิทธิ์แล้ว มีผลทันทีกับผู้ใช้ทุกคนในตำแหน่งนั้น');
    }

    public function destroy(Role $role)
    {
        abort_if($role->is_system, 422, 'ตำแหน่งตั้งต้นของระบบลบไม่ได้');
        abort_if($role->users()->exists(), 422, 'ยังมีผู้ใช้อยู่ในตำแหน่งนี้ ย้ายออกก่อน');
        Audit::log('user.role', $role, "ลบตำแหน่งงาน {$role->name}");
        $role->delete();

        return back()->with('success', 'ลบตำแหน่งแล้ว');
    }
}
