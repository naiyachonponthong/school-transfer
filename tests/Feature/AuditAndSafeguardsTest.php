<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Invoice;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ด่านกันการลบ + ประวัติการแก้ไข (audit log) */
class AuditAndSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function teacher(): User
    {
        return User::where('username', 'teacher')->first();
    }

    public function test_course_with_scores_cannot_be_deleted_but_empty_course_can(): void
    {
        $scored = Course::whereHas('assessments.scores')->first();
        $this->actingAs($this->admin())->delete("/courses/{$scored->id}")->assertSessionHasErrors('course');
        $this->assertModelExists($scored);

        $empty = Course::create(['term_id' => Term::current()->id, 'classroom_id' => Classroom::first()->id, 'subject_id' => Subject::create(['code' => 'Z99999', 'name' => 'เปิดผิด', 'credit' => 1, 'type' => 'extra'])->id]);
        $this->actingAs($this->admin())->delete("/courses/{$empty->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($empty);
        $this->assertTrue(AuditLog::where('action', 'course.delete')->where('description', 'like', '%Z99999%')->exists());
    }

    public function test_student_with_records_cannot_be_deleted(): void
    {
        $student = Student::whereHas('scores')->first();
        $this->actingAs($this->admin())->delete("/students/{$student->id}")->assertSessionHasErrors('student');
        $message = session('errors')->first('student');
        $this->assertStringContainsString('คะแนน', $message);
        $this->assertStringContainsString('ย้ายโรงเรียน', $message);
        $this->assertModelExists($student);

        $fresh = Student::create(['student_code' => 'NEW01', 'first_name' => 'เพิ่ม', 'last_name' => 'ผิด', 'classroom_id' => Classroom::first()->id]);
        $this->actingAs($this->admin())->delete("/students/{$fresh->id}")->assertRedirect(route('students.index'));
        $this->assertModelMissing($fresh);
        $this->assertSame('student.delete', AuditLog::latest('id')->value('action'));
    }

    public function test_score_changes_are_logged_only_on_locked_courses(): void
    {
        $course = Course::where('teacher_id', $this->teacher()->id)->with('assessments')->orderBy('id')->first();
        $student = $course->classroom->students()->first();
        $a = $course->assessments->first();
        $old = Score::where(['assessment_id' => $a->id, 'student_id' => $student->id])->value('score');

        // ยังไม่ล็อก: ครูกรอกปกติ ไม่ต้องบันทึกประวัติ
        $this->actingAs($this->teacher())->postJson("/courses/{$course->id}/grades", ['scores' => [$student->id => [$a->id => '5']]])->assertOk();
        $this->assertFalse(AuditLog::where('action', 'grade.locked_edit')->exists());

        // ล็อก: ผู้ดูแลแก้ → บันทึกค่าเดิม/ใหม่ของแต่ละช่อง
        $this->actingAs($this->admin())->put("/courses/{$course->id}", ['teacher_id' => $course->teacher_id, 'locked' => 1]);
        $this->assertSame('course.update', AuditLog::latest('id')->value('action'));
        $this->actingAs($this->admin())->postJson("/courses/{$course->id}/grades", ['scores' => [$student->id => [$a->id => '7']]])->assertOk();
        $log = AuditLog::where('action', 'grade.locked_edit')->first();
        $this->assertNotNull($log);
        $this->assertSame([['student' => $student->student_code, 'assessment' => $a->name, 'old' => 5, 'new' => 7]], $log->changes['cells']);
        $this->assertSame($this->admin()->name, $log->user_name);
        $this->assertNotNull($old);
    }

    public function test_outcome_and_finance_actions_are_logged(): void
    {
        $course = Course::where('teacher_id', $this->teacher()->id)->whereHas('subject', fn ($q) => $q->where('type', 'basic'))->orderBy('id')->first();
        $student = $course->classroom->students()->first();
        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/{$student->id}", ['special' => 'ร']);
        $log = AuditLog::where('action', 'grade.outcome')->first();
        $this->assertSame(['special' => 'ร', 'remedial_grade' => null], $log->changes['after']);
        $this->assertSame('Student', $log->subject_type);

        $invoice = Invoice::where('status', 'unpaid')->first();
        $this->actingAs($this->admin())->post("/invoices/{$invoice->id}/payments", ['amount' => 100, 'method' => 'cash'])->assertRedirect();
        $this->assertTrue(AuditLog::where('action', 'finance.payment')->where('description', 'like', '%100.00 บาท%')->exists());

        $unpaid = Invoice::where('status', 'unpaid')->where('paid', 0)->first();
        $this->actingAs($this->admin())->post("/invoices/{$unpaid->id}/void");
        $this->assertTrue(AuditLog::where('action', 'finance.void')->where('subject_id', $unpaid->id)->exists());
    }

    public function test_user_and_setting_changes_are_logged_without_secrets(): void
    {
        $teacher = $this->teacher();
        $this->actingAs($this->admin())->put("/users/{$teacher->id}", [
            'name' => $teacher->name, 'username' => $teacher->username, 'role' => 'admin', 'is_active' => 1, 'password' => 'newpass123',
        ])->assertRedirect();
        $log = AuditLog::where('action', 'user.update')->first();
        $this->assertSame(['teacher', 'admin'], $log->changes['role']);
        $this->assertSame(['(เดิม)', '(ตั้งใหม่)'], $log->changes['password']);
        $this->assertStringNotContainsString('newpass123', json_encode($log->changes, JSON_UNESCAPED_UNICODE));

        $settings = Settings::all();
        $this->actingAs($this->admin())->post('/settings', array_merge($settings, [
            'school_name' => 'โรงเรียนใหม่', 'line_channel_token' => 'SECRET-TOKEN', 'theme_color' => $settings['theme_color'],
        ]))->assertRedirect();
        $log = AuditLog::where('action', 'setting.update')->first();
        $this->assertSame([$settings['school_name'], 'โรงเรียนใหม่'], $log->changes['school_name']);
        $this->assertSame(['***', '***'], $log->changes['line_channel_token']);
        $this->assertStringNotContainsString('SECRET-TOKEN', json_encode($log->changes));
    }

    public function test_audit_page_is_admin_only_and_filters(): void
    {
        $student = Student::first();
        $this->actingAs($this->admin())->put("/students/{$student->id}", $student->only(['student_code', 'first_name', 'last_name', 'status']) + ['religion' => 'คริสต์']);
        $this->actingAs($this->admin())->post('/students/'.Student::skip(1)->first()->id.'/certificate', ['purpose' => 'ศึกษาต่อ']);

        $this->actingAs($this->admin())->get('/audit')->assertOk()->assertSee('ประวัติการแก้ไข')->assertSee('ศาสนา')->assertSee('ออก ปพ.7');
        $this->actingAs($this->admin())->get('/audit?group=document')->assertOk()->assertSee('ออก ปพ.7')->assertDontSee('แก้ข้อมูลนักเรียน');
        $this->actingAs($this->admin())->get("/audit?subject=Student:{$student->id}")->assertOk()->assertSee('แก้ข้อมูลนักเรียน')->assertDontSee('ออก ปพ.7');
        $this->actingAs($this->admin())->get("/students/{$student->id}")->assertSee(route('audit.index', ['subject' => 'Student:'.$student->id]), false);
        $this->actingAs($this->teacher())->get('/audit')->assertForbidden();
    }
}
