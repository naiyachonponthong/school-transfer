<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** PDPA: การรับทราบประกาศความเป็นส่วนตัว และการส่งออกข้อมูลส่วนบุคคลของนักเรียนตามคำขอ */
class PrivacyController extends Controller
{
    public static function currentVersion(): int
    {
        return (int) Settings::get('privacy_version', 1);
    }

    /** ต้องให้ผู้ใช้คนนี้รับทราบประกาศ (ฉบับปัจจุบัน) ก่อนหรือไม่ — ไม่ได้ตั้งประกาศไว้ = ไม่ต้อง */
    public static function required(\App\Models\User $user): bool
    {
        return filled(Settings::get('privacy_notice'))
            && ! DB::table('privacy_consents')->where('user_id', $user->id)->where('version', self::currentVersion())->exists();
    }

    public function show(Request $request)
    {
        return view('auth.privacy', ['notice' => (string) Settings::get('privacy_notice'), 'needsAccept' => self::required($request->user())]);
    }

    public function accept(Request $request)
    {
        $request->validate(['agree' => ['accepted']], ['agree.accepted' => 'กรุณาติ๊กรับทราบก่อน']);
        DB::table('privacy_consents')->insertOrIgnore([
            'user_id' => $request->user()->id, 'version' => self::currentVersion(), 'ip' => $request->ip(), 'accepted_at' => now(),
        ]);

        return redirect()->intended(route('home'));
    }

    /** ส่งออกข้อมูลทั้งหมดที่ระบบเก็บเกี่ยวกับนักเรียนหนึ่งคน (สิทธิขอเข้าถึงข้อมูลของเจ้าของข้อมูล) */
    public function export(Request $request, Student $student)
    {
        $student->load(['classroom', 'guardians', 'enrollments.classroom', 'attendances', 'behaviorRecords', 'leaveRequests',
            'healthVisits', 'measurements', 'invoices.items', 'invoices.payments', 'works', 'bookLoans.book']);
        Audit::log('student.data_export', $student, "ส่งออกข้อมูลส่วนบุคคลของ {$student->student_code} {$student->fullName()}");

        $data = [
            'exported_at' => now()->toIso8601String(),
            'school' => Settings::get('school_name'),
            'student' => $student->only(['student_code', 'citizen_id', 'prefix', 'first_name', 'last_name', 'nickname', 'gender', 'blood_type', 'medical_note', 'address', 'phone', 'status'])
                + ['birthdate' => $student->birthdate?->toDateString(), 'classroom' => $student->classroom?->name()],
            'guardians' => $student->guardians->map(fn ($g) => ['name' => $g->name, 'phone' => $g->phone, 'relation' => $g->pivot->relation])->all(),
            'enrollments' => $student->enrollments->map(fn ($e) => ['year' => $e->year, 'classroom' => $e->classroom?->name(), 'number' => $e->number, 'status' => $e->statusLabel()])->all(),
            'attendance' => $student->attendances->map(fn ($a) => ['date' => $a->date->toDateString(), 'status' => $a->status, 'note' => $a->note])->all(),
            'scores' => DB::table('scores')->join('assessments', 'assessments.id', '=', 'scores.assessment_id')->join('courses', 'courses.id', '=', 'assessments.course_id')
                ->join('subjects', 'subjects.id', '=', 'courses.subject_id')->join('terms', 'terms.id', '=', 'courses.term_id')->where('scores.student_id', $student->id)
                ->get(['terms.year', 'terms.term', 'subjects.code', 'subjects.name as subject', 'assessments.name as assessment', 'assessments.max_score', 'scores.score'])->all(),
            'results' => DB::table('course_results')->join('courses', 'courses.id', '=', 'course_results.course_id')->join('subjects', 'subjects.id', '=', 'courses.subject_id')
                ->join('terms', 'terms.id', '=', 'courses.term_id')->where('course_results.student_id', $student->id)
                ->get(['terms.year', 'terms.term', 'subjects.code', 'subjects.name as subject', 'course_results.special', 'course_results.remedial_grade', 'course_results.note'])->all(),
            'behavior' => $student->behaviorRecords->map(fn ($b) => ['date' => $b->date->toDateString(), 'title' => $b->title, 'points' => $b->points, 'note' => $b->note])->all(),
            'leaves' => $student->leaveRequests->map(fn ($l) => ['type' => $l->type, 'from' => $l->start_date->toDateString(), 'to' => $l->end_date->toDateString(), 'reason' => $l->reason, 'status' => $l->status])->all(),
            'health_visits' => $student->healthVisits->map(fn ($v) => ['at' => (string) $v->visited_at, 'symptom' => $v->symptom, 'treatment' => $v->treatment, 'medicine' => $v->medicine, 'action' => $v->action])->all(),
            'measurements' => $student->measurements->map(fn ($m) => ['on' => (string) $m->measured_on, 'weight' => $m->weight, 'height' => $m->height])->all(),
            'invoices' => $student->invoices->map(fn ($i) => ['no' => $i->invoice_no, 'title' => $i->title, 'total' => $i->total, 'discount' => $i->discount, 'paid' => $i->paid, 'status' => $i->status,
                'payments' => $i->payments->map(fn ($p) => ['receipt' => $p->receipt_no, 'amount' => $p->amount, 'paid_at' => (string) $p->paid_at, 'voided' => $p->isVoided()])->all()])->all(),
            'portfolio' => $student->works->map(fn ($w) => ['category' => $w->category, 'title' => $w->title, 'date' => (string) $w->date])->all(),
            'library' => $student->bookLoans->map(fn ($l) => ['book' => $l->book?->title, 'borrowed' => (string) $l->borrowed_on, 'returned' => (string) $l->returned_on])->all(),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="student-'.$student->student_code.'-'.now()->format('Ymd').'.json"',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
