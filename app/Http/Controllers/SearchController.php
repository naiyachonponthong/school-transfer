<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;

/** ค้นหาด่วน (Ctrl+K) คืนผลเป็น JSON */
class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q'));
        if (mb_strlen($q) < 1) {
            return response()->json([]);
        }

        $students = Student::with('classroom')->search($q)->active()->limit(8)->get()->map(fn (Student $s) => [
            'type' => 'นักเรียน',
            'title' => $s->fullName().($s->nickname ? " ({$s->nickname})" : ''),
            'sub' => $s->student_code.' · '.($s->classroom?->name() ?? 'ไม่มีห้อง'),
            'url' => route('students.show', $s),
        ]);

        $users = collect();
        if ($request->user()->isAdmin()) {
            $users = User::where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"))
                ->limit(5)->get()->map(fn (User $u) => [
                    'type' => $u->roleLabel(),
                    'title' => $u->name,
                    'sub' => $u->username.($u->phone ? ' · '.$u->phone : ''),
                    'url' => route('users.edit', $u),
                ]);
        }

        return response()->json($students->concat($users)->values());
    }
}
