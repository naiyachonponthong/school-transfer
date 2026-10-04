<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Conversation;
use App\Models\Course;
use App\Models\Score;
use App\Models\Student;
use App\Models\StudentWork;
use App\Models\Submission;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EngagementModulesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function teacher(): User
    {
        return User::where('username', 'teacher')->first();
    }

    private function parent(): User
    {
        return User::where('phone', '0812345678')->first();
    }

    public function test_pages_render(): void
    {
        $student = Student::first();
        $survey = Survey::first();
        $hw = Assignment::first();
        foreach (['/chat', '/homework', "/homework/{$hw->id}", '/surveys', "/surveys/{$survey->id}/classroom", "/surveys/{$survey->id}/students/{$student->id}",
            "/portfolio/{$student->id}", '/surveys/create', "/surveys/{$survey->id}/edit"] as $url) {
            $this->assertSame(200, $this->actingAs(User::where('username', 'admin')->first())->get($url)->status(), $url);
        }
        $child = $this->parent()->children()->first();
        foreach (['/chat', '/parent/homework', "/portfolio/{$child->id}", "/parent/child/{$child->id}?tab=survey", '/chat?c='.Conversation::first()->id] as $url) {
            $this->assertSame(200, $this->actingAs($this->parent())->get($url)->status(), $url);
        }
    }

    public function test_chat_flow_permissions_and_unread(): void
    {
        $parent = $this->parent();
        $child = $parent->children()->where('classroom_id', $this->teacher()->myClassrooms()->first()->id)->first();

        // ผู้ปกครองเริ่มแชทกับครูที่ไม่ได้สอนลูกไม่ได้
        $stranger = User::where('role', 'teacher')->whereDoesntHave('homerooms')->whereDoesntHave('courses', fn ($q) => $q->where('classroom_id', $child->classroom_id))->first();
        if ($stranger) {
            $this->actingAs($parent)->post('/chat', ['student_id' => $child->id, 'teacher_id' => $stranger->id])->assertForbidden();
        }

        // ครูเริ่มแชท → ใช้ห้องเดิมที่มีอยู่แล้ว (seed)
        $res = $this->actingAs($this->teacher())->post('/chat', ['student_id' => $child->id]);
        $res->assertRedirect();
        $conv = Conversation::where('student_id', $child->id)->first();

        $this->actingAs($this->teacher())->postJson("/chat/{$conv->id}", ['body' => 'ทดสอบข้อความ'])->assertOk()->assertJsonPath('message.mine', true);
        $this->assertGreaterThan(0, Conversation::unreadTotal($parent));

        $poll = $this->actingAs($parent)->getJson("/chat/{$conv->id}/poll?after=0")->assertOk();
        $this->assertContains('ทดสอบข้อความ', collect($poll->json('messages'))->pluck('body')->all());
        $this->assertSame(0, Conversation::unreadTotal($parent->fresh()));

        // หน้าแชทเรียงข้อความเก่า → ใหม่
        $html = strstr($this->actingAs($parent)->get('/chat?c='.$conv->id)->getContent(), 'id="threadBody"');
        $this->assertLessThan(strpos($html, 'ทดสอบข้อความ'), strpos($html, 'สวัสดีค่ะคุณครู'));

        // คนนอกห้องอ่าน/ส่งไม่ได้
        $other = User::where('role', 'parent')->where('id', '!=', $parent->id)->first();
        $this->actingAs($other)->getJson("/chat/{$conv->id}/poll")->assertForbidden();
        $this->actingAs($other)->postJson("/chat/{$conv->id}", ['body' => 'x'])->assertForbidden();
    }

    public function test_homework_submit_grade_and_sync_to_gradebook(): void
    {
        Storage::fake('local');
        $t = $this->teacher();
        $course = Course::where('teacher_id', $t->id)->whereHas('classroom', fn ($q) => $q->whereKey($t->myClassrooms()->first()->id))->with('assessments')->first();
        $assessment = $course->assessments->first(); // เต็ม 30

        $this->actingAs($t)->post('/homework', ['course_id' => $course->id, 'title' => 'งานทดสอบ', 'max_score' => 10, 'assessment_id' => $assessment->id, 'notify' => 0])->assertRedirect();
        $hw = Assignment::where('title', 'งานทดสอบ')->first();

        $parent = $this->parent();
        $child = $parent->children()->where('classroom_id', $course->classroom_id)->first();
        $this->actingAs($parent)->post("/parent/homework/{$hw->id}", ['student_id' => $child->id, 'file' => UploadedFile::fake()->image('work.jpg')])->assertSessionHasNoErrors();
        $this->assertNotNull(Submission::where('assignment_id', $hw->id)->where('student_id', $child->id)->value('submitted_at'));

        $this->actingAs($t)->post("/homework/{$hw->id}/grade", ['rows' => [$child->id => ['score' => 8, 'feedback' => 'ดี']]])->assertSessionHasNoErrors();
        $this->actingAs($t)->post("/homework/{$hw->id}/grade", ['rows' => [$child->id => ['score' => 11]]])->assertSessionHasErrors();
        $this->actingAs($t)->post("/homework/{$hw->id}/sync")->assertRedirect();
        $this->assertEquals(24.0, Score::where('assessment_id', $assessment->id)->where('student_id', $child->id)->value('score')); // 8/10 × 30

        // ตรวจแล้วส่งใหม่ไม่ได้ / ครูคนอื่นตรวจไม่ได้
        $this->actingAs($parent)->post("/parent/homework/{$hw->id}", ['student_id' => $child->id, 'text' => 'แก้'])->assertStatus(422);
        $this->actingAs(User::where('username', 't2')->first())->get("/homework/{$hw->id}")->assertForbidden();
    }

    public function test_survey_scoring_with_reverse_items_and_bands(): void
    {
        $survey = Survey::with('items')->first();
        $student = Student::active()->whereDoesntHave('guardians', fn ($q) => $q->where('phone', '0812345678'))->first();
        // ตอบ "จริงแน่นอน" (2) ทุกข้อ: ข้อกลับคะแนนจะได้ 0
        $answers = $survey->items->mapWithKeys(fn ($i) => [$i->id => 2])->all();
        $scores = $survey->score($answers);
        $emo = $survey->items->where('subscale', 'emo');
        $expectedEmo = $emo->sum(fn ($i) => $i->reverse ? 0 : 2);
        $this->assertSame($expectedEmo, $scores['subscales']['emo']['score']);
        $this->assertNotNull($scores['total_band']);

        $this->actingAs($this->teacher())->post("/surveys/{$survey->id}/students/{$student->id}", ['a' => $answers])->assertRedirect();
        $this->assertTrue(SurveyResponse::where('survey_id', $survey->id)->where('student_id', $student->id)->where('respondent_role', 'teacher')->exists());

        // ตอบไม่ครบ
        $this->actingAs($this->teacher())->post("/surveys/{$survey->id}/students/{$student->id}", ['a' => [array_key_first($answers) => 1]])->assertSessionHasErrors();

        // ผู้ปกครองตอบได้เฉพาะลูกตัวเอง
        $child = $this->parent()->children()->first();
        $this->actingAs($this->parent())->post("/surveys/{$survey->id}/students/{$child->id}", ['a' => $answers])->assertRedirect();
        $this->actingAs($this->parent())->get("/surveys/{$survey->id}/students/{$student->id}")->assertForbidden();

        // นักเรียน: ตอบไม่ได้จนกว่าแบบประเมินจะเปิดให้นักเรียนประเมินตนเอง และตอบได้เฉพาะของตัวเอง
        $me = \App\Models\User::create(['name' => $child->fullName(), 'username' => 'stu-survey', 'password' => 'secret123', 'role' => 'student', 'is_active' => true]);
        $child->update(['user_id' => $me->id]);
        $this->actingAs($me)->get("/surveys/{$survey->id}/students/{$child->id}")->assertForbidden();
        $this->get('/me/info?tab=survey')->assertOk()->assertDontSee('id="p-survey"', false);

        $survey->update(['respondent' => 'all']);
        $this->get('/me/info?tab=survey')->assertOk()->assertSee('แบบประเมินตนเอง')->assertSee($survey->title);
        $this->get("/surveys/{$survey->id}/students/{$child->id}")->assertOk()->assertSee('ประเมินตนเอง');
        $this->post("/surveys/{$survey->id}/students/{$child->id}", ['a' => $answers])->assertRedirect(route('student.info', ['tab' => 'survey']));
        $this->post("/surveys/{$survey->id}/students/{$student->id}", ['a' => $answers])->assertForbidden();
        $this->assertTrue(SurveyResponse::where(['survey_id' => $survey->id, 'student_id' => $child->id, 'respondent_role' => 'student', 'user_id' => $me->id])->exists());
        // ครูเห็นผลที่นักเรียนประเมินตนเองในตารางรายห้อง
        $this->actingAs(User::where('username', 'admin')->first())->get(route('surveys.classroom', ['survey' => $survey, 'classroom' => $child->classroom_id]))->assertOk()->assertSee('นักเรียน');
    }

    public function test_survey_editor_parses_definition_and_rejects_bad_input(): void
    {
        $admin = User::where('username', 'admin')->first();
        $def = "[ตัวเลือก]\nไม่ใช่=0\nใช่=1\n\n[ด้าน]\na | ด้านเอ | 1 | ปกติ<=1; เสี่ยง<=2\n\n[รวม]\nปกติ<=1; เสี่ยง<=2\n\n[ข้อคำถาม]\nข้อหนึ่ง | a\nข้อสอง | a | R";
        $this->actingAs($admin)->post('/surveys', ['title' => 'ทดสอบ', 'respondent' => 'teacher', 'definition' => $def, 'is_active' => 1])->assertRedirect();
        $s = Survey::where('title', 'ทดสอบ')->with('items')->first();
        $this->assertCount(2, $s->items);
        $this->assertTrue($s->items[1]->reverse);
        $this->assertSame('warning', $s->subscales[0]['bands'][1]['color']);

        $this->actingAs($admin)->post('/surveys', ['title' => 'เสีย', 'respondent' => 'teacher', 'definition' => "[ข้อคำถาม]\nข้อ | zzz"])->assertSessionHasErrors('definition');
    }

    public function test_portfolio_parent_adds_pending_teacher_verifies(): void
    {
        $parent = $this->parent();
        $child = $parent->children()->first();
        $this->actingAs($parent)->post("/portfolio/{$child->id}", ['category' => 'volunteer', 'title' => 'ปลูกต้นไม้', 'hours' => 2])->assertRedirect();
        $w = StudentWork::where('title', 'ปลูกต้นไม้')->first();
        $this->assertFalse($w->verified);

        $this->actingAs($parent)->post("/portfolio-items/{$w->id}/verify")->assertForbidden();
        $this->actingAs($this->teacher())->post("/portfolio-items/{$w->id}/verify")->assertRedirect();
        $this->assertTrue($w->fresh()->verified);

        $stranger = Student::whereDoesntHave('guardians', fn ($q) => $q->whereKey($parent->id))->first();
        $this->actingAs($parent)->get("/portfolio/{$stranger->id}")->assertForbidden();
    }
}
