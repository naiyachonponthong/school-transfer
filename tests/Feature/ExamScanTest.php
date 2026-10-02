<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamResponse;
use App\Models\Score;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExamScanTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function teacher(): User
    {
        return User::where('username', 'teacher')->first();
    }

    private function exam(): Exam
    {
        return Exam::where('title', 'สอบกลางภาค')->first();
    }

    private function newExam(int $n = 5, array $key = ['1', '2', '3', '4', '1']): Exam
    {
        $course = Course::where('teacher_id', $this->teacher()->id)->whereHas('term', fn ($q) => $q->where('is_current', true))->first();
        $this->actingAs($this->teacher())->post('/exams', ['title' => 'สอบย่อย', 'n_items' => $n, 'course_ids' => [$course->id]])->assertRedirect();
        $exam = Exam::latest('id')->first();
        $this->actingAs($this->teacher())->postJson("/exams/{$exam->id}/key", ['key' => $key])->assertOk();

        return $exam->fresh();
    }

    private function item(Exam $exam, string $code, string $answers, array $extra = []): array
    {
        return array_merge(['request_id' => 'r-'.uniqid('', true), 'student_code' => $code, 'answers' => $answers, 'flags' => [], 'review' => false, 'confidence' => 0.9], $extra);
    }

    public function test_scoring_matches_scangrade_rules(): void
    {
        $e = new Exam(['n_items' => 5, 'answer_key' => ['1', '24', '3', '', '4'], 'cancelled' => [3], 'cancel_mode' => 'give', 'points' => 2]);
        // ข้อ 2 ถูกได้หลายตัวเลือก · ข้อ 3 ยกเลิก (ทุกคนได้) · ข้อ 4 ไม่มีเฉลย (ไม่มีใครได้) · ตอบซ้อน/ว่างไม่ได้คะแนน
        $this->assertSame(['score' => 6.0, 'max' => 10.0, 'marks' => [1, 1, 'c', 0, 0]], $e->score('14199'));
        $e->cancel_mode = 'drop';
        $this->assertSame(4.0, $e->score('14199')['score']);
        $this->assertSame(8.0, $e->score('14199')['max']);
        $this->assertSame(['1', '24', '3', '', '4'], Exam::cleanKey(['1', '42', 'ค3', null, '44'], 5));
        $this->assertSame('1234', Exam::normalizeAnswers('ก ข ค ง'));
    }

    public function test_pages_render_for_teacher(): void
    {
        $exam = $this->exam();
        $r = $exam->responses()->first();
        $this->actingAs($this->teacher());
        foreach (['/exams', "/exams/{$exam->id}", "/exams/{$exam->id}/sheets", "/exams/{$exam->id}/scan", "/exams/{$exam->id}/results",
            "/exams/{$exam->id}/results?tab=all", "/exams/{$exam->id}/analysis", "/exams/{$exam->id}/responses/{$r->id}"] as $url) {
            $this->assertSame(200, $this->get($url)->status(), $url);
        }
        $this->get("/exams/{$exam->id}/roster")->assertOk()->assertJsonPath('exam.n_items', 30)->assertJsonCount(24, 'students');
        $this->get('/menu')->assertSee('ตรวจข้อสอบ');
    }

    public function test_other_teacher_parent_and_student_are_blocked(): void
    {
        $exam = $this->exam();
        $other = User::where('role', 'teacher')->where('id', '!=', $this->teacher()->id)
            ->whereDoesntHave('courses', fn ($q) => $q->whereIn('id', $exam->courses->pluck('id')))->first();
        $this->actingAs($other)->get("/exams/{$exam->id}")->assertForbidden();
        $this->actingAs($other)->postJson("/exams/{$exam->id}/responses", ['items' => [$this->item($exam, '69001', str_repeat('1', 30))]])->assertForbidden();
        $this->actingAs($other)->get('/exams')->assertOk()->assertDontSee(route('exams.show', $exam));
        $this->actingAs(User::where('phone', '0812345678')->first())->get("/exams/{$exam->id}")->assertForbidden();
        $this->actingAs(User::where('role', 'student')->first())->get('/exams')->assertForbidden();
    }

    public function test_save_key_regrades_existing_sheets(): void
    {
        $exam = $this->newExam();
        $s = $exam->students()->first();
        $this->actingAs($this->teacher())->postJson("/exams/{$exam->id}/responses", ['items' => [$this->item($exam, $s->student_code, '12341')]])->assertOk();
        $this->assertSame(5.0, $exam->responses()->first()->score);

        // แก้เฉลยข้อ 1 → ตรวจใหม่อัตโนมัติ · ยกเลิกข้อ 5 แบบตัดออก
        $this->actingAs($this->teacher())->postJson("/exams/{$exam->id}/key", ['key' => ['2', '2', '3', '4', '1'], 'cancelled' => [5], 'cancel_mode' => 'drop'])
            ->assertOk()->assertJsonPath('regraded', 1)->assertJsonPath('key_ready', true);
        $r = $exam->responses()->first();
        $this->assertSame(3.0, $r->score);
        $this->assertSame(4.0, $r->max_score);
    }

    public function test_submit_is_idempotent_and_flags_unknown_and_duplicate(): void
    {
        $exam = $this->newExam();
        [$a, $b] = $exam->students()->take(2)->all();
        $first = $this->item($exam, '0'.$a->student_code, '12340'); // 0 นำหน้าก็หาเจอ
        $res = $this->actingAs($this->teacher())->postJson("/exams/{$exam->id}/responses", ['items' => [
            $first,
            $this->item($exam, '99999', '11111'),                               // ไม่พบรหัส
            $this->item($exam, $b->student_code, '12341', ['seat' => '40ก']),   // เลขที่ไม่ตรง
            $this->item($exam, $a->student_code, '12341'),                       // สแกนซ้ำ
        ]])->assertOk()->json('results');

        $this->assertSame(['ok', 'review', 'review', 'review'], array_column($res, 'status'));
        $this->assertEquals(4, $res[0]['score']);
        $this->assertSame($a->id, $res[0]['student']['id']);
        $this->assertContains('ไม่พบเลขประจำตัว', $res[1]['reasons']);
        $this->assertContains('เลขที่ไม่ตรงรายชื่อ', $res[2]['reasons']);
        $this->assertContains('สแกนซ้ำ', $res[3]['reasons']);

        // มือถือส่งซ้ำ (เน็ตหลุดตอนรอคำตอบ) → ไม่เกิดแถวซ้ำ
        $again = $this->actingAs($this->teacher())->postJson("/exams/{$exam->id}/responses", ['items' => [$first]])->json('results.0');
        $this->assertTrue($again['duplicate_request']);
        $this->assertSame(4, $exam->responses()->count());

        // คำตอบผิดความยาว/อักขระ → 422
        $this->actingAs($this->teacher())->postJson("/exams/{$exam->id}/responses", ['items' => [$this->item($exam, $a->student_code, '123')]])->assertStatus(422);
        $this->actingAs($this->teacher())->postJson("/exams/{$exam->id}/responses", ['items' => [$this->item($exam, $a->student_code, '1234x')]])->assertStatus(422);
    }

    public function test_scan_image_is_private_and_validated(): void
    {
        Storage::fake('local');
        $exam = $this->newExam();
        $s = $exam->students()->first();
        $img = imagecreatetruecolor(20, 28);
        ob_start();
        imagejpeg($img);
        $jpeg = 'data:image/jpeg;base64,'.base64_encode(ob_get_clean());
        $this->actingAs($this->teacher())->postJson("/exams/{$exam->id}/responses", ['items' => [
            $this->item($exam, $s->student_code, '12341', ['image' => $jpeg]),
            $this->item($exam, $s->student_code, '12341', ['image' => 'data:image/jpeg;base64,'.base64_encode('<?php echo 1;')]),
        ]])->assertOk();
        [$good, $bad] = $exam->responses()->orderBy('id')->get()->all();
        $this->assertNotNull($good->image);
        $this->assertNull($bad->image);
        Storage::disk('local')->assertExists($good->image);
        $this->actingAs($this->teacher())->get("/exams/{$exam->id}/responses/{$good->id}/image")->assertOk();
        $this->actingAs(User::where('phone', '0812345678')->first())->get("/exams/{$exam->id}/responses/{$good->id}/image")->assertForbidden();
        $this->assertStringNotContainsString('/storage/', route('exams.image', [$exam, $good]));
    }

    public function test_review_fixes_answers_owner_and_replaces_duplicate(): void
    {
        $exam = $this->newExam();
        [$a, $b] = $exam->students()->take(2)->all();
        $this->actingAs($this->teacher())->postJson("/exams/{$exam->id}/responses", ['items' => [
            $this->item($exam, $a->student_code, '12341'),
            $this->item($exam, '99999', '19341', ['flags' => ['multi:2']]),
        ]]);
        $orig = $exam->responses()->where('student_id', $a->id)->first();
        $unknown = $exam->responses()->whereNull('student_id')->first();
        $this->assertSame('review', $unknown->status);

        // ต้องระบุเจ้าของก่อน
        $this->actingAs($this->teacher())->put("/exams/{$exam->id}/responses/{$unknown->id}", ['answers' => '12341'])->assertSessionHasErrors('student_id');
        // เจ้าของเป็นคนที่มีแผ่นแล้ว → ถามก่อน แล้วยืนยันด้วย replace
        $this->actingAs($this->teacher())->put("/exams/{$exam->id}/responses/{$unknown->id}", ['answers' => '12341', 'student_id' => $a->id])->assertSessionHas('replace_prompt');
        $this->assertSame('review', $unknown->fresh()->status);
        $this->actingAs($this->teacher())->put("/exams/{$exam->id}/responses/{$unknown->id}", ['answers' => '12341', 'student_id' => $a->id, 'replace' => 1])->assertRedirect();
        $this->assertSame('void', $orig->fresh()->status);
        $u = $unknown->fresh();
        $this->assertSame(['ok', $a->id, 5.0], [$u->status, $u->student_id, $u->score]);
        $this->assertStringContainsString('แก้คำตอบข้อ 2', collect($u->edits)->last()['what']);

        // ยกเลิก/นำกลับ · คนนอกห้องสอบเป็นเจ้าของไม่ได้
        $this->actingAs($this->teacher())->put("/exams/{$exam->id}/responses/{$u->id}", ['action' => 'void']);
        $this->assertSame('void', $u->fresh()->status);
        $outsider = \App\Models\Student::whereNotIn('classroom_id', $exam->courses->pluck('classroom_id'))->first();
        $this->actingAs($this->teacher())->put("/exams/{$exam->id}/responses/{$u->id}", ['answers' => '12341', 'student_id' => $outsider->id])->assertSessionHasErrors('student_id');
    }

    public function test_sync_to_gradebook_scales_to_assessment_max(): void
    {
        $exam = $this->exam();
        $course = $exam->courses()->first();
        $a = $course->assessments()->where('name', 'สอบกลางภาค')->first(); // เต็ม 20
        $r = $exam->responses()->where('status', 'ok')->first();
        $this->actingAs($this->teacher())->post("/exams/{$exam->id}/sync", ['assessment_name' => 'สอบกลางภาค'])->assertSessionHas('success');
        $this->assertEquals(round($r->score / $r->max_score * 20, 2), (float) Score::where(['assessment_id' => $a->id, 'student_id' => $r->student_id])->value('score'));
        // แผ่นรอตรวจทานไม่ถูกส่ง
        $pending = $exam->responses()->where('status', 'review')->first();
        Score::where(['assessment_id' => $a->id, 'student_id' => $pending->student_id])->delete();
        $this->actingAs($this->teacher())->post("/exams/{$exam->id}/sync", ['assessment_name' => 'สอบกลางภาค']);
        $this->assertFalse(Score::where(['assessment_id' => $a->id, 'student_id' => $pending->student_id])->exists());
    }

    public function test_export_csv_and_evana_format(): void
    {
        $exam = $this->exam();
        $csv = $this->actingAs($this->teacher())->get("/exams/{$exam->id}/export")->assertOk()->streamedContent();
        $this->assertStringContainsString('เลขประจำตัว', $csv);
        $evana = $this->actingAs($this->teacher())->get("/exams/{$exam->id}/export?format=evana")->streamedContent();
        $this->assertStringContainsString('KEY,'.implode(',', $exam->key()), $evana);
    }

    public function test_published_scores_visible_to_student_and_parent_only_when_published(): void
    {
        $exam = $this->exam();
        $student = User::where('role', 'student')->first()->studentProfile;
        $this->assertTrue($exam->responses()->where('student_id', $student->id)->where('status', 'ok')->exists());
        $studentUser = $student->user;
        $this->actingAs($studentUser)->get('/me/info?tab=grades')->assertDontSee('ผลสอบ');
        $exam->update(['published' => true]);
        $this->actingAs($studentUser)->get('/me/info?tab=grades')->assertSee('ผลสอบ')->assertSee('สอบกลางภาค');
        $this->actingAs($student->guardians()->first())->get("/parent/child/{$student->id}?tab=grades")->assertSee('ผลสอบ');
    }

    public function test_create_rejects_mixed_subjects_and_other_teachers_courses(): void
    {
        $mine = Course::where('teacher_id', $this->teacher()->id)->whereHas('term', fn ($q) => $q->where('is_current', true))->get();
        $a = $mine->first();
        $b = $mine->firstWhere('subject_id', '!=', $a->subject_id);
        $this->actingAs($this->teacher())->post('/exams', ['title' => 'x', 'n_items' => 10, 'course_ids' => [$a->id, $b->id]])->assertStatus(422);
        $theirs = Course::where('teacher_id', '!=', $this->teacher()->id)->first();
        $this->actingAs($this->teacher())->post('/exams', ['title' => 'x', 'n_items' => 10, 'course_ids' => [$theirs->id]])->assertForbidden();
        $this->actingAs($this->teacher())->post('/exams', ['title' => 'x', 'n_items' => 101, 'course_ids' => [$a->id]])->assertSessionHasErrors('n_items');
    }
}
