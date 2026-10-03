<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Score;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    public function test_every_seeded_student_has_an_enrollment_for_the_current_year(): void
    {
        $year = Term::current()->year;
        $students = Student::whereNotNull('classroom_id')->get();

        $this->assertNotEmpty($students);
        foreach ($students as $s) {
            $this->assertSame($s->classroom_id, $s->classroomIdForYear($year));
        }
    }

    public function test_changing_classroom_updates_the_same_years_enrollment(): void
    {
        $s = Student::active()->whereNotNull('classroom_id')->first();
        $other = Classroom::where('year', $s->classroom->year)->where('id', '!=', $s->classroom_id)->first();

        $s->update(['classroom_id' => $other->id, 'number' => 40]);

        $this->assertSame(1, Enrollment::where('student_id', $s->id)->count());
        $this->assertDatabaseHas('enrollments', ['student_id' => $s->id, 'classroom_id' => $other->id, 'number' => 40, 'status' => 'studying']);
    }

    public function test_promotion_keeps_past_term_grades_and_gradebook_roster(): void
    {
        $term = Term::current();
        $student = Student::findOrFail(Score::whereNotNull('score')->value('student_id'));
        $oldRoom = $student->classroom;
        $course = Course::where('term_id', $term->id)->where('classroom_id', $oldRoom->id)->first();
        $this->actingAs($this->admin())->get(route('report-card', ['student' => $student, 'term' => $term->id]))->assertOk()->assertSee($course->subject->name);

        $this->get(route('classrooms.promote.form', ['from_year' => $term->year]))->assertOk()->assertSee($oldRoom->name());
        $this->post(route('classrooms.promote'), ['from_year' => $term->year, 'graduate_levels' => ['ม.6']])
            ->assertRedirect(route('classrooms.index', ['year' => $term->year + 1]));

        $student->refresh();
        $this->assertSame($term->year + 1, $student->classroom->year);
        $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'year' => $term->year, 'classroom_id' => $oldRoom->id, 'status' => 'promoted']);
        $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'year' => $term->year + 1, 'classroom_id' => $student->classroom_id, 'status' => 'studying']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'setting.promote']);

        // ปพ.6 ของภาคเรียนเก่ายังเห็นรายวิชาและห้องเดิม · สมุดคะแนนของรายวิชาปีเก่ายังมีนักเรียนคนนี้
        $this->get(route('report-card', ['student' => $student, 'term' => $term->id]))
            ->assertOk()->assertSee($course->subject->name)->assertSee($oldRoom->name());
        $this->get(route('gradebook.show', $course))->assertOk()->assertSee($student->first_name);
    }

    public function test_retained_student_stays_in_the_same_level(): void
    {
        $year = Term::current()->year;
        $student = Student::active()->whereHas('classroom', fn ($q) => $q->where('year', $year))->first();
        $level = $student->classroom->level;

        $this->actingAs($this->admin())->post(route('classrooms.promote'), ['from_year' => $year, 'retain' => [$student->id]]);

        $student->refresh();
        $this->assertSame($level, $student->classroom->level);
        $this->assertSame($year + 1, $student->classroom->year);
        $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'year' => $year, 'status' => 'retained']);
    }

    public function test_promotion_can_be_undone_until_the_new_year_has_data(): void
    {
        $year = Term::current()->year;
        $before = Student::whereNotNull('classroom_id')->pluck('classroom_id', 'id')->all();
        $enrollments = Enrollment::count();

        $this->actingAs($this->admin())->post(route('classrooms.promote'), ['from_year' => $year, 'graduate_levels' => ['ม.1']]);
        $this->assertNotSame($before, Student::whereNotNull('classroom_id')->pluck('classroom_id', 'id')->all());

        $this->post(route('classrooms.promote.undo'), ['to_year' => $year + 1])
            ->assertRedirect(route('classrooms.index', ['year' => $year]));

        $this->assertSame($before, Student::whereNotNull('classroom_id')->pluck('classroom_id', 'id')->all());
        $this->assertSame(0, Student::where('status', 'graduated')->whereIn('id', array_keys($before))->count());
        $this->assertSame($enrollments, Enrollment::count());

        // ยกเลิกซ้ำไม่ได้ เพราะไม่มีการเลื่อนชั้นค้างอยู่แล้ว
        $this->post(route('classrooms.promote.undo'), ['to_year' => $year + 1])->assertStatus(422);
    }

    public function test_database_refuses_to_cascade_away_financial_records(): void
    {
        $invoice = Invoice::whereHas('payments')->first();
        $payments = Payment::count();

        // ต่อให้โค้ดชั้นบนพลาด ฐานข้อมูลก็ไม่ยอมให้ใบแจ้งหนี้/ใบเสร็จหายตามนักเรียน
        try {
            Student::whereKey($invoice->student_id)->delete();
            $this->fail('การลบนักเรียนที่มีใบแจ้งหนี้ควรถูกฐานข้อมูลปฏิเสธ');
        } catch (QueryException) {
            $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
            $this->assertSame($payments, Payment::count());
        }
    }
}
