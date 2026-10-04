<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Course;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ชุมนุม: ตั้งชุมนุม ช่วงเปิดรับ นักเรียนเลือกเอง จำกัดจำนวน และสร้างรายวิชาของชุมนุม */
class ClubSignupTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function studentUser(Student $s, string $username): User
    {
        $u = $s->user ?? User::create(['name' => $s->fullName(), 'username' => $username, 'password' => 'secret123', 'role' => 'student', 'is_active' => true]);
        $s->update(['user_id' => $u->id]);

        return $u->fresh();
    }

    private function open(): void
    {
        Settings::set(['club_signup_from' => now()->subDay()->toDateTimeString(), 'club_signup_until' => now()->addDays(3)->toDateTimeString()]);
    }

    public function test_academics_sets_up_clubs_and_the_signup_window(): void
    {
        $teacher = User::where('username', 'teacher')->first();
        $this->actingAs($teacher)->get(route('clubs.index'))->assertOk()->assertDontSee('เพิ่มชุมนุม');
        $this->post(route('clubs.store'), ['name' => 'ชุมนุมหุ่นยนต์'])->assertForbidden();

        $this->actingAs($this->admin())->post(route('clubs.store'), ['name' => 'ชุมนุมหุ่นยนต์', 'teacher_id' => $teacher->id, 'capacity' => 2, 'levels' => ['ม.1']])->assertSessionHasNoErrors();
        $this->post(route('clubs.store'), ['name' => 'ชุมนุมหุ่นยนต์'])->assertSessionHasErrors('name');
        $club = Club::first();
        $this->assertSame(['ม.1'], $club->levels);
        $this->get(route('clubs.index'))->assertOk()->assertSee('ชุมนุมหุ่นยนต์')->assertSee('ปิดรับอยู่')->assertSee('นักเรียนที่ยังไม่ได้เลือกชุมนุม');

        $this->post(route('clubs.window'), ['club_signup_from' => now()->addDay()->toDateTimeString(), 'club_signup_until' => now()->toDateTimeString()])->assertSessionHasErrors('club_signup_until');
        $this->post(route('clubs.window'), ['club_signup_from' => now()->subHour()->toDateTimeString(), 'club_signup_until' => now()->addDay()->toDateTimeString()])->assertSessionHasNoErrors();
        $this->assertTrue(Club::signupOpen());
        $this->get(route('clubs.index'))->assertSee('กำลังเปิดรับ');

        // ครูที่ปรึกษาจัดการสมาชิกของชุมนุมตัวเองได้ ครูคนอื่นไม่ได้
        $s = Student::active()->whereHas('classroom', fn ($q) => $q->where('level', 'ม.1'))->first();
        $other = User::where('role', 'teacher')->where('id', '!=', $teacher->id)->first();
        $this->actingAs($other)->post(route('clubs.members.add', $club), ['student_id' => $s->id])->assertForbidden();
        $this->actingAs($teacher)->post(route('clubs.members.add', $club), ['student_id' => $s->id])->assertSessionHasNoErrors();
        $this->get(route('clubs.show', $club))->assertOk()->assertSee($s->fullName());
        $this->delete(route('clubs.members.remove', [$club, $s]))->assertRedirect();
        $this->assertSame(0, $club->students()->count());
    }

    public function test_students_choose_one_club_within_the_window_and_capacity(): void
    {
        $term = Term::current();
        $m1 = Student::active()->whereHas('classroom', fn ($q) => $q->where('level', 'ม.1')->where('year', $term->year))->take(3)->get();
        $m2 = Student::active()->whereHas('classroom', fn ($q) => $q->where('level', 'ม.2')->where('year', $term->year))->first();
        $robot = Club::create(['term_id' => $term->id, 'name' => 'ชุมนุมหุ่นยนต์', 'capacity' => 2, 'levels' => ['ม.1']]);
        $music = Club::create(['term_id' => $term->id, 'name' => 'ชุมนุมดนตรี']);
        [$a, $b, $c] = [$this->studentUser($m1[0], 'club-a'), $this->studentUser($m1[1], 'club-b'), $this->studentUser($m1[2], 'club-c')];

        // ยังไม่เปิดรับ: เห็นรายการแต่เลือกไม่ได้
        $this->actingAs($a)->get(route('student.clubs'))->assertOk()->assertSee('ชุมนุมหุ่นยนต์')->assertDontSee('เลือกชุมนุมนี้');
        $this->post(route('student.clubs.join', $robot))->assertSessionHasErrors('club');

        $this->open();
        $this->get(route('student.clubs'))->assertOk()->assertSee('เลือกชุมนุมนี้')->assertSee('เหลือ 2 ที่');
        $this->post(route('student.clubs.join', $robot))->assertSessionHasNoErrors();
        // เลือกซ้ำ/เลือกชุมนุมที่สองไม่ได้
        $this->post(route('student.clubs.join', $music))->assertSessionHasErrors('club');
        $this->get(route('student.clubs'))->assertSee('ชุมนุมของฉัน')->assertSee('เปลี่ยนชุมนุม');

        // คนที่สองได้ที่สุดท้าย คนที่สามเจอเต็ม
        $this->actingAs($b)->post(route('student.clubs.join', $robot))->assertSessionHasNoErrors();
        $this->actingAs($c)->get(route('student.clubs'))->assertSee('เต็ม');
        $this->post(route('student.clubs.join', $robot))->assertSessionHasErrors('club');
        $this->assertSame(2, $robot->students()->count());

        // ระดับชั้นที่ไม่รับ: ไม่เห็นชุมนุมนั้น และเลือกตรง ๆ ก็ไม่ได้
        $d = $this->studentUser($m2, 'club-d');
        $this->actingAs($d)->get(route('student.clubs'))->assertOk()->assertDontSee('ชุมนุมหุ่นยนต์')->assertSee('ชุมนุมดนตรี');
        $this->post(route('student.clubs.join', $robot))->assertSessionHasErrors('club');

        // เปลี่ยนชุมนุมได้ระหว่างเปิดรับ · ปิดรับแล้วเปลี่ยนเองไม่ได้
        $this->actingAs($a)->delete(route('student.clubs.leave'))->assertSessionHasNoErrors();
        $this->post(route('student.clubs.join', $music))->assertSessionHasNoErrors();
        $this->assertSame(1, $robot->students()->count());
        Settings::set(['club_signup_until' => now()->subMinute()->toDateTimeString()]);
        $this->delete(route('student.clubs.leave'))->assertSessionHasErrors('club');
        $this->assertSame(1, $music->students()->count());

        // ครู/ผู้ปกครองเข้าหน้าเลือกของนักเรียนไม่ได้
        $this->actingAs($this->admin())->get(route('student.clubs'))->assertForbidden();
    }

    public function test_club_course_uses_members_for_grading_and_follows_changes(): void
    {
        $term = Term::current();
        $teacher = User::where('username', 'teacher')->first();
        $students = Student::active()->whereHas('classroom', fn ($q) => $q->where('year', $term->year))->take(3)->get();
        $club = Club::create(['term_id' => $term->id, 'name' => 'ชุมนุมหุ่นยนต์', 'teacher_id' => $teacher->id]);

        $this->actingAs($this->admin())->post(route('clubs.course', $club))->assertSessionHasErrors('course'); // ยังไม่มีสมาชิก
        $this->post(route('clubs.members.add', $club), ['student_id' => $students[0]->id]);
        $this->post(route('clubs.members.add', $club), ['student_id' => $students[1]->id]);
        $this->post(route('clubs.course', $club))->assertSessionHasNoErrors();

        $course = $club->fresh()->course;
        $this->assertNotNull($course);
        $this->assertSame('ชุมนุมหุ่นยนต์', $course->label());
        $this->assertSame('club-'.$club->id, $course->variant);
        $this->assertSame($teacher->id, $course->teacher_id);
        $this->assertEqualsCanonicalizing([$students[0]->id, $students[1]->id], $course->memberIds());
        $this->assertTrue($course->subject->activity_kind === 'club');

        // เพิ่มสมาชิกทีหลัง รายชื่อในรายวิชาตามไปด้วย · ครูที่ปรึกษาเปิดสมุดคะแนนของชุมนุมได้
        $this->post(route('clubs.members.add', $club), ['student_id' => $students[2]->id]);
        $this->assertCount(3, Course::find($course->id)->memberIds());
        $this->actingAs($teacher)->get(route('gradebook.show', $course))->assertOk()->assertSee('ชุมนุมหุ่นยนต์')->assertSee($students[2]->first_name);

        // รายวิชาปกติของห้องเดียวกันยังเปิดได้ตามเดิม และชุมนุมที่มีรายวิชาแล้วลบไม่ได้
        $this->actingAs($this->admin())->delete(route('clubs.destroy', $club))->assertStatus(422);
        $this->assertSame(1, Course::where('term_id', $term->id)->where('classroom_id', $course->classroom_id)->where('subject_id', $course->subject_id)->where('variant', '')->count()
            + (int) ! Course::where('term_id', $term->id)->where('classroom_id', $course->classroom_id)->where('subject_id', $course->subject_id)->where('variant', '')->exists());
    }
}
