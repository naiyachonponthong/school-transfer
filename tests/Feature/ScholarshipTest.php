<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Role;
use App\Models\Scholarship;
use App\Models\ScholarshipAward;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ทุนการศึกษา: ตั้งทุน เสนอชื่อ พิจารณา มอบแบบลดค่าธรรมเนียมและจ่ายเป็นเงิน เพิกถอน และสิทธิ์การมองเห็น */
class ScholarshipTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    /** ครูประจำชั้นและห้องของตัวเอง */
    private function homeroom(): array
    {
        $teacher = User::where('username', 'teacher')->first();

        return [$teacher, $teacher->myClassrooms()->first()];
    }

    private function make(array $overrides = []): Scholarship
    {
        $this->actingAs($this->admin())->post('/scholarships', $overrides + [
            'name' => 'ทุนเรียนดี', 'category' => 'merit', 'donor' => 'มูลนิธิศิษย์เก่า', 'year' => Term::current()->year,
            'mode' => 'cash', 'value_type' => 'amount', 'value' => 2000, 'slots' => 2, 'budget' => 4000, 'is_open' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return Scholarship::latest('id')->first();
    }

    public function test_homeroom_teacher_nominates_only_own_students_and_sees_only_them(): void
    {
        [$teacher, $room] = $this->homeroom();
        $mine = $room->students()->first();
        $other = Student::active()->whereNotIn('classroom_id', $teacher->myClassrooms()->pluck('id'))->first();
        $scholarship = $this->make();

        // ครูทั่วไปตั้งทุนหรือพิจารณาไม่ได้
        $this->actingAs($teacher)->post('/scholarships', ['name' => 'x'])->assertForbidden();
        $this->actingAs($teacher)->get('/scholarships')->assertOk()->assertSee('ทุนเรียนดี')->assertDontSee('ตั้งทุน');

        $this->actingAs($teacher)->post("/scholarships/{$scholarship->id}/nominate", ['student_id' => $mine->id])->assertSessionHasErrors('reason');
        $this->actingAs($teacher)->post("/scholarships/{$scholarship->id}/nominate", ['student_id' => $mine->id, 'reason' => 'เรียนดีต่อเนื่อง'])->assertSessionHas('success');
        $this->actingAs($teacher)->post("/scholarships/{$scholarship->id}/nominate", ['student_id' => $mine->id, 'reason' => 'ซ้ำ'])->assertSessionHas('warning');
        $this->actingAs($teacher)->post("/scholarships/{$scholarship->id}/nominate", ['student_id' => $other->id, 'reason' => 'ไม่ใช่ห้องตัวเอง'])->assertForbidden();
        $this->actingAs($this->admin())->post("/scholarships/{$scholarship->id}/nominate", ['student_id' => $other->id, 'reason' => 'ฝ่ายการเงินเสนอ'])->assertSessionHas('success');

        // ครูเห็นเฉพาะนักเรียนในห้องตัวเอง ผู้จัดการทุนเห็นทุกคนพร้อมข้อมูลประกอบ
        $this->actingAs($teacher)->get('/profile'); // ล้างข้อความแจ้งเตือนจากการเสนอชื่อ (มีชื่อนักเรียนอยู่)
        $this->actingAs($teacher)->get("/scholarships/{$scholarship->id}")->assertOk()->assertSee($mine->fullName())->assertDontSee($other->fullName())->assertDontSee('บันทึกผลของคนที่เลือก');
        $this->actingAs($this->admin())->get("/scholarships/{$scholarship->id}")->assertOk()->assertSee($mine->fullName())->assertSee($other->fullName())->assertSee('ผลการเรียน')->assertSee('บันทึกผลของคนที่เลือก');
        $this->actingAs($teacher)->post("/scholarships/{$scholarship->id}/decide", ['awards' => [1], 'decision' => 'approved'])->assertForbidden();

        // ถอนชื่อได้เฉพาะของที่ตัวเองเสนอ และก่อนถูกพิจารณา
        $theirs = ScholarshipAward::where('student_id', $other->id)->first();
        $this->actingAs($teacher)->delete("/scholarship-awards/{$theirs->id}")->assertForbidden();
        $own = ScholarshipAward::where('student_id', $mine->id)->first();
        $this->actingAs($teacher)->delete("/scholarship-awards/{$own->id}")->assertSessionHas('success');
        $this->assertModelMissing($own);

        // ปิดรับแล้วเสนอชื่อไม่ได้
        $scholarship->update(['closes_on' => today()->subDay()]);
        $this->actingAs($teacher)->post("/scholarships/{$scholarship->id}/nominate", ['student_id' => $mine->id, 'reason' => 'ช้าไป'])->assertStatus(422);
    }

    public function test_cash_scholarship_respects_slots_and_budget_then_pays_with_a_numbered_receipt(): void
    {
        $scholarship = $this->make();
        $students = Student::active()->orderBy('id')->limit(3)->get();
        foreach ($students as $s) {
            $scholarship->awards()->create(['student_id' => $s->id, 'reason' => 'เสนอ', 'nominated_by' => $this->admin()->id]);
        }
        $ids = $scholarship->awards()->orderBy('id')->pluck('id');

        // 3 คนเกินจำนวนทุน (2) ไม่อนุมัติให้ใครเลย
        $this->actingAs($this->admin())->post("/scholarships/{$scholarship->id}/decide", ['awards' => $ids->all(), 'decision' => 'approved'])->assertStatus(422);
        $this->assertSame(0, $scholarship->approvedCount());

        $this->actingAs($this->admin())->post("/scholarships/{$scholarship->id}/decide", ['awards' => [$ids[0], $ids[1]], 'decision' => 'approved', 'note' => 'มติ 1/2569'])->assertSessionHas('success');
        $this->actingAs($this->admin())->post("/scholarships/{$scholarship->id}/decide", ['awards' => [$ids[2]], 'decision' => 'reserve'])->assertSessionHas('success');
        $this->assertSame(['approved', 'approved', 'reserve'], $scholarship->awards()->orderBy('id')->pluck('status')->all());
        $this->assertSame('4000.00', (string) number_format($scholarship->approvedAmount(), 2, '.', ''));
        $this->assertSame(0, StudentDiscount::whereNotNull('scholarship_award_id')->count());

        // สำรองเลื่อนขึ้นไม่ได้จนกว่าจะมีทุนว่าง
        $this->actingAs($this->admin())->post("/scholarships/{$scholarship->id}/decide", ['awards' => [$ids[2]], 'decision' => 'approved'])->assertStatus(422);
        $first = ScholarshipAward::find($ids[0]);
        $this->actingAs($this->admin())->post("/scholarship-awards/{$first->id}/revoke", [])->assertSessionHasErrors('note');
        $this->actingAs($this->admin())->post("/scholarship-awards/{$first->id}/revoke", ['note' => 'ย้ายโรงเรียน'])->assertSessionHas('success');
        $this->actingAs($this->admin())->post("/scholarships/{$scholarship->id}/decide", ['awards' => [$ids[2]], 'decision' => 'approved'])->assertSessionHas('success');
        $this->assertSame(['revoked', 'approved', 'approved'], $scholarship->awards()->orderBy('id')->pluck('status')->all());

        // จ่ายทุน: ได้เลขที่ใบสำคัญ จ่ายซ้ำไม่ได้ จ่ายแล้วเพิกถอนไม่ได้
        $second = ScholarshipAward::find($ids[1]);
        $this->actingAs($this->admin())->post("/scholarship-awards/{$second->id}/pay", ['received_by' => 'นางสมศรี ใจดี'])->assertRedirect(route('scholarships.receipt', $second));
        $second->refresh();
        $this->assertStringStartsWith('SC'.now()->format('Ym'), $second->doc_no);
        $this->actingAs($this->admin())->get("/scholarship-awards/{$second->id}/receipt")->assertOk()->assertSee($second->doc_no)->assertSee('นางสมศรี ใจดี')->assertSee('สองพันบาทถ้วน');
        $this->actingAs($this->admin())->post("/scholarship-awards/{$second->id}/pay", ['received_by' => 'อีกครั้ง'])->assertSessionHas('warning');
        $this->actingAs($this->admin())->post("/scholarship-awards/{$second->id}/revoke", ['note' => 'x'])->assertStatus(422);
        $this->actingAs($this->admin())->post("/scholarship-awards/{$first->id}/pay", ['received_by' => 'ถูกเพิกถอนแล้ว'])->assertStatus(422);

        $this->actingAs($this->admin())->get("/scholarships/{$scholarship->id}/announce")->assertOk()->assertSee('ประกาศ')->assertSee($students[1]->fullName())->assertDontSee($students[0]->fullName());
        $this->actingAs($this->admin())->get("/scholarships/{$scholarship->id}/export")->assertOk();

        // มีผู้ได้รับแล้ว แก้มูลค่าไม่ได้ แต่แก้อย่างอื่นได้
        $base = ['name' => 'ทุนเรียนดี (แก้ชื่อ)', 'category' => 'merit', 'year' => $scholarship->year, 'mode' => 'cash', 'value_type' => 'amount', 'slots' => 5, 'is_open' => 1];
        $this->actingAs($this->admin())->put("/scholarships/{$scholarship->id}", $base + ['value' => 9999])->assertSessionHas('warning');
        $this->actingAs($this->admin())->put("/scholarships/{$scholarship->id}", $base + ['value' => 2000])->assertSessionHas('success');
        $this->assertSame(['ทุนเรียนดี (แก้ชื่อ)', '2000.00', 5], [$scholarship->fresh()->name, $scholarship->fresh()->value, $scholarship->fresh()->slots]);
    }

    public function test_discount_scholarship_creates_a_student_discount_and_revoking_switches_it_off(): void
    {
        $scholarship = $this->make(['name' => 'ทุนขาดแคลน', 'category' => 'need', 'mode' => 'discount', 'value_type' => 'percent', 'value' => 50, 'slots' => '', 'budget' => '']);
        $parent = User::where('phone', '0812345678')->first();
        $child = $parent->children()->first();
        $award = $scholarship->awards()->create(['student_id' => $child->id, 'reason' => 'ครอบครัวรายได้น้อย', 'nominated_by' => $this->admin()->id]);

        // ผู้ปกครองยังไม่เห็นจนกว่าจะอนุมัติ
        $this->actingAs($parent)->get('/profile'); // ล้างข้อความแจ้งเตือนจากการตั้งทุน (มีชื่อทุนอยู่)
        $this->actingAs($parent)->get("/parent/child/{$child->id}?tab=fees")->assertOk()->assertDontSee('ทุนขาดแคลน');

        $this->actingAs($this->admin())->post("/scholarships/{$scholarship->id}/decide", ['awards' => [$award->id], 'decision' => 'approved'])->assertSessionHas('success');
        $discount = StudentDiscount::where('scholarship_award_id', $award->id)->sole();
        $this->assertSame([$child->id, 'ทุนทุนขาดแคลน', 'percent', 50.0, $scholarship->year, true],
            [$discount->student_id, $discount->name, $discount->type, $discount->value, $discount->year, $discount->is_active]);
        $this->assertNull($award->fresh()->amount);

        $this->actingAs($parent)->get("/parent/child/{$child->id}?tab=fees")->assertOk()->assertSee('ทุนขาดแคลน')->assertSee('ได้รับทุน');
        $this->actingAs($this->admin())->get("/students/{$child->id}")->assertOk()->assertSee('ทุนขาดแคลน');

        // ส่วนลดที่มาจากทุนลบตรง ๆ ไม่ได้ ต้องเพิกถอนที่ทุน
        $this->actingAs($this->admin())->delete("/fees/discounts/{$discount->id}")->assertSessionHas('warning');
        $this->assertModelExists($discount);
        $this->actingAs($this->admin())->post("/scholarship-awards/{$award->id}/revoke", ['note' => 'ขาดคุณสมบัติ'])->assertSessionHas('success');
        $this->assertFalse($discount->fresh()->is_active);
        $this->actingAs($parent)->get('/profile');
        $this->actingAs($parent)->get("/parent/child/{$child->id}?tab=fees")->assertOk()->assertDontSee('ทุนขาดแคลน');

        // นักเรียนที่มีประวัติทุนลบไม่ได้
        $fresh = Student::create(['student_code' => 'SC001', 'first_name' => 'ใหม่', 'last_name' => 'ทุน', 'classroom_id' => Classroom::first()->id]);
        $scholarship->awards()->create(['student_id' => $fresh->id, 'reason' => 'เสนอ']);
        $this->actingAs($this->admin())->delete("/students/{$fresh->id}")->assertSessionHasErrors('student');
        $this->assertStringContainsString('ทุนการศึกษา', session('errors')->first('student'));
    }

    public function test_finance_role_manages_scholarships_and_menu_shows_pending_nominations(): void
    {
        $finance = User::where('username', 't3')->first();
        $finance->roles()->sync([Role::where('key', 'finance')->first()->id]);
        $scholarship = $this->make();
        $scholarship->awards()->create(['student_id' => Student::active()->first()->id, 'reason' => 'เสนอ']);

        $this->actingAs($finance->fresh())->get('/scholarships')->assertOk()->assertSee('ตั้งทุน');
        $this->actingAs($finance->fresh())->get('/menu')->assertOk()->assertSee('ทุนการศึกษา');
        $this->actingAs($finance->fresh())->get("/scholarships/{$scholarship->id}")->assertOk()->assertSee('บันทึกผลของคนที่เลือก');
        // ทุนจ่ายเป็นเงินใช้ร้อยละไม่ได้
        $this->actingAs($finance->fresh())->post('/scholarships', ['name' => 'ผิด', 'category' => 'other', 'year' => 2569, 'mode' => 'cash', 'value_type' => 'percent', 'value' => 50])->assertStatus(422);
    }
}
