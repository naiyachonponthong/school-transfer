<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Support\CumulativeRecord;
use Illuminate\Http\Request;

/** ระเบียนสะสม (ปพ.8): รายคน หรือพิมพ์ทั้งห้อง · มีข้อมูลสุขภาพและการเยี่ยมบ้าน จึงเปิดได้เฉพาะครูและบุคลากร */
class CumulativeRecordController extends Controller
{
    public function show(Request $request, Student $student)
    {
        abort_unless($request->user()->isStaff(), 403);

        return view('reports.pp8', ['records' => [CumulativeRecord::for($student)], 'back' => route('students.show', $student)]);
    }

    public function classroom(Request $request, Classroom $classroom)
    {
        $user = $request->user();
        abort_unless($classroom->isManagedBy($user) || $user->hasPermission('academics.manage'), 403);
        $students = $classroom->students()->where('status', 'active')->orderBy('number')->get();

        return view('reports.pp8', [
            'records' => $students->map(fn ($s) => CumulativeRecord::for($s))->all(),
            'classroom' => $classroom,
            'back' => url()->previous(),
        ]);
    }
}
