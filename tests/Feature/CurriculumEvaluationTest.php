<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\PeriodAttendance;
use App\Models\Score;
use App\Models\Student;
use App\Models\StudentEvaluation;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use App\Support\Evaluation;
use App\Support\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ข้อมูลพื้นฐานของเอกสาร ปพ.: ผลพิเศษ/แก้ตัว · กิจกรรม ผ/มผ · คุณลักษณะ/อ่านคิดเขียน · ข้อมูลหัว ปพ.1 */
class CurriculumEvaluationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function teacher(): User
    {
        return User::where('username', 'teacher')->first();
    }

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    /** วิชาคณิตที่ครูตัวอย่างสอน ม.1/1 ภาคปัจจุบัน */
    private function mathCourse(): Course
    {
        return Course::where('teacher_id', $this->teacher()->id)
            ->whereHas('term', fn ($q) => $q->where('is_current', true))
            ->whereHas('subject', fn ($q) => $q->where('type', '!=', 'activity'))
            ->with('assessments')->orderBy('id')->first();
    }

    /** กรอกคะแนนทุกช่องให้ได้ร้อยละที่ต้องการ */
    private function scoreAll(Course $course, Student $student, float $percent): void
    {
        foreach ($course->assessments as $a) {
            Score::updateOrCreate(['assessment_id' => $a->id, 'student_id' => $student->id], ['score' => $a->max_score * $percent / 100]);
        }
    }

    public function test_grade_rules(): void
    {
        $this->assertSame(['1'], Grade::remedialOptions('0'));
        $this->assertSame(['0', '1'], Grade::remedialOptions('มส'));
        $this->assertContains('4', Grade::remedialOptions('ร'));
        $this->assertSame(['ผ'], Grade::remedialOptions('มผ'));
        $this->assertSame([], Grade::remedialOptions('3'));

        $this->assertTrue(Grade::passed('1'));
        $this->assertTrue(Grade::passed('ผ'));
        $this->assertFalse(Grade::passed('0'));
        $this->assertFalse(Grade::passed('ร'));
        $this->assertFalse(Grade::passed('มผ'));

        // ร/มส นับเป็น 0 ในตัวหาร · ผ ไม่นับ
        $this->assertSame(2.0, Grade::gpa([['grade' => '4', 'credit' => 1.0], ['grade' => 'ร', 'credit' => 1.0], ['grade' => 'ผ', 'credit' => 0.0]]));
        $this->assertSame('success', Grade::color('ผ'));
        $this->assertSame('danger', Grade::color('มส'));
    }

    public function test_trait_summary_rules(): void
    {
        $all = fn (int $v) => array_fill_keys(array_keys(Evaluation::TRAITS), $v);

        $this->assertNull(Evaluation::summarize([1 => 3, 2 => 3]));                         // ยังไม่ครบ 8 ข้อ
        $this->assertSame(3, Evaluation::summarize(array_combine(range(1, 8), [3, 3, 3, 3, 3, 2, 2, 2])));  // ดีเยี่ยม 5 ข้อ
        $this->assertSame(2, Evaluation::summarize(array_combine(range(1, 8), [3, 3, 3, 3, 2, 2, 2, 2])));  // ดีเยี่ยม 4 ข้อ
        $this->assertSame(2, Evaluation::summarize($all(2)));
        $this->assertSame(2, Evaluation::summarize(array_combine(range(1, 8), [3, 3, 3, 3, 3, 3, 3, 1]))); // ดีขึ้นไป 7 ข้อ มีผ่าน 1
        $this->assertSame(1, Evaluation::summarize(array_combine(range(1, 8), [3, 3, 3, 3, 1, 1, 1, 1]))); // ดีขึ้นไป 4 ข้อ
        $this->assertSame(1, Evaluation::summarize($all(1)));
        $this->assertSame(0, Evaluation::summarize(array_combine(range(1, 8), [3, 3, 3, 3, 3, 3, 3, 0])));
    }

    public function test_activity_course_is_graded_pass_or_fail(): void
    {
        $course = Course::whereHas('subject', fn ($q) => $q->where('type', 'activity'))
            ->where('teacher_id', $this->teacher()->id)->whereHas('term', fn ($q) => $q->where('is_current', true))
            ->with('assessments')->first();
        [$a, $b] = $course->classroom->students()->take(2)->get()->all();
        $item = $course->assessments->first();

        $res = $this->actingAs($this->teacher())->postJson("/courses/{$course->id}/grades", ['scores' => [$a->id => [$item->id => '80'], $b->id => [$item->id => '30']]]);
        $res->assertOk()->assertJsonPath("results.{$a->id}.grade", 'ผ')->assertJsonPath("results.{$b->id}.grade", 'มผ');

        // มผ ซ่อมเป็น ผ ได้ แต่ใส่เกรดตัวเลขไม่ได้
        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/{$b->id}", ['remedial_grade' => '2'])->assertSessionHasErrors('remedial_grade');
        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/{$b->id}", ['remedial_grade' => 'ผ'])->assertSessionHasNoErrors();
        $this->assertSame('ผ', $course->fresh()->results()[$b->id]['grade']);
        $this->assertSame('มผ', $course->fresh()->results()[$b->id]['original']);

        $this->actingAs($this->teacher())->get("/courses/{$course->id}/grades")->assertOk()->assertSee('data-activity="1"', false)->assertSee('มผ (ไม่ผ่าน)');
    }

    public function test_special_grade_and_remedial_caps(): void
    {
        $course = $this->mathCourse();
        [$a, $b] = $course->classroom->students()->take(2)->get()->all();
        $this->scoreAll($course, $a, 90);
        $this->scoreAll($course, $b, 20);
        $this->assertSame('4', $course->results()[$a->id]['grade']);
        $this->assertSame('0', $course->results()[$b->id]['grade']);

        // ร แทนผลจากคะแนน แล้วแก้ ร ได้ผลตามจริง
        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/{$a->id}", ['special' => 'ร'])->assertSessionHasNoErrors();
        $this->assertSame('ร', $course->results()[$a->id]['grade']);
        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/{$a->id}", ['special' => 'ร', 'remedial_grade' => '3.5'])->assertSessionHasNoErrors();
        $r = $course->results()[$a->id];
        $this->assertSame(['ร', '3.5'], [$r['original'], $r['grade']]);
        $this->assertNotNull(CourseResult::where(['course_id' => $course->id, 'student_id' => $a->id])->value('remedied_on'));

        // 0 แก้ตัวได้ไม่เกิน 1
        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/{$b->id}", ['remedial_grade' => '2'])->assertSessionHasErrors('remedial_grade');
        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/{$b->id}", ['remedial_grade' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('1', $course->results()[$b->id]['grade']);

        // เกรดปกติไม่ต้องแก้ตัว · ผลพิเศษของกิจกรรมใช้กับวิชาทั่วไปไม่ได้
        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/{$a->id}", ['remedial_grade' => '4'])->assertSessionHasErrors('remedial_grade');
        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/{$a->id}", ['special' => 'มผ'])->assertSessionHasErrors('special');

        // ล้างทุกช่อง = ลบผลพิเศษ กลับไปใช้ผลจากคะแนน
        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/{$b->id}", [])->assertSessionHasNoErrors();
        $this->assertSame('0', $course->results()[$b->id]['grade']);
        $this->assertFalse(CourseResult::where(['course_id' => $course->id, 'student_id' => $b->id])->exists());

        // มส ที่ยังไม่มีคะแนนเลยก็ต้องขึ้นในผลการเรียน
        $c = $course->classroom->students()->skip(2)->first();
        Score::where('student_id', $c->id)->whereIn('assessment_id', $course->assessments->pluck('id'))->delete();
        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/{$c->id}", ['special' => 'มส']);
        $this->assertSame('มส', $course->results()[$c->id]['grade']);
        $this->assertNull($course->results()[$c->id]['total']);
    }

    public function test_locked_course_allows_remedial_but_not_special_for_teacher(): void
    {
        $course = $this->mathCourse();
        $student = $course->classroom->students()->first();
        $this->scoreAll($course, $student, 10);
        $course->update(['locked' => true]);

        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/{$student->id}", ['special' => 'มส'])->assertForbidden();
        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/{$student->id}", ['remedial_grade' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('1', $course->results()[$student->id]['grade']);
        // ผู้ดูแลเปลี่ยนผลพิเศษได้แม้ล็อก
        $this->actingAs($this->admin())->post("/courses/{$course->id}/outcomes/{$student->id}", ['special' => 'มส'])->assertSessionHasNoErrors();
        $this->assertSame('มส', $course->results()[$student->id]['original']);
    }

    public function test_teacher_cannot_record_outcome_in_other_teachers_course(): void
    {
        $other = Course::where('teacher_id', '!=', $this->teacher()->id)->first();
        $student = $other->classroom->students()->first();
        $this->actingAs($this->teacher())->post("/courses/{$other->id}/outcomes/{$student->id}", ['special' => 'ร'])->assertForbidden();
        $this->actingAs($this->teacher())->post("/courses/{$other->id}/outcomes/ms")->assertForbidden();
    }

    public function test_apply_ms_marks_students_below_attendance_threshold(): void
    {
        $course = $this->mathCourse();
        $students = $course->classroom->students()->get();
        $target = $students->first();
        PeriodAttendance::where('course_id', $course->id)->delete();
        for ($i = 0; $i < 10; $i++) {
            foreach ($students as $s) {
                PeriodAttendance::create(['course_id' => $course->id, 'student_id' => $s->id, 'date' => today()->subDays(30 + $i)->toDateString(), 'period' => 1,
                    'status' => $s->is($target) && $i < 3 ? 'absent' : 'present']);
            }
        }

        $this->actingAs($this->teacher())->get("/courses/{$course->id}/grades")->assertSee('ตั้ง มส. ให้ทั้งหมด');
        $this->actingAs($this->teacher())->post("/courses/{$course->id}/outcomes/ms")->assertRedirect();
        $this->assertSame(['มส'], CourseResult::where('course_id', $course->id)->pluck('special')->all());
        $this->assertSame($target->id, CourseResult::where('course_id', $course->id)->value('student_id'));
        $this->actingAs($this->teacher())->get("/courses/{$course->id}/grades")->assertDontSee('ตั้ง มส. ให้ทั้งหมด');
    }

    public function test_homeroom_teacher_records_evaluations_shown_on_report_card(): void
    {
        $teacher = $this->teacher();
        $classroom = $teacher->myClassrooms()->first();
        $term = Term::current();
        [$a, $b] = $classroom->students()->take(2)->get()->all();

        $this->actingAs($teacher)->get('/evaluations')->assertOk()->assertSee('รักชาติ ศาสน์ กษัตริย์')->assertSee($a->first_name);

        $this->actingAs($teacher)->post("/evaluations/{$classroom->id}", ['term_id' => $term->id, 'eval' => [
            $a->id => ['t' => array_fill_keys(range(1, 8), '3'), 'rtw' => '2'],
            $b->id => ['t' => array_fill_keys(range(1, 8), ''), 'rtw' => ''],
        ]])->assertRedirect()->assertSessionHas('success');

        $e = StudentEvaluation::where(['term_id' => $term->id, 'student_id' => $a->id])->first();
        $this->assertSame(3, $e->traitsSummary());
        $this->assertSame(2, $e->rtw);
        $this->assertFalse(StudentEvaluation::where(['term_id' => $term->id, 'student_id' => $b->id])->exists());

        // ค่าเกินช่วงไม่รับ
        $this->actingAs($teacher)->post("/evaluations/{$classroom->id}", ['term_id' => $term->id, 'eval' => [$a->id => ['rtw' => '5']]])
            ->assertSessionHasErrors();

        // ผู้ปกครองเห็นในสมุดรายงานผล
        $parent = $a->guardians()->first();
        $this->actingAs($parent)->get("/report-card/{$a->id}")->assertOk()->assertSee('คุณลักษณะอันพึงประสงค์')->assertSee('ดีเยี่ยม');

        // ครูที่ไม่ใช่ครูประจำชั้นบันทึกไม่ได้
        $other = User::where('role', 'teacher')->get()->first(fn ($u) => ! $classroom->isManagedBy($u));
        $this->actingAs($other)->post("/evaluations/{$classroom->id}", ['term_id' => $term->id, 'eval' => []])->assertForbidden();
    }

    public function test_student_pp1_fields_and_subject_hours(): void
    {
        $admin = $this->admin();
        $student = Student::first();
        $payload = $student->only(['student_code', 'first_name', 'last_name', 'status']) + [
            'nationality' => 'ไทย', 'religion' => 'อิสลาม', 'father_name' => 'นายสมชาย ใจดี', 'mother_name' => 'นางสมศรี ใจดี',
            'admitted_on' => '2024-05-16', 'previous_school' => 'โรงเรียนบ้านโคก', 'previous_level' => 'ป.6', 'left_on' => '2024-01-01',
        ];
        // วันออกก่อนวันเข้าเรียนไม่ได้
        $this->actingAs($admin)->put("/students/{$student->id}", $payload)->assertSessionHasErrors('left_on');
        $this->actingAs($admin)->put("/students/{$student->id}", ['left_on' => null] + $payload)->assertSessionHasNoErrors();
        $student->refresh();
        $this->assertSame(['อิสลาม', 'นายสมชาย ใจดี', '2024-05-16'], [$student->religion, $student->father_name, $student->admitted_on->toDateString()]);
        $this->actingAs($admin)->get("/students/{$student->id}")->assertSee('โรงเรียนบ้านโคก')->assertSee('นางสมศรี ใจดี');

        $this->actingAs($admin)->post('/subjects', ['code' => 'ก99901', 'name' => 'ชุมนุมดนตรี', 'credit' => 0, 'hours' => 20, 'type' => 'activity'])
            ->assertSessionHasErrors('activity_kind');
        $this->actingAs($admin)->post('/subjects', ['code' => 'ก99901', 'name' => 'ชุมนุมดนตรี', 'credit' => 0, 'hours' => 20, 'type' => 'activity', 'activity_kind' => 'club'])
            ->assertSessionHasNoErrors();
        $this->assertSame(['activity_kind' => 'club', 'hours' => 20], Subject::where('code', 'ก99901')->first()->only(['activity_kind', 'hours']));
        // วิชาทั่วไปไม่เก็บประเภทกิจกรรม
        $this->actingAs($admin)->post('/subjects', ['code' => 'ค99901', 'name' => 'คณิตเสริม', 'credit' => 1, 'hours' => 40, 'type' => 'extra', 'activity_kind' => 'club']);
        $this->assertNull(Subject::where('code', 'ค99901')->value('activity_kind'));
        $this->actingAs($admin)->get('/subjects')->assertOk()->assertSee('ชุมนุม/ชมรม');
    }

    public function test_transcript_uses_final_grades_and_earned_credits(): void
    {
        // ภาคที่แล้วของ ม.1/1 มีกิจกรรม (คนสุดท้ายซ่อม มผ → ผ)
        $student = Student::whereHas('classroom', fn ($q) => $q->where('level', 'ม.1')->where('room', 1))->orderByDesc('number')->first();
        $this->actingAs($this->admin())->get("/transcript/{$student->id}")->assertOk()->assertSee('ชุมนุม')->assertSee('ผ');

        $course = Course::whereHas('subject', fn ($q) => $q->where('type', '!=', 'activity'))
            ->whereHas('term', fn ($q) => $q->where('year', 2568))->with('subject')->first();
        Score::where('student_id', $student->id)->whereIn('assessment_id', $course->assessments()->pluck('id'))->update(['score' => 10]);
        $credit = (float) $course->subject->credit;

        $before = $this->actingAs($this->admin())->get("/transcript/{$student->id}")->viewData('credits');
        CourseResult::create(['course_id' => $course->id, 'student_id' => $student->id, 'remedial_grade' => '1']);
        $after = $this->actingAs($this->admin())->get("/transcript/{$student->id}")->viewData('credits');
        $this->assertEqualsWithDelta($before + $credit, $after, 0.001);
    }

    public function test_admission_enroll_copies_previous_school(): void
    {
        $admission = Admission::create([
            'app_no' => 'T0001', 'year' => 2569, 'level' => 'ม.1', 'prefix' => 'เด็กหญิง', 'first_name' => 'มะลิ', 'last_name' => 'ทดสอบ',
            'gender' => 'F', 'previous_school' => 'โรงเรียนวัดทดสอบ', 'parent_name' => 'นางมาลี ทดสอบ', 'parent_phone' => '0899999999',
            'relation' => 'มารดา', 'status' => 'accepted',
        ]);
        $this->actingAs($this->admin())->post("/admissions/{$admission->id}/enroll", ['student_code' => '99999'])->assertRedirect();
        $student = Student::where('student_code', '99999')->first();
        $this->assertSame('โรงเรียนวัดทดสอบ', $student->previous_school);
        $this->assertSame('นางมาลี ทดสอบ', $student->mother_name);
        $this->assertTrue($student->admitted_on->isToday());
    }
}
