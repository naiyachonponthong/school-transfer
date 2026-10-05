<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\FeeItem;
use App\Models\HomeVisit;
use App\Models\Scholarship;
use App\Models\ScholarshipAward;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\Term;
use App\Models\User;
use App\Services\Notifier;
use App\Support\Audit;
use App\Support\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * ทุนการศึกษา: ทะเบียนทุน การเสนอชื่อโดยครูประจำชั้น การพิจารณา และการมอบทุน
 * ผู้มีสิทธิ์ scholarships.manage ตั้งทุน พิจารณา และบันทึกการจ่าย · ครูประจำชั้นเสนอชื่อและเห็นเฉพาะนักเรียนในห้องตัวเอง
 */
class ScholarshipController extends Controller
{
    private function canManage(User $user): bool
    {
        return $user->hasPermission('scholarships.manage');
    }

    /** ห้องที่ผู้ใช้เสนอชื่อและเห็นรายชื่อได้ */
    private function classroomsFor(User $user)
    {
        return $this->canManage($user) ? Classroom::currentYear()->ordered()->get() : $user->myClassrooms();
    }

    public function index(Request $request)
    {
        $years = Scholarship::distinct()->orderByDesc('year')->pluck('year');
        $year = (int) $request->query('year', Term::current()?->year ?? $years->first() ?? now()->year + 543);

        return view('scholarships.index', [
            'year' => $year,
            'years' => $years->push($year)->unique()->sortDesc()->values(),
            'scholarships' => Scholarship::where('year', $year)->orderBy('name')
                ->withCount(['awards as nominated_count' => fn ($q) => $q->where('status', 'nominated'), 'awards as approved_count' => fn ($q) => $q->where('status', 'approved')])
                ->withSum(['awards as approved_sum' => fn ($q) => $q->where('status', 'approved')], 'amount')->get(),
            'canManage' => $this->canManage($request->user()),
            'feeItems' => FeeItem::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(Scholarship::CATEGORIES))],
            'donor' => ['nullable', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:2500', 'max:2700'],
            'mode' => ['required', Rule::in(array_keys(Scholarship::MODES))],
            'value_type' => ['required', Rule::in(['amount', 'percent'])],
            'value' => ['required', 'numeric', 'min:0.01', $request->input('value_type') === 'percent' ? 'max:100' : 'max:9999999'],
            'fee_item_id' => ['nullable', 'exists:fee_items,id'],
            'slots' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'budget' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'conditions' => ['nullable', 'string', 'max:5000'],
            'opens_on' => ['nullable', 'date'],
            'closes_on' => ['nullable', 'date', 'after_or_equal:opens_on'],
        ], [], ['name' => 'ชื่อทุน', 'value' => 'มูลค่าต่อทุน', 'slots' => 'จำนวนทุน', 'budget' => 'งบรวม', 'closes_on' => 'วันปิดรับ', 'opens_on' => 'วันเปิดรับ', 'year' => 'ปีการศึกษา']);
        // ทุนที่จ่ายเป็นเงินมีมูลค่าเป็นบาทเสมอ และไม่ผูกกับรายการค่าธรรมเนียม
        if ($data['mode'] === 'cash') {
            abort_if($data['value_type'] === 'percent', 422, 'ทุนที่จ่ายเป็นเงินต้องระบุมูลค่าเป็นบาท');
            $data['fee_item_id'] = null;
        }
        $data['is_open'] = $request->boolean('is_open');

        return $data;
    }

    public function store(Request $request)
    {
        $scholarship = Scholarship::create($this->validated($request) + ['created_by' => $request->user()->id]);
        Audit::log('finance.scholarship', $scholarship, "ตั้งทุนการศึกษา {$scholarship->name} ปี {$scholarship->year} ({$scholarship->valueLabel()})");

        return redirect()->route('scholarships.show', $scholarship)->with('success', "ตั้งทุน {$scholarship->name} แล้ว");
    }

    public function update(Request $request, Scholarship $scholarship)
    {
        $data = $this->validated($request);
        // มีผู้ได้รับทุนแล้ว: วิธีมอบและมูลค่าเปลี่ยนไม่ได้ เพราะส่วนลดและยอดที่อนุมัติไปแล้วอ้างค่าเดิม
        if ($scholarship->approvedCount() > 0) {
            $changed = $scholarship->mode !== $data['mode'] || $scholarship->value_type !== $data['value_type']
                || (float) $scholarship->value !== (float) $data['value'] || (int) $scholarship->fee_item_id !== (int) ($data['fee_item_id'] ?? 0);
            if ($changed) {
                return back()->withInput()->with('warning', 'ทุนนี้มีผู้ได้รับแล้ว เปลี่ยนวิธีมอบหรือมูลค่าต่อทุนไม่ได้ (เพิกถอนผู้ได้รับก่อน หรือตั้งทุนใหม่)');
            }
        }
        $scholarship->update($data);

        return back()->with('success', 'บันทึกทุนแล้ว');
    }

    public function show(Request $request, Scholarship $scholarship)
    {
        $user = $request->user();
        $manage = $this->canManage($user);
        $rooms = $this->classroomsFor($user);
        $awards = $scholarship->awards()->with(['student.classroom', 'nominator', 'decider', 'payer'])
            ->when(! $manage, fn ($q) => $q->whereHas('student', fn ($s) => $s->whereIn('classroom_id', $rooms->pluck('id'))))
            ->get()->sortBy(fn (ScholarshipAward $a) => [array_search($a->status, array_keys(ScholarshipAward::STATUSES)), $a->student->classroom?->level_order, $a->student->student_code])->values();

        return view('scholarships.show', [
            'scholarship' => $scholarship->load('feeItem'),
            'awards' => $awards,
            'facts' => $this->facts($awards->pluck('student')),
            'canManage' => $manage,
            'rooms' => $rooms,
            // นักเรียนที่เสนอชื่อได้: ห้องที่ดูแล และยังไม่ถูกเสนอในทุนนี้
            'candidates' => $scholarship->acceptsNominations()
                ? Student::active()->with('classroom')->whereIn('classroom_id', $rooms->pluck('id'))
                    ->whereNotIn('id', $scholarship->awards()->pluck('student_id'))->orderBy('classroom_id')->orderBy('number')->get()
                : collect(),
            'approvedCount' => $scholarship->approvedCount(),
            'approvedAmount' => $scholarship->approvedAmount(),
            'feeItems' => FeeItem::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * ข้อมูลประกอบการพิจารณาจากที่มีอยู่แล้วในระบบ: เกรดเฉลี่ย ความประพฤติ เวลาเรียน และเยี่ยมบ้านแล้วหรือยัง
     * ไม่แสดงรายได้หรือรายละเอียดจากแบบเยี่ยมบ้าน (ข้อมูลอ่อนไหว ผู้พิจารณาเปิดดูได้ตามสิทธิ์ของตัวเองในเมนูดูแลช่วยเหลือ)
     *
     * @return array<int, array{gpa: ?float, percent: ?float, behavior: int, attendance: ?float, visited: bool}>
     */
    private function facts($students): array
    {
        $term = Term::current();
        $ids = $students->pluck('id');
        $attendance = Attendance::whereIn('student_id', $ids)
            ->when($term?->start_date, fn ($q) => $q->where('date', '>=', $term->start_date->toDateString()))
            ->select('student_id', DB::raw("sum(case when status in ('present','late') then 1 else 0 end) as came"), DB::raw('count(*) as total'))
            ->groupBy('student_id')->get()->keyBy('student_id');
        $visited = $term ? HomeVisit::where('term_id', $term->id)->whereIn('student_id', $ids)->pluck('student_id')->flip() : collect();

        $out = [];
        foreach ($students as $student) {
            $row = $attendance[$student->id] ?? null;
            $grades = StudentController::gradesFor($student, $term);
            // ระหว่างภาคยังไม่มีเกรด ใช้ร้อยละของคะแนนที่เก็บแล้วแทน
            $scored = $grades->filter(fn ($g) => $g['total'] !== null && $g['max'] > 0);
            $out[$student->id] = [
                'gpa' => Grade::gpa($grades->map(fn ($g) => ['grade' => $g['grade'], 'credit' => (float) $g['course']->subject->credit])),
                'percent' => $scored->isNotEmpty() ? round($scored->sum('total') / $scored->sum('max') * 100, 1) : null,
                'behavior' => $student->behaviorScore(),
                'attendance' => $row && $row->total ? round($row->came / $row->total * 100, 1) : null,
                'visited' => $visited->has($student->id),
            ];
        }

        return $out;
    }

    /** ครูประจำชั้น (หรือผู้จัดการทุน) เสนอชื่อนักเรียนในห้องที่ดูแล */
    public function nominate(Request $request, Scholarship $scholarship)
    {
        abort_unless($scholarship->acceptsNominations(), 422, 'ทุนนี้ปิดรับการเสนอชื่อแล้ว');
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'reason' => ['required', 'string', 'max:2000'],
        ], [], ['student_id' => 'นักเรียน', 'reason' => 'เหตุผล']);
        $student = Student::active()->findOrFail($data['student_id']);
        abort_unless($this->classroomsFor($request->user())->contains('id', $student->classroom_id), 403, 'เสนอชื่อได้เฉพาะนักเรียนในห้องที่คุณเป็นครูประจำชั้น');
        if ($scholarship->awards()->where('student_id', $student->id)->exists()) {
            return back()->with('warning', "{$student->fullName()} ถูกเสนอชื่อในทุนนี้แล้ว");
        }
        $scholarship->awards()->create(['student_id' => $student->id, 'reason' => $data['reason'], 'nominated_by' => $request->user()->id]);

        return back()->with('success', "เสนอชื่อ {$student->fullName()} แล้ว");
    }

    /** ผู้เสนอถอนชื่อได้ตราบที่ยังไม่ถูกพิจารณา */
    public function withdraw(Request $request, ScholarshipAward $award)
    {
        abort_unless($award->status === 'nominated', 422, 'รายการนี้พิจารณาไปแล้ว ถอนชื่อไม่ได้');
        abort_unless($this->canManage($request->user()) || $award->nominated_by === $request->user()->id, 403);
        $award->delete();

        return back()->with('success', 'ถอนการเสนอชื่อแล้ว');
    }

    /** พิจารณาหลายคนพร้อมกัน: อนุมัติ สำรอง หรือไม่อนุมัติ */
    public function decide(Request $request, Scholarship $scholarship)
    {
        $data = $request->validate([
            'awards' => ['required', 'array', 'min:1'], 'awards.*' => ['integer'],
            'decision' => ['required', Rule::in(['approved', 'reserve', 'rejected'])],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['awards.required' => 'เลือกอย่างน้อย 1 คน'], ['awards' => 'รายชื่อ', 'decision' => 'ผลการพิจารณา']);

        $count = DB::transaction(function () use ($scholarship, $data, $request) {
            $scholarship = Scholarship::whereKey($scholarship->id)->lockForUpdate()->firstOrFail();
            // พิจารณาได้เฉพาะคนที่รอพิจารณาหรือสำรอง (สำรองเลื่อนขึ้นมาได้เมื่อมีทุนว่าง)
            $awards = $scholarship->awards()->with('student')->whereIn('id', $data['awards'])->whereIn('status', ['nominated', 'reserve'])->get()
                ->reject(fn (ScholarshipAward $a) => $a->status === $data['decision']);
            if ($data['decision'] === 'approved' && ($problem = $scholarship->roomFor($awards->count()))) {
                abort(422, $problem);
            }
            foreach ($awards as $award) {
                $award->update(['status' => $data['decision'], 'decided_by' => $request->user()->id, 'decided_at' => now(), 'decision_note' => $data['note'] ?? null,
                    'amount' => $data['decision'] === 'approved' ? $scholarship->awardAmount() : null]);
                if ($data['decision'] === 'approved') {
                    $this->grant($scholarship, $award, $request->user());
                }
            }

            return $awards->count();
        });
        if (! $count) {
            return back()->with('warning', 'ไม่มีรายการที่พิจารณาได้');
        }
        Audit::log('finance.scholarship', $scholarship, "พิจารณาทุน {$scholarship->name}: ".ScholarshipAward::STATUSES[$data['decision']][0]." {$count} คน");

        return back()->with('success', 'บันทึกผลการพิจารณา '.$count.' คนแล้ว');
    }

    /** ผลของการอนุมัติ: ทุนลดค่าธรรมเนียมสร้างส่วนลดประจำตัวให้ ใบแจ้งหนี้ที่ออกจากแผนหลังจากนี้จึงหักเอง แล้วแจ้งผู้ปกครอง */
    private function grant(Scholarship $scholarship, ScholarshipAward $award, User $by): void
    {
        if ($scholarship->mode === 'discount') {
            StudentDiscount::create([
                'student_id' => $award->student_id, 'name' => 'ทุน'.$scholarship->name, 'type' => $scholarship->value_type, 'value' => $scholarship->value,
                'fee_item_id' => $scholarship->fee_item_id, 'year' => $scholarship->year, 'is_active' => true, 'created_by' => $by->id, 'scholarship_award_id' => $award->id,
            ]);
        }
        Notifier::parents($award->student, "🎓 {$award->student->fullName()} ได้รับทุนการศึกษา \"{$scholarship->name}\" ({$scholarship->valueLabel()}) "
            .($scholarship->mode === 'discount' ? 'ระบบจะหักจากค่าธรรมเนียมให้อัตโนมัติ' : 'โรงเรียนจะแจ้งวันรับทุนอีกครั้ง'));
    }

    /** เพิกถอนทุนที่อนุมัติไปแล้ว: ส่วนลดที่สร้างจากทุนถูกปิด (ใบแจ้งหนี้ที่ออกไปแล้วไม่เปลี่ยน) */
    public function revoke(Request $request, ScholarshipAward $award)
    {
        abort_unless($award->status === 'approved', 422, 'เพิกถอนได้เฉพาะทุนที่อนุมัติแล้ว');
        abort_if($award->paid_at, 422, 'ทุนนี้จ่ายเงินไปแล้ว เพิกถอนไม่ได้');
        $data = $request->validate(['note' => ['required', 'string', 'max:255']], [], ['note' => 'เหตุผล']);
        DB::transaction(function () use ($award, $data, $request) {
            $award->update(['status' => 'revoked', 'amount' => null, 'decided_by' => $request->user()->id, 'decided_at' => now(), 'decision_note' => $data['note']]);
            StudentDiscount::where('scholarship_award_id', $award->id)->update(['is_active' => false]);
        });
        Audit::log('finance.scholarship', $award->scholarship, "เพิกถอนทุน {$award->scholarship->name} ของ {$award->student->fullName()}: {$data['note']}");

        return back()->with('success', 'เพิกถอนทุนแล้ว');
    }

    /** บันทึกการจ่ายทุนที่จ่ายเป็นเงิน/สิ่งของ และออกเลขที่ใบสำคัญรับเงิน */
    public function pay(Request $request, ScholarshipAward $award)
    {
        abort_unless($award->status === 'approved' && $award->scholarship->mode === 'cash', 422, 'บันทึกการจ่ายได้เฉพาะทุนที่จ่ายเป็นเงินและอนุมัติแล้ว');
        $data = $request->validate(['received_by' => ['required', 'string', 'max:255']], [], ['received_by' => 'ผู้รับเงิน']);
        $paid = DB::transaction(function () use ($award, $data, $request) {
            $award = ScholarshipAward::whereKey($award->id)->lockForUpdate()->firstOrFail();
            if ($award->paid_at) {
                return false;
            }
            $award->update(['paid_at' => now(), 'paid_by' => $request->user()->id, 'received_by' => $data['received_by'], 'doc_no' => ScholarshipAward::nextNumber()]);

            return true;
        });
        if (! $paid) {
            return back()->with('warning', 'ทุนนี้บันทึกการจ่ายไปแล้ว');
        }
        $award->refresh();
        Audit::log('finance.scholarship', $award->scholarship, "จ่ายทุน {$award->scholarship->name} ".baht($award->amount)." บาท ให้ {$award->student->fullName()} เลขที่ {$award->doc_no}");

        return redirect()->route('scholarships.receipt', $award)->with('success', "บันทึกการจ่ายแล้ว เลขที่ {$award->doc_no}");
    }

    /** ใบสำคัญรับเงินทุนการศึกษา */
    public function receipt(ScholarshipAward $award)
    {
        abort_unless($award->paid_at, 404);

        return view('scholarships.receipt', ['award' => $award->load(['scholarship', 'student.classroom', 'payer'])]);
    }

    /** ประกาศรายชื่อผู้ได้รับทุน */
    public function announce(Scholarship $scholarship)
    {
        return view('scholarships.announce', [
            'scholarship' => $scholarship,
            'approved' => $this->listed($scholarship, 'approved'),
            'reserve' => $this->listed($scholarship, 'reserve'),
        ]);
    }

    private function listed(Scholarship $scholarship, string $status)
    {
        return $scholarship->awards()->with('student.classroom')->where('status', $status)->get()
            ->sortBy(fn (ScholarshipAward $a) => [$a->student->classroom?->level_order, $a->student->classroom?->room, $a->student->number])->values();
    }

    public function export(Scholarship $scholarship)
    {
        $awards = $scholarship->awards()->with(['student.classroom', 'nominator', 'decider'])->get();

        return response()->streamDownload(function () use ($awards, $scholarship) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['รหัส', 'ชื่อ-สกุล', 'ห้อง', 'สถานะ', 'มูลค่า (บาท)', 'เหตุผลที่เสนอ', 'ผู้เสนอ', 'ผู้พิจารณา', 'วันที่พิจารณา', 'หมายเหตุ', 'เลขที่ใบสำคัญ', 'วันที่จ่าย', 'ผู้รับเงิน']);
            foreach ($awards as $a) {
                fputcsv($out, [$a->student->student_code, $a->student->fullName(), $a->student->classroom?->name() ?? '-', $a->statusLabel(),
                    $a->amount !== null ? number_format((float) $a->amount, 2, '.', '') : ($a->status === 'approved' ? $scholarship->valueLabel() : ''),
                    $a->reason, $a->nominator?->name, $a->decider?->name, $a->decided_at ? thai_date($a->decided_at) : '', $a->decision_note,
                    $a->doc_no, $a->paid_at ? thai_date($a->paid_at) : '', $a->received_by]);
            }
            fclose($out);
        }, "ทุน-{$scholarship->name}-{$scholarship->year}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
