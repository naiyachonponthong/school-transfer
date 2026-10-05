<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\FeeItem;
use App\Models\FeePlan;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\Term;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** ผังค่าธรรมเนียม: รายการมาตรฐาน → แผนเรียกเก็บรายระดับชั้น → ออกใบแจ้งหนี้ทั้งระดับชั้น + ส่วนลดประจำตัว */
class FeePlanController extends Controller
{
    public function index()
    {
        $term = Term::current();

        return view('fees.index', [
            'items' => FeeItem::orderBy('category')->orderBy('name')->get(),
            'plans' => FeePlan::with(['items.feeItem', 'term'])->withCount(['invoices' => fn ($q) => $q->where('status', '!=', 'void')])
                ->orderByDesc('year')->orderByDesc('id')->get(),
            'discounts' => StudentDiscount::with(['student.classroom', 'feeItem'])->latest()->get(),
            'terms' => Term::orderByDesc('year')->orderByDesc('term')->get(),
            'term' => $term,
            'levels' => Classroom::LEVELS,
        ]);
    }

    public function storeItem(Request $request)
    {
        FeeItem::create($this->itemData($request));

        return back()->with('success', 'เพิ่มรายการค่าธรรมเนียมแล้ว');
    }

    public function updateItem(Request $request, FeeItem $item)
    {
        $item->update($this->itemData($request) + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'บันทึกรายการแล้ว');
    }

    private function itemData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:60'],
            'default_amount' => ['required', 'numeric', 'min:0'],
        ], [], ['name' => 'ชื่อรายการ', 'category' => 'หมวด', 'default_amount' => 'จำนวนเงิน']);
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'term_id' => ['required', 'exists:terms,id'],
            'levels' => ['required', 'array', 'min:1'],
            'levels.*' => [Rule::in(Classroom::LEVELS)],
            'due_date' => ['nullable', 'date'],
            'amounts' => ['required', 'array'],
            'amounts.*' => ['nullable', 'numeric', 'min:0'],
        ], ['levels.required' => 'เลือกระดับชั้นอย่างน้อย 1 ระดับ']);

        $amounts = collect($data['amounts'])->filter(fn ($a) => $a !== null && $a > 0);
        $valid = FeeItem::whereIn('id', $amounts->keys())->pluck('id');
        if ($valid->isEmpty()) {
            return back()->withInput()->withErrors(['amounts' => 'ใส่จำนวนเงินอย่างน้อย 1 รายการ']);
        }
        $term = Term::findOrFail($data['term_id']);

        // ระดับชั้นละ 1 แผน (ยอดเท่ากัน แก้ทีหลังไม่ได้ ถ้ายอดต่างกันให้สร้างแยก)
        foreach ($data['levels'] as $level) {
            $plan = FeePlan::create(['title' => $data['title'], 'year' => $term->year, 'term_id' => $term->id, 'level' => $level, 'due_date' => $data['due_date'] ?? null]);
            $plan->items()->createMany($valid->map(fn ($id) => ['fee_item_id' => $id, 'amount' => $amounts[$id]])->all());
        }

        return back()->with('success', 'สร้างแผนเรียกเก็บ '.count($data['levels']).' ระดับชั้นแล้ว กด "ออกใบแจ้งหนี้" เมื่อพร้อม');
    }

    public function destroyPlan(FeePlan $plan)
    {
        abort_if($plan->invoices()->exists(), 422, 'แผนนี้ออกใบแจ้งหนี้ไปแล้ว ลบไม่ได้');
        $plan->delete();

        return back()->with('success', 'ลบแผนแล้ว');
    }

    public function issue(Request $request, FeePlan $plan)
    {
        $count = $plan->issue($request->user());
        if ($count) {
            Audit::log('finance.plan_issue', $plan, "ออกใบแจ้งหนี้จากแผน {$plan->title} ชั้น {$plan->level} จำนวน {$count} ใบ");
        }

        return back()->with('success', $count
            ? "ออกใบแจ้งหนี้ \"{$plan->title}\" ชั้น {$plan->level} จำนวน {$count} ใบแล้ว"
            : "ไม่มีนักเรียนชั้น {$plan->level} ที่ยังไม่ได้รับใบแจ้งหนี้จากแผนนี้");
    }

    public function storeDiscount(Request $request)
    {
        $data = $request->validate([
            'student_code' => ['required', 'string', Rule::exists('students', 'student_code')],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(StudentDiscount::TYPES))],
            'value' => ['required', 'numeric', 'min:0.01', $request->input('type') === 'percent' ? 'max:100' : 'max:9999999'],
            'fee_item_id' => ['nullable', 'exists:fee_items,id'],
            'year' => ['nullable', 'integer', 'min:2500', 'max:2700'],
        ], ['student_code.exists' => 'ไม่พบรหัสนักเรียนนี้'], ['name' => 'ชื่อส่วนลด', 'value' => 'จำนวน']);

        StudentDiscount::create([
            'student_id' => Student::where('student_code', $data['student_code'])->value('id'),
            'name' => $data['name'], 'type' => $data['type'], 'value' => $data['value'],
            'fee_item_id' => $data['fee_item_id'] ?? null, 'year' => $data['year'] ?? null, 'created_by' => $request->user()->id,
        ]);

        Audit::log('finance.discount', null, "เพิ่มส่วนลดประจำตัว {$data['name']} ให้นักเรียนรหัส {$data['student_code']}");

        return back()->with('success', 'เพิ่มส่วนลดประจำตัวแล้ว มีผลกับใบแจ้งหนี้ที่ออกจากแผนหลังจากนี้');
    }

    public function destroyDiscount(StudentDiscount $discount)
    {
        // ส่วนลดที่มาจากทุนการศึกษาต้องเพิกถอนที่ทุน ทะเบียนทุนกับส่วนลดจึงตรงกันเสมอ
        if ($discount->scholarship_award_id) {
            return back()->with('warning', 'ส่วนลดนี้มาจากทุนการศึกษา ให้เพิกถอนทุนที่เมนูทุนการศึกษาแทนการลบ');
        }
        Audit::log('finance.discount', $discount, "ลบส่วนลดประจำตัว {$discount->name}");
        $discount->delete();

        return back()->with('success', 'ลบส่วนลดแล้ว (ใบแจ้งหนี้ที่ออกไปแล้วไม่เปลี่ยน)');
    }
}
