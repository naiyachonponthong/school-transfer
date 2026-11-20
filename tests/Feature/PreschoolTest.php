<?php

namespace Tests\Feature;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\PreschoolAssessment;
use App\Models\PreschoolDailyLog;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use App\Support\CumulativeRecord;
use App\Support\Preschool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** ปฐมวัย: ประเมินพัฒนาการ 12 มาตรฐาน บันทึกประจำวัน ปฐ.03 และ ปพ.8 */
class PreschoolTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Term::flushCurrent(); // ภาคปัจจุบันถูกจำไว้แบบ static ข้ามเทสต์
    }

    private Term $term;

    private Classroom $room;

    private User $teacher;

    /** ห้อง อ.1/1 ของปีปัจจุบัน ย้ายนักเรียนตัวอย่าง 3 คนเข้ามา */
    private function kindergarten(): array
    {
        Term::flushCurrent();
        $this->term = Term::current();
        $this->teacher = User::where('username', 'teacher')->first();
        $this->room = Classroom::create(['year' => $this->term->year, 'level' => 'อ.1', 'room' => 1, 'homeroom_teacher_id' => $this->teacher->id]);
        $kids = Student::where('status', 'active')->orderBy('id')->take(3)->get();
        foreach ($kids as $i => $s) {
            $s->update(['classroom_id' => $this->room->id, 'number' => $i + 1]);
            Enrollment::updateOrCreate(['student_id' => $s->id, 'year' => $this->term->year], ['classroom_id' => $this->room->id, 'number' => $i + 1, 'status' => 'active']);
        }

        return $kids->all();
    }

    public function test_domain_summary_rules(): void
    {
        $this->assertSame(3, Preschool::summarize([3, 3]));
        $this->assertSame(3, Preschool::summarize([3, 2]));      // 2.5
        $this->assertSame(2, Preschool::summarize([3, 2, 1, 2]));
        $this->assertSame(1, Preschool::summarize([1, 2, 1]));
        $this->assertNull(Preschool::summarize([3, null]));
        $this->assertSame(['body' => 3, 'mind' => null, 'social' => null, 'intellect' => null], Preschool::domainSummary([1 => 3, 2 => 3]));
    }

    public function test_menu_only_when_kindergarten_rooms_exist(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->get('/menu')->assertDontSee(route('preschool.index'));
        $this->kindergarten();
        $this->actingAs($admin)->get('/menu')->assertSee(route('preschool.index'));
    }

    public function test_homeroom_teacher_records_assessment_and_parent_sees_pp03(): void
    {
        [$a, $b] = $this->kindergarten();
        $this->actingAs($this->teacher)->get(route('preschool.index'))->assertOk()->assertSee('อ.1/1')->assertSee($a->first_name);

        $levels = array_fill_keys(range(1, 12), '3');
        $levels[9] = '1';
        $levels[10] = '1';
        $this->post(route('preschool.save', $this->room), ['term_id' => $this->term->id, 'ps' => [
            $a->id => ['l' => $levels, 'comment' => 'ร่าเริง ช่วยเหลือเพื่อน'],
            $b->id => ['l' => array_fill_keys(range(1, 12), ''), 'comment' => ''],
        ]])->assertRedirect()->assertSessionHas('success');

        $pa = PreschoolAssessment::where(['term_id' => $this->term->id, 'student_id' => $a->id])->first();
        $this->assertSame(['body' => 3, 'mind' => 3, 'social' => 3, 'intellect' => 2], $pa->domains());
        $this->assertFalse(PreschoolAssessment::where('student_id', $b->id)->exists());

        $this->post(route('preschool.save', $this->room), ['term_id' => $this->term->id, 'ps' => [$a->id => ['l' => [1 => '4']]]])->assertSessionHasErrors();

        // ผู้ปกครองเปิดสมุดรายงาน ได้ ปฐ.03 แทน ปพ.6
        $parent = $a->guardians()->first();
        $this->actingAs($parent)->get(route('report-card', $a))->assertOk()
            ->assertSee('ปฐ.03')->assertDontSee('ปพ.6')->assertSee('ร่าเริง ช่วยเหลือเพื่อน')->assertSee(Preschool::STANDARDS[12]);

        // ครูพิมพ์ทั้งห้อง
        $this->actingAs($this->teacher)->get(route('preschool.report', [$this->room, 'term' => $this->term->id]))->assertOk()->assertSee($b->first_name);

        // ปพ.8 แสดงพัฒนาการ 4 ด้าน
        $row = CumulativeRecord::for($a->fresh())['rows']->firstWhere('term.id', $this->term->id);
        $this->assertSame(2, $row['preschool']['intellect']);

        // ครูคนอื่นบันทึกไม่ได้
        $other = User::where('role', 'teacher')->get()->first(fn ($u) => ! $this->room->isManagedBy($u));
        $this->actingAs($other)->post(route('preschool.save', $this->room), ['term_id' => $this->term->id])->assertForbidden();
        $this->actingAs($parent)->get(route('preschool.report', $this->room))->assertForbidden();
    }

    public function test_daily_log_saves_whole_class(): void
    {
        [$a, $b, $c] = $this->kindergarten();
        $date = today()->toDateString();
        $this->actingAs($this->teacher)->get(route('preschool.daily'))->assertOk()->assertSee('ดื่มนม');

        $this->post(route('preschool.daily.save', $this->room), ['date' => $date, 'log' => [
            $a->id => ['milk' => '1', 'meal' => '1', 'brush' => '1', 'nap' => '1'],
            $b->id => ['milk' => '1', 'note' => 'ไม่สบาย กลับก่อน'],
        ]])->assertRedirect()->assertSessionHas('success');

        $this->assertSame(3, PreschoolDailyLog::where('date', $date)->count());
        $this->assertTrue(PreschoolDailyLog::where(['date' => $date, 'student_id' => $a->id])->first()->nap);
        $logB = PreschoolDailyLog::where(['date' => $date, 'student_id' => $b->id])->first();
        $this->assertTrue($logB->milk);
        $this->assertFalse($logB->meal);
        $this->assertSame('ไม่สบาย กลับก่อน', $logB->note);

        // บันทึกซ้ำวันเดิมแก้ค่าเดิม ไม่สร้างแถวใหม่
        $this->post(route('preschool.daily.save', $this->room), ['date' => $date, 'log' => [$b->id => ['meal' => '1']]])->assertRedirect();
        $this->assertSame(3, PreschoolDailyLog::where('date', $date)->count());
        $this->assertTrue(PreschoolDailyLog::where(['date' => $date, 'student_id' => $b->id])->first()->meal);

        $this->post(route('preschool.daily.save', $this->room), ['date' => today()->addDay()->toDateString()])->assertSessionHasErrors('date');
        $this->get(route('preschool.daily', ['date' => $date]))->assertSee('ไม่สบาย กลับก่อน');
    }

    public function test_standard_names_can_be_customised(): void
    {
        $admin = User::where('username', 'admin')->first();
        $this->actingAs($admin)->post(route('preschool.standards'), ['standards' => [1 => 'ร่างกายแข็งแรง (ฉบับโรงเรียน)', 2 => Preschool::STANDARDS[2]]])->assertRedirect();
        $this->assertSame('ร่างกายแข็งแรง (ฉบับโรงเรียน)', Preschool::standards()[1]);
        $this->assertSame(Preschool::STANDARDS[2], Preschool::standards()[2]);

        $this->actingAs(User::where('username', 'teacher')->first())->post(route('preschool.standards'), ['standards' => [1 => 'x']])->assertForbidden();
    }
}
