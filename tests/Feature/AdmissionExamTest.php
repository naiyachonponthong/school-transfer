<?php

namespace Tests\Feature;

use App\Http\Controllers\AdmissionExamController;
use App\Models\Admission;
use App\Models\AdmissionRound;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** สอบคัดเลือก: จัดห้องสอบ/เลขประจำตัวสอบ → ตรวจกระดาษด้วยระบบตรวจข้อสอบ → จัดอันดับ → ประกาศผล */
class AdmissionExamTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private int $n = 0;

    private function admin(): User
    {
        return User::where('username', 'admin')->first();
    }

    private function applicant(string $first, string $level = 'ป.1', array $extra = []): Admission
    {
        $this->n++;

        return Admission::create(array_merge([
            'app_no' => Admission::nextNumber(2570), 'year' => 2570, 'level' => $level, 'prefix' => 'เด็กชาย', 'first_name' => $first, 'last_name' => 'ทดสอบ',
            'parent_phone' => '08100000'.str_pad((string) $this->n, 2, '0', STR_PAD_LEFT), 'status' => 'submitted', 'submitted_at' => now(), 'fee_status' => 'none',
        ], $extra));
    }

    private function round(string $level = 'ป.1'): AdmissionRound
    {
        $this->actingAs($this->admin())->post('/admission-exams', ['year' => 2570, 'level' => $level])->assertRedirect();

        return AdmissionRound::where('level', $level)->first();
    }

    private function sheet(Exam $exam, Admission $a, string $answers, array $extra = []): array
    {
        return array_merge(['request_id' => 'r-'.uniqid('', true), 'student_code' => $a->exam_no, 'seat' => $a->exam_seat, 'answers' => $answers,
            'flags' => [], 'review' => false, 'confidence' => 0.95], $extra);
    }

    public function test_rooms_and_exam_numbers(): void
    {
        $this->actingAs(User::where('username', 'teacher')->first())->get('/admission-exams')->assertForbidden();
        $this->actingAs($this->admin())->get('/admission-exams?year=2570')->assertOk()->assertSee('ชั้น ม.1')->assertSee('เริ่มจัดสอบคัดเลือกชั้น ม.1');

        $round = $this->round('ม.1');
        $this->assertSame(10001, $round->exam_no_start);
        $this->actingAs($this->admin())->get("/admission-exams/{$round->id}")->assertOk()->assertSee('จัดห้องสอบและออกเลขประจำตัวสอบ');

        // ค้างค่าสมัคร → ยังไม่มีสิทธิ์สอบ · ผ่านแล้ว (accepted) ไม่ต้องสอบ
        $unpaid = $this->applicant('ค้างจ่าย', 'ม.1', ['fee_amount' => 200, 'fee_status' => 'unpaid']);
        $paid = $this->applicant('จ่ายแล้ว', 'ม.1', ['fee_amount' => 200, 'fee_status' => 'paid']);
        $eligible = Admission::where('level', 'ม.1')->whereIn('status', ['submitted', 'reviewing'])->where('id', '!=', $unpaid->id)->orderBy('app_no')->get();
        $this->assertCount(3, $eligible);

        $this->actingAs($this->admin())->post("/admission-exams/{$round->id}/seats", ['rooms' => '321, 1', 'exam_no_start' => 10001, 'order_by' => 'app_no', 'mode' => 'all'])
            ->assertSessionHasErrors('rooms');
        $this->assertSame(0, $round->takers()->count());

        $this->actingAs($this->admin())->post("/admission-exams/{$round->id}/seats", ['rooms' => "321, 2\n322 : 30 ที่นั่ง", 'exam_no_start' => 10001, 'order_by' => 'app_no', 'mode' => 'all', 'exam_date' => '2027-03-14'])
            ->assertRedirect();
        $takers = $round->takers()->get();
        $this->assertSame($eligible->pluck('id')->all(), $takers->pluck('id')->all());
        $this->assertSame(['10001', '10002', '10003'], $takers->pluck('exam_no')->all());
        $this->assertSame([['321', '1'], ['321', '2'], ['322', '1']], $takers->map(fn ($a) => [$a->exam_room, $a->exam_seat])->all());
        $this->assertNull($unpaid->fresh()->exam_no);
        $this->assertSame('2027-03-14', $round->fresh()->exam_date->toDateString());

        // ชำระค่าสมัครทีหลัง → ออกเลขต่อท้าย ไม่กระทบคนเดิม
        $unpaid->update(['fee_status' => 'paid']);
        $this->actingAs($this->admin())->post("/admission-exams/{$round->id}/seats", ['rooms' => "321, 2\n322, 30", 'exam_no_start' => 10001, 'order_by' => 'app_no', 'mode' => 'append'])->assertRedirect();
        $this->assertSame(['10004', '322', '2'], [$unpaid->fresh()->exam_no, $unpaid->fresh()->exam_room, $unpaid->fresh()->exam_seat]);
        // คนเดิมเลขไม่เปลี่ยน
        $this->assertSame($takers->pluck('exam_no', 'id')->all(), $round->takers()->whereIn('id', $takers->pluck('id'))->pluck('exam_no', 'id')->all());

        foreach (['door' => 'ห้องสอบ 321', 'sign' => 'กรรมการคุมสอบ', 'desk' => '10004'] as $doc => $see) {
            $this->actingAs($this->admin())->get("/admission-exams/{$round->id}/print/{$doc}")->assertOk()->assertSee($see)->assertSee($paid->fullName());
        }
        $this->actingAs($this->admin())->get("/admission-exams/{$round->id}/print/door?room=322")->assertOk()->assertDontSee('ห้องสอบ 321');

        // แก้รายคน: เลขประจำตัวสอบห้ามซ้ำในชั้นเดียวกัน
        $this->actingAs($this->admin())->put("/admissions/{$paid->id}/exam", ['exam_no' => '10001', 'exam_room' => '321', 'exam_seat' => '1'])->assertSessionHasErrors('exam_no');
        $this->actingAs($this->admin())->get("/admissions/{$paid->id}/print/application")->assertOk();
    }

    public function test_room_lines_are_parsed(): void
    {
        $this->assertSame([['name' => 'ห้อง 321', 'seats' => 35], ['name' => 'หอประชุม', 'seats' => 30], ['name' => '411', 'seats' => 40]],
            AdmissionExamController::parseRooms("ห้อง 321 : 35 ที่นั่ง\nหอประชุม\n\n411,40\n411, 20"));
    }

    public function test_scan_rank_publish_and_status_page(): void
    {
        [$a, $b, $c, $d, $e] = collect(['เอ', 'บี', 'ซี', 'ดี', 'อี'])->map(fn ($n) => $this->applicant($n))->all();
        $round = $this->round();
        $this->actingAs($this->admin())->post("/admission-exams/{$round->id}/seats", ['rooms' => '101, 30', 'exam_no_start' => 10001, 'order_by' => 'app_no', 'mode' => 'all']);
        [$a, $b, $c, $d, $e] = array_map(fn ($x) => $x->fresh(), [$a, $b, $c, $d, $e]);

        $this->actingAs($this->admin())->post("/admission-exams/{$round->id}/subjects", ['subject_name' => 'คณิตศาสตร์', 'n_items' => 5])->assertRedirect();
        $this->actingAs($this->admin())->post("/admission-exams/{$round->id}/subjects", ['subject_name' => 'ภาษาไทย', 'n_items' => 5, 'weight' => 2])->assertRedirect();
        [$math, $thai] = $round->exams()->get()->all();
        $this->assertTrue($math->isAdmission());
        foreach ([$math, $thai] as $ex) {
            $this->actingAs($this->admin())->postJson("/exams/{$ex->id}/key", ['key' => ['1', '1', '1', '1', '1']])->assertOk();
        }

        // หน้าระบบตรวจข้อสอบเดิมใช้กับผู้สมัครได้
        $this->actingAs($this->admin())->get("/exams/{$math->id}")->assertOk()->assertSee('สอบคัดเลือก')->assertSee('คณิตศาสตร์');
        $this->actingAs($this->admin())->get("/exams/{$math->id}/sheets")->assertOk()->assertSee('ห้องสอบ 101')
            ->assertSee(trim(json_encode($a->fullName()), '"'), false); // รายชื่ออยู่ใน data-students (JSON)
        $this->actingAs($this->admin())->getJson("/exams/{$math->id}/roster")->assertOk()->assertJsonPath('students.0.code', '10001')->assertJsonPath('students.0.name', $a->fullName());
        $this->actingAs(User::where('username', 'teacher')->first())->get("/exams/{$math->id}")->assertForbidden();
        $this->actingAs($this->admin())->get('/exams')->assertOk()->assertDontSee('สอบคัดเลือก ป.1');

        // A: 5+3×2=11 · B: 3+4×2=11 (เท่ากัน → ดูคณิตก่อน A ชนะ) · C: 2+2×2=6 · D: คณิต 1 (ไทยขาด) · E: ขาดสอบ
        $sheets = [[$math, $a, '11111'], [$thai, $a, '11100'], [$math, $b, '11100'], [$thai, $b, '11110'], [$math, $c, '11000'], [$thai, $c, '11000'], [$math, $d, '10000']];
        foreach ($sheets as [$ex, $who, $ans]) {
            $this->actingAs($this->admin())->postJson("/exams/{$ex->id}/responses", ['items' => [$this->sheet($ex, $who, $ans)]])
                ->assertOk()->assertJsonPath('results.0.status', 'ok')->assertJsonPath('results.0.student.name', $who->fullName());
        }
        // แผ่นที่อ่านเลขไม่ได้ → รอตรวจทาน → ประกาศผลไม่ได้
        $res = $this->actingAs($this->admin())->postJson("/exams/{$thai->id}/responses", ['items' => [$this->sheet($thai, $e, '22222', ['student_code' => '99999'])]])
            ->assertJsonPath('results.0.status', 'review')->json('results.0.response_id');
        $this->actingAs($this->admin())->get("/exams/{$thai->id}/results")->assertOk()->assertSee($a->fullName())->assertSee('ห้องสอบ');
        $this->actingAs($this->admin())->get("/exams/{$thai->id}/responses/{$res}")->assertOk()->assertSee($b->fullName());
        $this->actingAs($this->admin())->get("/exams/{$math->id}/analysis")->assertOk();
        $this->actingAs($this->admin())->get("/exams/{$math->id}/export")->assertOk();

        $this->actingAs($this->admin())->put("/admission-exams/{$round->id}", ['quota' => 1, 'reserve' => 1])->assertRedirect();
        $round->refresh();
        $rows = $round->standings();
        $this->assertSame([$a->id, $b->id, $c->id, $d->id, $e->id], $rows->map(fn ($r) => $r['application']->id)->all());
        $this->assertSame(['pass', 'reserve', 'fail', 'fail', 'absent'], $rows->pluck('result')->all());
        $this->assertSame([11.0, 11.0, 6.0, 1.0], $rows->take(4)->pluck('total')->all());

        $this->actingAs($this->admin())->post("/admission-exams/{$round->id}/publish")->assertSessionHasErrors('publish');
        $this->assertFalse($round->fresh()->isPublished());
        $this->actingAs($this->admin())->put("/exams/{$thai->id}/responses/{$res}", ['action' => 'void']);

        $this->actingAs($this->admin())->get("/admission-exams/{$round->id}?tab=results")->assertOk()->assertSee('ผ่านการคัดเลือก')->assertSee('ขาดสอบ');
        $this->actingAs($this->admin())->post("/admission-exams/{$round->id}/publish")->assertRedirect()->assertSessionHas('success');
        $this->assertTrue($round->fresh()->isPublished());
        $this->assertSame(['accepted', 1, 11.0], [$a->fresh()->status, $a->fresh()->exam_rank, $a->fresh()->exam_total]);
        $this->assertSame(['reserve', 1, 2], [$b->fresh()->status, $b->fresh()->reserve_no, $b->fresh()->exam_rank]);
        $this->assertSame(['rejected', 'rejected', 'rejected'], [$c->fresh()->status, $d->fresh()->status, $e->fresh()->status]);
        $this->assertNull($e->fresh()->exam_total);

        // ผู้สมัครดูผลเอง
        $this->get('/apply/status?'.http_build_query(['app_no' => $b->app_no, 'phone' => $b->parent_phone]))->assertOk()
            ->assertSee('สำรอง ลำดับที่ 1')->assertSee('คะแนนสอบรวม')->assertSee('10002');
        $this->get('/apply/status?'.http_build_query(['app_no' => $a->app_no, 'phone' => $a->parent_phone]))->assertSee('ขอแสดงความยินดี');

        $this->actingAs($this->admin())->get("/admission-exams/{$round->id}/print/announce")->assertOk()
            ->assertSee('เรื่อง รายชื่อผู้ผ่านการคัดเลือกเข้าศึกษาต่อชั้น ป.1')->assertSee($a->fullName())->assertSee($b->fullName())->assertDontSee($c->fullName());
        $this->actingAs($this->admin())->get("/admission-exams/{$round->id}/print/scores")->assertOk()->assertSee($c->fullName());
        $csv = $this->actingAs($this->admin())->get("/admission-exams/{$round->id}/export")->assertOk()->streamedContent();
        $this->assertStringContainsString('ภาษาไทย (×2)', $csv);
        $this->assertStringContainsString($a->fullName(), $csv);

        // สแกนแล้ว → จัดเลขใหม่ทั้งหมดไม่ได้
        $this->actingAs($this->admin())->post("/admission-exams/{$round->id}/seats", ['rooms' => '101, 30', 'exam_no_start' => 10001, 'order_by' => 'name', 'mode' => 'all'])
            ->assertSessionHasErrors('mode');

        // ยกเลิกประกาศ → กลับเป็นกำลังตรวจสอบ (มอบตัวแล้วไม่เปลี่ยน)
        $c->update(['status' => 'enrolled']);
        $this->actingAs($this->admin())->delete("/admission-exams/{$round->id}/publish")->assertRedirect();
        $this->assertFalse($round->fresh()->isPublished());
        $this->assertSame(['reviewing', 'reviewing', 'enrolled'], [$a->fresh()->status, $b->fresh()->status, $c->fresh()->status]);
        $this->assertNull($a->fresh()->exam_rank);
    }

    public function test_minimum_score_and_unlimited_quota(): void
    {
        [$a, $b] = [$this->applicant('หนึ่ง'), $this->applicant('สอง')];
        $round = $this->round();
        $this->actingAs($this->admin())->post("/admission-exams/{$round->id}/seats", ['rooms' => '101, 30', 'exam_no_start' => 20001, 'order_by' => 'app_no', 'mode' => 'all']);
        $this->actingAs($this->admin())->post("/admission-exams/{$round->id}/subjects", ['subject_name' => 'รวม', 'n_items' => 4]);
        $ex = $round->exams()->first();
        $this->actingAs($this->admin())->postJson("/exams/{$ex->id}/key", ['key' => ['1', '1', '1', '1']]);
        $this->actingAs($this->admin())->postJson("/exams/{$ex->id}/responses", ['items' => [$this->sheet($ex, $a->fresh(), '1111'), $this->sheet($ex, $b->fresh(), '1000')]])->assertOk();
        $round->update(['min_score' => 2]);
        $this->assertSame(['pass', 'fail'], $round->fresh()->standings()->pluck('result')->all());
        $this->assertSame('20001', $a->fresh()->exam_no);
    }
}
