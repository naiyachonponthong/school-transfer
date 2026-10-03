<?php

namespace Tests\Feature;

use App\Http\Controllers\StudentController;
use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Role;
use App\Models\Score;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectivesAndGradeApprovalTest extends TestCase
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

    private function homeroom(): Classroom
    {
        return Classroom::where('homeroom_teacher_id', $this->teacher()->id)->first();
    }

    private function course(Classroom $room, ?int $teacherId = null): Course
    {
        $subject = Subject::create(['code' => 'E'.uniqid(), 'name' => 'วิชาเลือก '.uniqid(), 'credit' => 1, 'type' => 'extra']);
        $course = Course::create(['term_id' => Term::current()->id, 'classroom_id' => $room->id, 'subject_id' => $subject->id, 'teacher_id' => $teacherId]);
        Assessment::create(['course_id' => $course->id, 'name' => 'รวม', 'max_score' => 100, 'sort' => 1]);

        return $course;
    }

    /* ---------------- วิชาเลือก / ชุมนุม ---------------- */

    public function test_course_without_member_list_still_means_the_whole_classroom(): void
    {
        $room = $this->homeroom();
        $course = $this->course($room);

        $this->assertSame($room->students()->pluck('id')->sort()->values()->all(), $course->students()->pluck('students.id')->sort()->values()->all());
        $this->assertTrue($course->includesStudent($room->students()->first(), $room->id));
    }

    public function test_elective_with_members_across_classrooms(): void
    {
        $room = $this->homeroom();
        $otherRoom = Classroom::currentYear()->where('id', '!=', $room->id)->whereHas('students')->first();
        [$in, $out] = $room->students()->limit(2)->get()->all();
        $guest = $otherRoom->students()->first();
        $course = $this->course($room, $this->teacher()->id);

        $this->actingAs($this->admin())->get(route('courses.members', $course))->assertOk()->assertSee('รายชื่อผู้เรียน');
        $this->put(route('courses.members.update', $course), ['student_ids' => [$in->id, $guest->id]])->assertRedirect();
        $course = Course::find($course->id);

        $this->assertEqualsCanonicalizing([$in->id, $guest->id], $course->students()->pluck('students.id')->all());
        $this->assertTrue($course->includesStudent($guest, $guest->classroom_id));
        $this->assertFalse($course->includesStudent($out, $out->classroom_id));

        // สมุดคะแนนเห็นเฉพาะสมาชิก และกรอกคะแนนให้คนนอกรายชื่อไม่ได้
        $a = $course->assessments()->first();
        $this->get(route('gradebook.show', $course))->assertOk()->assertSee($guest->first_name)->assertDontSee($out->fullName());
        $this->post(route('gradebook.save', $course), ['scores' => [$guest->id => [$a->id => 88], $out->id => [$a->id => 50]]]);
        $this->assertDatabaseHas('scores', ['assessment_id' => $a->id, 'student_id' => $guest->id]);
        $this->assertDatabaseMissing('scores', ['assessment_id' => $a->id, 'student_id' => $out->id]);

        // ผลการเรียนของนักเรียนห้องอื่นมีวิชานี้ ส่วนคนในห้องหลักที่ไม่ได้ลงไม่มี
        $term = Term::current();
        $has = fn (Student $s) => StudentController::gradesFor($s, $term)->contains(fn ($g) => $g['course']->id === $course->id);
        $this->assertTrue($has($guest));
        $this->assertTrue($has($in));
        $this->assertFalse($has($out));

        // เช็คชื่อรายคาบใช้รายชื่อเดียวกัน
        $this->get(route('period-attendance.sheet', ['course' => $course, 'period' => 1]))->assertOk()->assertSee($guest->first_name);
        $course->update(['locked' => true]);

        // ล็อกแล้วแก้รายชื่อไม่ได้ · ครูทั่วไปแก้รายชื่อไม่ได้
        $this->put(route('courses.members.update', $course), ['student_ids' => []])->assertStatus(422);
        $this->actingAs($this->teacher())->get(route('courses.members', $course))->assertForbidden();
    }

    public function test_clearing_members_returns_to_whole_classroom(): void
    {
        $room = $this->homeroom();
        $course = $this->course($room);
        $course->members()->sync([$room->students()->first()->id]);

        $this->actingAs($this->admin())->put(route('courses.members.update', $course), [])->assertRedirect();

        $this->assertSame($room->students()->count(), Course::find($course->id)->students()->count());
    }

    /* ---------------- ส่ง / อนุมัติผลการเรียน ---------------- */

    public function test_teacher_submits_academics_returns_then_approves_and_locks(): void
    {
        $teacher = $this->teacher();
        $room = $this->homeroom();
        $course = $this->course($room, $teacher->id);
        $a = $course->assessments()->first();
        $s = $room->students()->first();
        Score::create(['assessment_id' => $a->id, 'student_id' => $s->id, 'score' => 70]);

        $this->actingAs($teacher)->get(route('gradebook.show', $course))->assertOk()->assertSee('ส่งผลการเรียน');
        $this->post(route('courses.submit', $course))->assertRedirect();
        $this->assertTrue($course->fresh()->isSubmitted());

        // ส่งแล้วครูแก้คะแนนไม่ได้ และส่งซ้ำไม่ได้
        $this->post(route('gradebook.save', $course), ['scores' => [$s->id => [$a->id => 99]]])->assertForbidden();
        $this->post(route('courses.submit', $course))->assertStatus(422);
        $this->get(route('courses.approvals'))->assertForbidden();

        // ฝ่ายวิชาการ (ไม่ใช่ admin) ตีกลับ
        $academic = User::where('role', 'teacher')->where('id', '!=', $teacher->id)->first();
        $academic->roles()->sync(Role::where('key', 'academic')->pluck('id'));
        $this->actingAs($academic)->get(route('courses.approvals'))->assertOk()->assertSee($course->subject->name);
        $this->post(route('courses.return', $course), [])->assertSessionHasErrors('return_note');
        $this->post(route('courses.return', $course), ['return_note' => 'คะแนนไม่ครบ'])->assertRedirect();
        $this->assertFalse($course->fresh()->isSubmitted());

        // ครูเห็นเหตุผล แก้ไขได้ แล้วส่งใหม่
        $this->actingAs($teacher)->get(route('gradebook.show', $course))->assertSee('คะแนนไม่ครบ');
        $this->post(route('gradebook.save', $course), ['scores' => [$s->id => [$a->id => 81]]])->assertRedirect();
        $this->post(route('courses.submit', $course))->assertRedirect();

        // อนุมัติ = ล็อก + เก็บผล
        $this->actingAs($academic)->post(route('courses.approve', $course))->assertRedirect();
        $course->refresh();
        $this->assertTrue($course->locked);
        $this->assertSame($academic->id, $course->approved_by);
        $this->assertSame('4', $course->results()[$s->id]['grade']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'course.approve']);
        $this->post(route('courses.approve', $course))->assertStatus(422);
    }

    public function test_teacher_cannot_submit_someone_elses_course(): void
    {
        $other = User::where('role', 'teacher')->where('id', '!=', $this->teacher()->id)->first();
        $course = $this->course($this->homeroom(), $other->id);

        $this->actingAs($this->teacher())->post(route('courses.submit', $course))->assertForbidden();
    }

    /* ---------------- วันประกาศผล ---------------- */

    public function test_parents_and_students_do_not_see_grades_before_the_announcement_date(): void
    {
        $term = Term::current();
        $parent = User::where('phone', '0812345678')->first();
        $child = $parent->children()->first();

        $this->actingAs($parent)->get(route('parent.child', ['student' => $child, 'tab' => 'grades']))->assertOk()->assertDontSee('โรงเรียนจะประกาศผลการเรียน');
        $this->get(route('report-card', $child))->assertOk();

        $this->actingAs($this->admin())->put(route('terms.update', $term), ['results_announce_on' => today()->addDays(5)->toDateString()])->assertRedirect();
        Term::flushCurrent();

        $this->actingAs($parent)->get(route('parent.child', ['student' => $child, 'tab' => 'grades']))->assertOk()->assertSee('โรงเรียนจะประกาศผลการเรียน');
        $this->get(route('report-card', $child))->assertForbidden();

        // บุคลากรเห็นได้ตลอด และถึงวันประกาศแล้วผู้ปกครองเห็น
        $this->actingAs($this->teacher())->get(route('report-card', $child))->assertOk();
        $this->travel(6)->days();
        $this->actingAs($parent)->get(route('report-card', $child))->assertOk();
    }
}
