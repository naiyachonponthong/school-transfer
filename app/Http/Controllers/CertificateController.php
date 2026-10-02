<?php

namespace App\Http\Controllers;

use App\Models\DocumentIssue;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** ปพ.7 ใบรับรองผลการศึกษา + ทะเบียนคุมเลขที่ (ผู้ดูแลระบบ/งานทะเบียน) */
class CertificateController extends Controller
{
    public const PURPOSES = ['ศึกษาต่อ', 'ย้ายสถานศึกษา', 'ขอทุนการศึกษา', 'สมัครงาน', 'เป็นหลักฐานทางราชการ'];

    public function index(Request $request)
    {
        $issues = DocumentIssue::with('issuer')
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('student_name', 'like', "%{$t}%")->orWhere('purpose', 'like', "%{$t}%")))
            ->latest('id')->paginate(50)->withQueryString();

        return view('certificates.index', compact('issues'));
    }

    public function create(Student $student)
    {
        return view('certificates.create', ['student' => $student->load('classroom'), 'preview' => self::snapshot($student)]);
    }

    public function store(Request $request, Student $student)
    {
        $data = $request->validate(['purpose' => ['required', 'string', 'max:255']], [], ['purpose' => 'ออกให้เพื่อ']);

        $issue = DB::transaction(function () use ($data, $student, $request) {
            $year = today()->year + 543;
            // ล็อกแถวของปีนี้ไว้ระหว่างหาเลขถัดไป กันออกเลขซ้ำเมื่อกดพร้อมกัน
            $number = (int) DocumentIssue::where(['type' => 'pp7', 'year' => $year])->lockForUpdate()->max('number') + 1;

            return DocumentIssue::create([
                'type' => 'pp7', 'year' => $year, 'number' => $number,
                'student_id' => $student->id, 'student_name' => $student->fullName(),
                'purpose' => $data['purpose'], 'issued_on' => today(),
                'snapshot' => self::snapshot($student), 'issued_by' => $request->user()->id,
            ]);
        });

        return redirect()->route('certificates.show', $issue)->with('success', "ออกใบรับรองเลขที่ {$issue->code()} แล้ว");
    }

    public function show(DocumentIssue $issue)
    {
        return view('certificates.show', ['issue' => $issue, 's' => $issue->snapshot]);
    }

    /** ข้อมูลที่พิมพ์ลงใบรับรอง ณ วันที่ออก */
    public static function snapshot(Student $student): array
    {
        $student->loadMissing('classroom');
        $record = ReportController::academicRecord($student);
        $last = $record['terms']->last();

        return [
            'name' => $student->fullName(),
            'student_code' => $student->student_code,
            'citizen_id' => $student->citizen_id,
            'birthdate' => $student->birthdate?->toDateString(),
            'father_name' => $student->father_name,
            'mother_name' => $student->mother_name,
            'status' => $student->status,
            'classroom' => $student->classroom?->name(),
            'year' => $student->classroom?->year,
            'admitted_on' => $student->admitted_on?->toDateString(),
            'left_on' => $student->left_on?->toDateString(),
            'leave_reason' => $student->leave_reason,
            'gpax' => $record['gpax'],
            'credits' => $record['credits'],
            'last_term' => $last ? $last['term']->label() : null,
            'photo' => $student->photo,
        ];
    }
}
