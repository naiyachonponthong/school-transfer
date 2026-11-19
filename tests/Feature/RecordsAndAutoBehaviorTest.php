<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\BehaviorRecord;
use App\Models\DocumentIssue;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** หักคะแนนความประพฤติอัตโนมัติ + ระเบียนสะสม ปพ.8 + ประกาศนียบัตร ปพ.2 */
class RecordsAndAutoBehaviorTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    /** ภาคเรียนที่มีวันเริ่ม/สิ้นสุด ครอบช่วงวันที่ใช้ทดสอบ */
    private function term(): Term
    {
        $t = Term::current();
        $t->update(['start_date' => '2030-05-01', 'end_date' => '2030-09-30']);

        return $t->fresh();
    }

    private function mark(Student $s, string $date, string $status): Attendance
    {
        return Attendance::updateOrCreate(['student_id' => $s->id, 'date' => $date], ['classroom_id' => $s->classroom_id, 'status' => $status]);
    }

    private function auto(Student $s)
    {
        return BehaviorRecord::where('student_id', $s->id)->whereNotNull('auto_key')->orderBy('id')->get();
    }

    public function test_auto_deduction_is_off_by_default(): void
    {
        $this->term();
        $s = Student::where('status', 'active')->first();
        foreach (['2030-06-01', '2030-06-02', '2030-06-03'] as $d) {
            $this->mark($s, $d, 'late');
        }

        $this->assertCount(0, $this->auto($s));
    }

    public function test_late_every_three_times_deducts_and_refunds_when_fixed(): void
    {
        $this->term();
        Settings::set(['behavior_auto_late_every' => '3', 'behavior_auto_late_points' => '5']);
        $s = Student::where('status', 'active')->first();
        $before = $s->behaviorScore();

        $this->mark($s, '2030-06-01', 'late');
        $this->mark($s, '2030-06-02', 'late');
        $this->assertCount(0, $this->auto($s));
        $third = $this->mark($s, '2030-06-03', 'late');
        $this->assertCount(1, $this->auto($s));
        $this->assertSame(-5, $this->auto($s)->first()->points);
        $this->assertSame('2030-06-03', $this->auto($s)->first()->date->toDateString());
        $this->assertSame($before - 5, $s->fresh()->behaviorScore());

        // บันทึกซ้ำสถานะเดิม ไม่หักซ้ำ
        $this->mark($s, '2030-06-03', 'late');
        $this->assertCount(1, $this->auto($s));

        // แก้ครั้งที่ 3 เป็น "มา" > ไม่ครบรอบ คืนคะแนน
        $third->update(['status' => 'present']);
        $this->assertCount(0, $this->auto($s));
        $this->assertSame($before, $s->fresh()->behaviorScore());

        // นอกภาคเรียนไม่นับ
        foreach (['2031-01-05', '2031-01-06', '2031-01-07'] as $d) {
            $this->mark($s, $d, 'late');
        }
        $this->assertCount(0, $this->auto($s));
    }

    public function test_changing_rules_in_settings_recalculates_current_term(): void
    {
        $this->term();
        $s = Student::where('status', 'active')->first();
        $this->mark($s, '2030-07-01', 'absent');
        $this->mark($s, '2030-07-02', 'absent');
        $this->assertCount(0, $this->auto($s));

        $this->actingAs($this->admin())->post(route('settings.update'), [
            'section' => 'behavior', 'behavior_auto_absent_every' => 1, 'behavior_auto_absent_points' => 10,
            'behavior_auto_late_every' => 0, 'behavior_auto_late_points' => 5,
        ])->assertSessionHasNoErrors();
        $this->assertCount(2, $this->auto($s));

        // ปิดเกณฑ์ > คืนคะแนนทั้งหมด
        $this->post(route('settings.update'), ['section' => 'behavior', 'behavior_auto_absent_every' => 0, 'behavior_auto_absent_points' => 10,
            'behavior_auto_late_every' => 0, 'behavior_auto_late_points' => 5]);
        $this->assertCount(0, $this->auto($s));
    }

    public function test_pp8_shows_cumulative_record_for_staff_only(): void
    {
        $s = Student::where('status', 'active')->first();
        $this->actingAs($this->admin())->get(route('pp8.show', $s))->assertOk()
            ->assertSee('ระเบียนสะสม (ปพ.8)')->assertSee($s->fullName())->assertSee('การมาเรียน');
        $this->actingAs($this->admin())->get(route('pp8.classroom', $s->classroom_id))->assertOk()->assertSee($s->fullName());

        $parent = $s->guardians()->first();
        if ($parent) {
            $this->actingAs($parent)->get(route('pp8.show', $s))->assertForbidden();
        }
        $other = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($other)->get(route('pp8.classroom', $s->classroom_id))->assertForbidden();
    }

    public function test_pp2_is_issued_once_for_graduated_students_only(): void
    {
        $level = 'ม.3';
        $students = Student::whereHas('classroom', fn ($q) => $q->where('level', $level))->where('status', 'active')->limit(2)->get();
        $this->assertCount(2, $students, 'ข้อมูลตัวอย่างต้องมีนักเรียน ม.3');
        [$grad, $notYet] = [$students[0], $students[1]];
        $grad->update(['status' => 'graduated', 'left_on' => '2030-03-31', 'leave_reason' => 'จบการศึกษา']);

        $this->actingAs($this->admin())->post(route('diplomas.issue'), ['level' => $level, 'student_ids' => [$grad->id, $notYet->id]])
            ->assertRedirect()->assertSessionHas('success');
        $issue = DocumentIssue::where('type', 'pp2')->sole();
        $this->assertSame($grad->id, $issue->student_id);
        $this->assertSame('มัธยมศึกษาตอนต้น', $issue->snapshot['stage_name']);

        // ออกซ้ำ = ใช้เลขเดิม ไม่ออกเลขใหม่
        $this->post(route('diplomas.issue'), ['level' => $level, 'student_ids' => [$grad->id]]);
        $this->assertSame(1, DocumentIssue::where('type', 'pp2')->count());

        $this->get(route('diplomas.print', ['ids' => $issue->id]))->assertOk()->assertSee('กระทรวงศึกษาธิการ')->assertSee('ปพ.๒ : บ')->assertSee($grad->fullName())
            ->assertSee('เป็นผู้สำเร็จการศึกษาภาคบังคับ')->assertSee('class="num">๑', false);
        $this->get(route('diplomas.print', ['ids' => $issue->id, 'mode' => 'overlay']))->assertOk()->assertSee('พิมพ์ลงแบบฟอร์มจริง');
        $this->post(route('diplomas.issue'), ['level' => $level, 'student_ids' => [$notYet->id]])->assertSessionHasErrors('student_ids');
    }

    public function test_pp2_print_offset_is_saved_in_school_settings(): void
    {
        $this->actingAs($this->admin())->post(route('settings.update'), ['section' => 'school', 'school_name' => 'โรงเรียนทดสอบ', 'pp2_offset_x' => 3.5, 'pp2_offset_y' => -2])
            ->assertSessionHasNoErrors();
        $this->assertSame('3.5', (string) Settings::get('pp2_offset_x'));
        $this->post(route('settings.update'), ['section' => 'school', 'school_name' => 'x', 'pp2_offset_x' => 99])->assertSessionHasErrors('pp2_offset_x');
    }
}
