<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\Classroom;
use App\Models\Student;
use App\Models\User;
use App\Support\AdmissionForm;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** รับสมัครนักเรียน (ฝั่งเจ้าหน้าที่): พิจารณา → ตรวจค่าสมัคร → มอบตัว (สร้างนักเรียน+บัญชีผู้ปกครองให้) · พิมพ์เอกสาร */
class AdmissionController extends Controller
{
    public const DOCS = ['application' => 'ใบสมัคร', 'receipt' => 'ใบเสร็จค่าสมัคร', 'enrollment' => 'ใบมอบตัว'];

    public function index(Request $request)
    {
        $status = $request->query('status', 'all');
        $year = (int) $request->query('year', ApplyController::year());
        $base = Admission::where('year', $year);
        $submitted = (clone $base)->submitted();

        return view('admissions.index', [
            // "ทั้งหมด" ไม่รวมร่างที่กรอกค้าง (ดูแยกได้ที่ตัวกรอง)
            'items' => ($status === 'draft' ? (clone $base)->where('status', 'draft') : (clone $submitted)->when($status !== 'all', fn ($q) => $q->where('status', $status)))
                ->when($request->query('level'), fn ($q, $l) => $q->where('level', $l))
                ->when($request->query('fee'), fn ($q, $f) => $q->where('fee_status', $f))
                ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('first_name', 'like', "%{$t}%")->orWhere('last_name', 'like', "%{$t}%")->orWhere('app_no', 'like', "%{$t}%")->orWhere('citizen_id', 'like', "%{$t}%")))
                ->latest()->paginate(40)->withQueryString(),
            'counts' => (clone $base)->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'byLevel' => (clone $submitted)->selectRaw('level, count(*) c')->groupBy('level')->pluck('c', 'level'),
            'slipsPending' => (clone $submitted)->where('fee_status', 'pending')->count(),
            'status' => $status,
            'year' => $year,
            'levels' => AdmissionForm::levels(),
            'open' => AdmissionForm::isOpen(),
            'caps' => AdmissionForm::config()['caps'],
        ]);
    }

    /** ส่งออกใบสมัครทั้งปี (ทุกคำถามที่เคยมีคำตอบ) — Excel เปิดภาษาไทยได้ */
    public function export(Request $request)
    {
        $year = (int) $request->query('year', ApplyController::year());
        $items = Admission::where('year', $year)->submitted()->orderBy('app_no')->get();
        $cols = [];
        foreach ($items as $a) {
            foreach ($a->answers ?? [] as $ans) {
                $cols[$ans['id']] = $ans['label'];
            }
        }

        return response()->streamDownload(function () use ($items, $cols) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_merge(['เลขที่ใบสมัคร', 'สถานะ', 'ระดับชั้น', 'คำนำหน้า', 'ชื่อ', 'นามสกุล', 'ชื่อเล่น', 'เพศ', 'วันเกิด', 'เลขบัตรประชาชน',
                'โรงเรียนเดิม', 'เกรดเฉลี่ย', 'ผู้ปกครอง', 'ความสัมพันธ์', 'เบอร์โทร', 'ที่อยู่', 'หมายเหตุ', 'ค่าสมัคร', 'ห้องสอบ', 'เลขที่นั่งสอบ', 'วันที่ส่ง'], array_values($cols)));
            foreach ($items as $a) {
                $ans = collect($a->answers ?? [])->keyBy('id');
                fputcsv($out, array_merge([
                    // เลขบัตร/เบอร์โทรนำหน้าด้วยแท็บ กัน Excel ตัดเลข 0 หรือแปลงเป็นเลขยกกำลัง
                    $a->app_no, $a->statusLabel(), $a->level, $a->prefix, $a->first_name, $a->last_name, $a->nickname,
                    ['M' => 'ชาย', 'F' => 'หญิง'][$a->gender] ?? '', $a->birthdate?->toDateString(), "\t".$a->citizen_id,
                    $a->previous_school, $a->gpa, $a->parent_name, $a->relation, "\t".$a->parent_phone, $a->address, $a->note,
                    $a->feeLabel(), $a->exam_room, $a->exam_seat, $a->submitted_at?->toDateTimeString(),
                ], array_map(fn ($id) => $ans->has($id) ? AdmissionForm::display($ans[$id]) : '', array_keys($cols))));
            }
            fclose($out);
        }, "applications_{$year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Admission $admission)
    {
        return view('admissions.show', [
            'a' => $admission->load('feeVerifier'),
            'classrooms' => Classroom::where('year', $admission->year)->where('level', $admission->level)->ordered()->get(),
            'nextCode' => (string) (((int) Student::max('student_code')) + 1),
        ]);
    }

    public function document(Admission $admission)
    {
        abort_unless($admission->document, 404);

        return response()->file(storage_path('app/private/'.$admission->document));
    }

    public function answerFile(Admission $admission, string $question)
    {
        return self::answerFileResponse($admission, $question);
    }

    /** ไฟล์แนบของคำถามที่โรงเรียนเพิ่มเอง (ดิสก์ส่วนตัว) · ใช้ทั้งฝั่งเจ้าหน้าที่และผู้สมัคร */
    public static function answerFileResponse(Admission $a, string $question)
    {
        if ($question === 'slip') {
            $path = $a->fee_slip;
        } else {
            $ans = collect($a->answers ?? [])->first(fn ($x) => $x['id'] === $question && $x['type'] === 'file');
            $path = $ans['value']['path'] ?? null;
        }
        abort_unless($path && str_starts_with($path, 'admissions/') && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=3600']);
    }

    public function update(Request $request, Admission $admission)
    {
        abort_if(in_array($admission->status, ['enrolled', 'draft'], true), 422, 'แก้สถานะใบสมัครนี้ไม่ได้');
        $admission->update($request->validate([
            'status' => ['required', Rule::in(['submitted', 'reviewing', 'accepted', 'rejected'])],
            'staff_note' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('success', 'อัปเดตสถานะเป็น "'.$admission->statusLabel().'" แล้ว');
    }

    /** ห้องสอบ/เลขที่นั่งสอบ (พิมพ์ในส่วนที่ 2 ของใบสมัคร และแสดงในหน้าตรวจสถานะ) */
    public function exam(Request $request, Admission $admission)
    {
        $admission->update($request->validate(['exam_room' => ['nullable', 'string', 'max:60'], 'exam_seat' => ['nullable', 'string', 'max:20']]));

        return back()->with('success', 'บันทึกห้องสอบแล้ว');
    }

    /** ค่าสมัคร: ยืนยันสลิป/รับเงินสด → ออกเลขใบเสร็จ · ตีกลับสลิป */
    public function fee(Request $request, Admission $admission)
    {
        abort_unless($admission->fee_amount > 0 && $admission->fee_status !== 'none', 422, 'ใบสมัครนี้ไม่มีค่าสมัคร');
        $data = $request->validate(['action' => ['required', 'in:approve,reject'], 'fee_note' => ['nullable', 'string', 'max:255']]);
        if ($data['action'] === 'reject') {
            abort_if($admission->fee_status === 'paid', 422, 'ออกใบเสร็จแล้ว');
            $admission->update(['fee_status' => 'unpaid', 'fee_note' => $data['fee_note'] ?: 'สลิปไม่ถูกต้อง กรุณาแนบใหม่']);

            return back()->with('success', 'ตีกลับสลิปแล้ว ผู้สมัครจะเห็นข้อความในหน้าตรวจสถานะ');
        }
        if ($admission->fee_status !== 'paid') {
            DB::transaction(fn () => $admission->update([
                'fee_status' => 'paid', 'fee_paid_at' => now(), 'fee_verified_by' => $request->user()->id, 'fee_note' => $data['fee_note'] ?? null,
                'fee_receipt_no' => Admission::nextReceiptNumber($admission->year),
            ]));
        }

        return back()->with('success', 'ยืนยันการชำระค่าสมัครแล้ว ใบเสร็จเลขที่ '.$admission->fee_receipt_no);
    }

    public function print(Admission $admission, string $doc)
    {
        return self::documentResponse($admission, $doc, fn ($q) => route('admissions.file', [$admission, $q]));
    }

    /** เอกสารพิมพ์ได้ (A4) · $fileUrl = ลิงก์ไฟล์แนบตามผู้ที่เปิด (เจ้าหน้าที่/ผู้สมัคร) */
    public static function documentResponse(Admission $a, string $doc, Closure $fileUrl)
    {
        abort_unless(isset(self::DOCS[$doc]), 404);
        $ready = match ($doc) {
            'application' => ! $a->isDraft(),
            'receipt' => $a->fee_status === 'paid',
            'enrollment' => in_array($a->status, ['accepted', 'enrolled'], true),
        };
        abort_unless($ready, 404, self::DOCS[$doc].'ยังไม่พร้อมพิมพ์');
        $a->loadMissing('student.classroom', 'feeVerifier');
        $photo = $a->photoAnswer();

        return view('admissions.docs.'.$doc, [
            'a' => $a, 'config' => AdmissionForm::config(), 'title' => self::DOCS[$doc],
            'photoUrl' => $photo ? $fileUrl($photo['id']) : null,
        ]);
    }

    /** มอบตัว: สร้างนักเรียน + บัญชีผู้ปกครอง */
    public function enroll(Request $request, Admission $admission)
    {
        abort_unless($admission->status === 'accepted', 422, 'ต้องผ่านการคัดเลือกก่อนมอบตัว');
        $data = $request->validate([
            'student_code' => ['required', 'string', 'max:20', 'unique:students,student_code'],
            'classroom_id' => ['nullable', 'exists:classrooms,id'],
        ]);

        $student = DB::transaction(function () use ($admission, $data) {
            $student = Student::create([
                'student_code' => $data['student_code'],
                'classroom_id' => $data['classroom_id'] ?? null,
                'number' => ! empty($data['classroom_id']) ? ((int) Student::where('classroom_id', $data['classroom_id'])->max('number')) + 1 : null,
                'status' => 'active',
                'address' => $admission->address,
                'admitted_on' => today(),
                'father_name' => $admission->relation === 'บิดา' ? $admission->parent_name : null,
                'mother_name' => $admission->relation === 'มารดา' ? $admission->parent_name : null,
            ] + $admission->only(['prefix', 'first_name', 'last_name', 'nickname', 'gender', 'birthdate', 'citizen_id', 'previous_school']));

            $parent = User::where('role', 'parent')->where('phone', $admission->parent_phone)->first()
                ?? User::create([
                    'name' => $admission->parent_name,
                    'username' => User::where('username', $admission->parent_phone)->exists() ? 'p'.$admission->app_no : $admission->parent_phone,
                    'phone' => $admission->parent_phone,
                    'role' => 'parent',
                    'password' => Hash::make(substr($admission->parent_phone, -6)),
                ]);
            $student->guardians()->syncWithoutDetaching([$parent->id => ['relation' => $admission->relation]]);
            $admission->update(['status' => 'enrolled', 'student_id' => $student->id]);

            return $student;
        });

        return redirect()->route('students.show', $student)->with('success', "มอบตัวเรียบร้อย {$student->fullName()} รหัส {$student->student_code} · ผู้ปกครองเข้าระบบด้วยเบอร์ {$admission->parent_phone} รหัสผ่าน 6 หลักท้ายเบอร์ · พิมพ์ใบมอบตัวได้ที่หน้าใบสมัคร");
    }
}
