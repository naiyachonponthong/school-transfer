<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->query('role', 'teacher');
        $users = User::query()
            ->when($role !== 'all', fn ($q) => $q->where('role', $role))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('username', 'like', "%{$t}%")->orWhere('phone', 'like', "%{$t}%")))
            ->withCount(['children', 'homerooms', 'courses'])
            ->orderBy('name')->paginate(40)->withQueryString();

        $counts = User::selectRaw('role, count(*) as c')->groupBy('role')->pluck('c', 'role');

        return view('users.index', compact('users', 'role', 'counts'));
    }

    public function create(Request $request)
    {
        return view('users.form', ['user' => new User(['role' => $request->query('role', 'teacher'), 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $password = $data['password'] ?: Str::lower(Str::random(8));
        $data['password'] = Hash::make($password);
        $user = User::create($data);

        return redirect()->route('users.index', ['role' => $user->role])
            ->with('success', "สร้างบัญชี {$user->name} แล้ว")
            ->with('credential', ['username' => $user->username, 'password' => $password]);
    }

    public function edit(User $user)
    {
        $user->load('children.classroom');

        return view('users.form', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        if ($data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        if ($user->id === $request->user()->id) {
            // กันผู้ดูแลล็อกตัวเองออกจากระบบ
            $data['role'] = $user->role;
            $data['is_active'] = true;
        }
        $user->update($data);

        return redirect()->route('users.index', ['role' => $user->role])->with('success', 'บันทึกแล้ว');
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'ลบบัญชีตัวเองไม่ได้');
        $user->delete();

        return back()->with('success', 'ลบบัญชีแล้ว');
    }

    public function resetPassword(User $user)
    {
        $password = $user->isParent() && $user->phone ? substr($user->phone, -6) : Str::lower(Str::random(8));
        $user->update(['password' => Hash::make($password)]);

        return back()->with('credential', ['username' => $user->username, 'password' => $password])
            ->with('success', "รีเซ็ตรหัสผ่านของ {$user->name} แล้ว");
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'alpha_dash', Rule::unique('users')->ignore($user?->id)],
            'email' => ['nullable', 'email', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'position' => ['nullable', 'string', 'max:100'],
            'password' => ['nullable', 'string', 'min:6'],
        ], [], ['username' => 'ชื่อผู้ใช้']);
        $data['is_active'] = $request->boolean('is_active');
        $data['phone'] = isset($data['phone']) ? preg_replace('/\D/', '', $data['phone']) : null;

        return $data;
    }
}
