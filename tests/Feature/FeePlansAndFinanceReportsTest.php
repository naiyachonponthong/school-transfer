<?php

namespace Tests\Feature;

use App\Models\CashClosing;
use App\Models\Classroom;
use App\Models\FeeItem;
use App\Models\FeePlan;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeePlansAndFinanceReportsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    /** @return array{0: FeePlan, 1: FeeItem, 2: FeeItem, 3: string} แผน 3,000 + 500 ของระดับชั้นที่มีนักเรียน */
    private function plan(): array
    {
        $level = Classroom::currentYear()->whereHas('students')->value('level');
        $tuition = FeeItem::create(['name' => 'ค่าเล่าเรียน', 'category' => 'ค่าเล่าเรียน', 'default_amount' => 3000]);
        $books = FeeItem::create(['name' => 'ค่าหนังสือ', 'category' => 'อุปกรณ์', 'default_amount' => 500]);

        $this->actingAs($this->admin())->post(route('fees.plans.store'), [
            'title' => 'ค่าธรรมเนียมทดสอบ', 'term_id' => Term::current()->id, 'levels' => [$level],
            'due_date' => today()->addDays(30)->toDateString(), 'amounts' => [$tuition->id => 3000, $books->id => 500],
        ])->assertSessionHasNoErrors();

        return [FeePlan::where('level', $level)->latest('id')->first(), $tuition, $books, $level];
    }

    private function levelStudents(string $level)
    {
        return Student::active()->whereHas('classroom', fn ($q) => $q->where('year', Term::current()->year)->where('level', $level))->get();
    }

    /* ---------------- ผังค่าธรรมเนียม + ส่วนลด ---------------- */

    public function test_plan_issues_one_invoice_per_student_and_never_twice(): void
    {
        [$plan, , , $level] = $this->plan();
        $students = $this->levelStudents($level);

        $this->get(route('fees.index'))->assertOk()->assertSee('ค่าธรรมเนียมทดสอบ')->assertSee('ออกใบแจ้งหนี้');
        $this->post(route('fees.plans.issue', $plan))->assertRedirect();

        $invoices = Invoice::where('fee_plan_id', $plan->id)->with('items')->get();
        $this->assertSame($students->count(), $invoices->count());
        $this->assertEquals(3500, $invoices->first()->total);
        $this->assertCount(2, $invoices->first()->items);
        $this->assertNotNull($invoices->first()->items->first()->fee_item_id);
        $this->assertSame($invoices->count(), $invoices->pluck('invoice_no')->unique()->count());

        // กดซ้ำไม่ออกซ้ำ · นักเรียนที่เข้ามาใหม่ได้รับเมื่อกดอีกครั้ง · ออกแล้วลบแผนไม่ได้
        $this->post(route('fees.plans.issue', $plan));
        $this->assertSame($students->count(), Invoice::where('fee_plan_id', $plan->id)->count());
        Student::create(['student_code' => 'NEW777', 'first_name' => 'มาใหม่', 'last_name' => 'ทดสอบ', 'status' => 'active', 'classroom_id' => $students->first()->classroom_id]);
        $this->post(route('fees.plans.issue', $plan));
        $this->assertSame($students->count() + 1, Invoice::where('fee_plan_id', $plan->id)->count());
        $this->delete(route('fees.plans.destroy', $plan))->assertStatus(422);
    }

    public function test_personal_discounts_are_applied_automatically(): void
    {
        [$plan, $tuition, , $level] = $this->plan();
        [$half, $fixed, $full, $none] = $this->levelStudents($level)->take(4)->all();

        $add = fn (Student $s, array $d) => $this->post(route('fees.discounts.store'), ['student_code' => $s->student_code] + $d)->assertSessionHasNoErrors();
        $add($half, ['name' => 'ทุนเรียนดี', 'type' => 'percent', 'value' => 50, 'fee_item_id' => $tuition->id]); // 50% ของค่าเล่าเรียน = 1,500
        $add($fixed, ['name' => 'พี่น้อง', 'type' => 'amount', 'value' => 700]);
        $add($full, ['name' => 'ทุนเต็มจำนวน', 'type' => 'percent', 'value' => 100]);
        $this->post(route('fees.discounts.store'), ['student_code' => 'nope', 'name' => 'x', 'type' => 'amount', 'value' => 1])->assertSessionHasErrors('student_code');
        $this->post(route('fees.discounts.store'), ['student_code' => $none->student_code, 'name' => 'x', 'type' => 'percent', 'value' => 150])->assertSessionHasErrors('value');

        $this->post(route('fees.plans.issue', $plan));
        $inv = fn (Student $s) => Invoice::where('fee_plan_id', $plan->id)->where('student_id', $s->id)->first();

        $this->assertEquals(1500, $inv($half)->discount);
        $this->assertSame('ทุนเรียนดี', $inv($half)->discount_note);
        $this->assertEquals(2000, $inv($half)->balance());
        $this->assertEquals(2800, $inv($fixed)->balance());
        $this->assertEquals(0, $inv($full)->balance());
        $this->assertSame('paid', $inv($full)->status);
        $this->assertEquals(3500, $inv($none)->balance());

        // ลบส่วนลดแล้วใบที่ออกไปแล้วไม่เปลี่ยน
        $this->delete(route('fees.discounts.destroy', StudentDiscount::where('student_id', $half->id)->first()))->assertRedirect();
        $this->assertEquals(1500, $inv($half)->discount);
    }

    /* ---------------- ผ่อนชำระ ---------------- */

    public function test_installments_split_the_balance_and_drive_the_due_date(): void
    {
        $inv = Invoice::where('status', 'unpaid')->first();
        $inv->update(['discount' => 0]);
        $net = $inv->netTotal();
        $first = today()->addDays(10);

        $this->actingAs($this->admin())->post(route('invoices.installments', $inv), ['count' => 3])->assertSessionHasErrors('first_due');
        $this->post(route('invoices.installments', $inv), ['count' => 3, 'first_due' => $first->toDateString()])->assertSessionHasNoErrors();

        $inv->refresh()->load('installments');
        $this->assertCount(3, $inv->installments);
        $this->assertEqualsWithDelta($net, $inv->installments->sum('amount'), 0.001);
        $this->assertSame($first->toDateString(), $inv->due_date->toDateString());
        $this->get(route('invoices.show', $inv))->assertOk()->assertSee('งวดที่ 3');

        // จ่ายงวดแรกครบ กำหนดชำระเลื่อนไปงวดที่สอง
        $this->post(route('invoices.pay', $inv), ['amount' => $inv->installments[0]->amount, 'method' => 'cash'])->assertRedirect();
        $inv->refresh()->load('installments');
        $this->assertSame($inv->installments[1]->due_date->toDateString(), $inv->due_date->toDateString());
        $this->assertSame(2, $inv->nextInstallment()->seq);

        // 1 งวด = ยกเลิกการแบ่ง
        $this->post(route('invoices.installments', $inv), ['count' => 1])->assertSessionHasNoErrors();
        $this->assertSame(0, $inv->installments()->count());
    }

    /* ---------------- ปิดยอด + รายงาน ---------------- */

    public function test_daily_closing_snapshots_totals_and_blocks_voiding(): void
    {
        $admin = $this->admin();
        [$a, $b] = Invoice::where('status', 'unpaid')->limit(2)->get()->all();
        $this->actingAs($admin)->post(route('invoices.pay', $a), ['amount' => 300, 'method' => 'cash']);
        $this->post(route('invoices.pay', $b), ['amount' => 200, 'method' => 'transfer']);
        $before = CashClosing::count();

        $this->get(route('finance.closing'))->assertOk()->assertSee($a->payments()->first()->receipt_no);
        $this->post(route('finance.close'), ['date' => today()->toDateString(), 'note' => 'นำฝากแล้ว'])->assertRedirect();

        $closing = CashClosing::whereDate('date', today())->first();
        $this->assertSame($before + 1, CashClosing::count());
        $this->assertGreaterThanOrEqual(300, $closing->cash);
        $this->assertGreaterThanOrEqual(200, $closing->transfer);
        $this->post(route('finance.close'), ['date' => today()->toDateString()])->assertStatus(422);
        $this->post(route('finance.close'), ['date' => today()->addDay()->toDateString()])->assertSessionHasErrors('date');

        // ปิดยอดแล้วยกเลิกใบเสร็จของวันนั้นไม่ได้ จนกว่าผู้ดูแลจะเปิดยอด
        $payment = $a->payments()->first();
        $this->post(route('payments.void', $payment), ['void_reason' => 'ผิด'])->assertStatus(422);

        $finance = User::where('role', 'teacher')->first();
        $finance->roles()->sync(Role::where('key', 'finance')->pluck('id'));
        $this->actingAs($finance)->delete(route('finance.reopen', $closing))->assertForbidden();
        $this->actingAs($admin)->delete(route('finance.reopen', $closing))->assertRedirect();
        $this->post(route('payments.void', $payment), ['void_reason' => 'ผิด'])->assertRedirect();
        $this->assertTrue($payment->fresh()->isVoided());
    }

    public function test_finance_reports_render_and_export(): void
    {
        [$plan, , , $level] = $this->plan();
        $this->post(route('fees.plans.issue', $plan));
        $inv = Invoice::where('fee_plan_id', $plan->id)->first();
        $this->post(route('invoices.pay', $inv), ['amount' => 3500, 'method' => 'cash']);
        $receipt = $inv->payments()->first()->receipt_no;
        Invoice::where('fee_plan_id', $plan->id)->where('id', '!=', $inv->id)->limit(1)->update(['due_date' => today()->subDays(45)]);

        $this->get(route('finance.reports'))->assertOk()->assertSee($receipt);
        // 3,500 ที่รับมา กระจายลงหมวดตามสัดส่วนรายการ: ค่าเล่าเรียน 3,000 · อุปกรณ์ 500
        $this->get(route('finance.reports', ['tab' => 'category']))->assertOk()->assertSee('ค่าเล่าเรียน')->assertSee('3,000.00')->assertSee('อุปกรณ์')->assertSee('500.00');
        $this->get(route('finance.reports', ['tab' => 'outstanding']))->assertOk()->assertSee('เกิน 31–60 วัน')->assertSee($inv->student->classroom->name());

        $csv = $this->get(route('finance.reports', ['tab' => 'receipts', 'export' => 1]));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString($receipt, $csv->streamedContent());

        // ครูทั่วไปเข้าไม่ได้ · ผู้บริหารดูรายงานได้แต่จัดการผังค่าธรรมเนียมไม่ได้
        $teacher = User::where('username', 'teacher')->first();
        $this->actingAs($teacher)->get(route('finance.reports'))->assertForbidden();
        $teacher->roles()->sync(Role::where('key', 'executive')->pluck('id'));
        $teacher->flushPermissions();
        $this->actingAs($teacher->fresh())->get(route('finance.reports'))->assertOk();
        $this->get(route('fees.index'))->assertForbidden();
    }
}
