<?php

namespace App\Http\Controllers;

use App\Models\Admission;
use App\Models\Term;
use App\Support\AdmissionForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * สมัครเรียนออนไลน์ (สาธารณะ ไม่ต้องล็อกอิน) แบบหลายขั้นตอน
 *
 * ขั้นแรกกรอก ระดับชั้น + เลขบัตร + วันเกิด + เบอร์ผู้ปกครอง → สร้างร่างทันที
 * กลับมากรอกต่อภายหลังได้ด้วยข้อมูล 3 อย่างเดิม · ใบสมัครที่ผู้ใช้ยืนยันตัวแล้วเก็บใน session "apply.id"
 */
class ApplyController extends Controller
{
    private const SESSION = 'apply.id';

    public static function year(): int
    {
        // รับสมัครสำหรับปีการศึกษาถัดไปเสมอ
        return (Term::current()?->year ?? now()->year + 543) + 1;
    }

    /** ใบสมัครที่ผู้ใช้เปิดอยู่ (ยืนยันตัวแล้ว) */
    private function current(Request $request): ?Admission
    {
        $id = $request->session()->get(self::SESSION);

        return $id ? Admission::find($id) : null;
    }

    private function draft(Request $request): Admission
    {
        $a = $this->current($request);
        if (! $a) {
            abort(redirect()->route('apply')->with('warning', 'กรอกเลขบัตร วันเกิด และเบอร์โทรเดิม เพื่อเปิดใบสมัครของท่าน'));
        }

        return $a;
    }

    public function start(Request $request)
    {
        $config = AdmissionForm::config();
        $preview = $request->boolean('preview') && $request->user()?->isAdmin();
        $current = $this->current($request);
        $year = self::year();

        return view('admissions.apply.start', [
            'config' => $config, 'year' => $year, 'levels' => AdmissionForm::levels(), 'preview' => $preview,
            'closedReason' => AdmissionForm::closedReason($config), 'full' => AdmissionForm::fullLevels($year, $config),
            'current' => $current,
            // ขั้นทั้งหมดของชั้นแรก ไว้แสดงภาพรวมว่ามีกี่ขั้น
            'previewSteps' => AdmissionForm::steps(AdmissionForm::levels()[0] ?? '', $config),
        ]);
    }

    /** เริ่มสมัคร / กลับมากรอกต่อ */
    public function begin(Request $request)
    {
        $config = AdmissionForm::config();
        abort_unless(AdmissionForm::isOpen($config), 403, AdmissionForm::closedReason($config) ?? 'ปิดรับสมัครแล้ว');
        $year = self::year();
        $data = $request->validate([
            'level' => ['required', Rule::in(array_diff(AdmissionForm::levels(), AdmissionForm::fullLevels($year, $config)))],
            'citizen_id' => ['required', 'digits:13'],
            'birthdate' => ['required', 'date', 'before:today'],
            'parent_phone' => ['required', 'regex:/^0\d{8,9}$/'],
            'consent' => ['accepted'],
            'website' => ['prohibited'], // honeypot กันบอท
        ], [
            'parent_phone.regex' => 'เบอร์โทรไม่ถูกต้อง', 'citizen_id.digits' => 'เลขประจำตัวประชาชนต้องมี 13 หลัก',
            'level.in' => 'ระดับชั้นนี้รับสมัครครบแล้ว หรือไม่ได้เปิดรับ', 'consent.accepted' => 'กรุณายอมรับการใช้ข้อมูลเพื่อการรับสมัคร',
        ], AdmissionForm::LABELS);

        $existing = Admission::where('year', $year)->where('citizen_id', $data['citizen_id'])->first();
        if ($existing) {
            // ต้องตรงทั้งวันเกิดและเบอร์ จึงเปิดใบสมัครเดิมได้ (กันคนอื่นที่รู้แค่เลขบัตร)
            if ($existing->birthdate?->toDateString() !== $data['birthdate'] || $existing->parent_phone !== $data['parent_phone']) {
                return back()->withInput()->withErrors(['citizen_id' => 'เลขบัตรนี้มีใบสมัครแล้ว แต่วันเกิดหรือเบอร์โทรไม่ตรงกับที่กรอกไว้']);
            }
            $request->session()->put(self::SESSION, $existing->id);
            if (! $existing->isDraft()) {
                return redirect()->route('apply.status')->with('success', 'ใบสมัครนี้ส่งแล้ว ดูสถานะและพิมพ์เอกสารได้ที่นี่');
            }

            return redirect()->route('apply.step', $this->nextStep($existing, $config))->with('success', "ยินดีต้อนรับกลับ — ใบสมัคร {$existing->app_no} กรอกต่อจากเดิมได้เลย");
        }

        $a = Admission::create([
            'year' => $year, 'app_no' => Admission::nextNumber($year), 'status' => 'draft', 'steps_done' => [],
            'level' => $data['level'], 'citizen_id' => $data['citizen_id'], 'birthdate' => $data['birthdate'], 'parent_phone' => $data['parent_phone'],
        ]);
        $request->session()->put(self::SESSION, $a->id);

        return redirect()->route('apply.step', AdmissionForm::steps($a->level, $config)[0] ?? 'review')
            ->with('success', "เริ่มใบสมัครเลขที่ {$a->app_no} แล้ว — ข้อมูลบันทึกทุกขั้น ปิดแล้วกลับมากรอกต่อได้");
    }

    /** ขั้นแรกที่ยังไม่ได้ผ่าน */
    private function nextStep(Admission $a, array $config): string
    {
        foreach (AdmissionForm::steps($a->level, $config) as $s) {
            if (! in_array($s, $a->steps_done ?? [], true)) {
                return $s;
            }
        }

        return 'review';
    }

    public function step(Request $request, string $step)
    {
        $a = $this->draft($request);
        if (! $a->isDraft()) {
            return redirect()->route('apply.status');
        }
        $config = AdmissionForm::config();
        if ($step === 'review') {
            return $this->review($a, $config);
        }
        $steps = AdmissionForm::steps($a->level, $config);
        abort_unless(in_array($step, $steps, true), 404);

        return view('admissions.apply.step', [
            'a' => $a, 'config' => $config, 'step' => $step, 'steps' => $steps, 'year' => self::year(),
            'fields' => AdmissionForm::stepFields($step, $config),
            'questions' => AdmissionForm::questionsFor($a->level, $config, $step),
            'levels' => AdmissionForm::levels(), 'full' => AdmissionForm::fullLevels(self::year(), $config),
        ]);
    }

    public function saveStep(Request $request, string $step)
    {
        $a = $this->draft($request);
        abort_unless($a->isDraft(), 422, 'ใบสมัครนี้ส่งแล้ว แก้ไขไม่ได้');
        $config = AdmissionForm::config();
        abort_unless(in_array($step, AdmissionForm::steps($a->level, $config), true), 404);
        $nav = $request->input('nav', 'next');
        $strict = $nav === 'next';

        // ไฟล์ที่ติ๊ก "ลบ" ถือว่ายังไม่ได้แนบ (คำถามบังคับจะต้องแนบใหม่)
        $removing = array_keys(array_filter((array) $request->input('remove', [])));
        $kept = array_values(array_filter($a->answers ?? [], fn ($x) => ! in_array($x['id'], $removing, true)));
        $rules = AdmissionForm::stepRules($step, $a->level, $kept, $strict, $config);
        if (in_array('level', AdmissionForm::stepFields($step, $config), true)) {
            $open = array_diff(AdmissionForm::levels(), array_diff(AdmissionForm::fullLevels(self::year(), $config), [$a->level]));
            $rules['level'] = ['required', Rule::in($open)];
        }
        $data = $request->validate($rules + ['remove' => ['array']], ['level.in' => 'ระดับชั้นนี้รับสมัครครบแล้ว'], AdmissionForm::attributes($a->level, $config));

        $level = $data['level'] ?? $a->level;
        $a->fill(collect($data)->except(['answers', 'remove'])->all());
        $a->answers = AdmissionForm::mergeAnswers($a->answers ?? [], $step, $level, $request->all()['answers'] ?? [], $data['remove'] ?? [], $config);
        if ($strict) {
            $a->steps_done = array_values(array_unique(array_merge($a->steps_done ?? [], [$step])));
        }
        $a->save();

        $steps = AdmissionForm::steps($a->level, $config); // เปลี่ยนชั้นแล้วขั้นอาจเปลี่ยน
        $i = array_search($step, $steps, true);
        if ($nav === 'save') {
            return back()->with('success', 'บันทึกแล้ว — กลับมากรอกต่อได้ด้วยเลขบัตร วันเกิด และเบอร์โทรเดิม');
        }
        $target = $nav === 'back' ? ($steps[$i - 1] ?? $steps[0]) : ($steps[$i + 1] ?? 'review');

        return redirect()->route('apply.step', $target);
    }

    private function review(Admission $a, array $config)
    {
        return view('admissions.apply.review', [
            'a' => $a, 'config' => $config, 'steps' => AdmissionForm::steps($a->level, $config), 'year' => self::year(),
            'missing' => AdmissionForm::incomplete($a, $config), 'fee' => AdmissionForm::fee($a->level, $config),
            'closedReason' => AdmissionForm::closedReason($config),
        ]);
    }

    public function submit(Request $request)
    {
        $a = $this->draft($request);
        abort_unless($a->isDraft(), 422, 'ใบสมัครนี้ส่งแล้ว');
        $config = AdmissionForm::config();
        abort_unless(AdmissionForm::isOpen($config), 403, AdmissionForm::closedReason($config) ?? 'ปิดรับสมัครแล้ว');
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'กรุณาติ๊กยืนยันว่าข้อมูลถูกต้อง']);
        if ($missing = AdmissionForm::incomplete($a, $config)) {
            return redirect()->route('apply.step', $missing['step'])->withErrors($missing['errors'])->with('warning', 'ยังกรอกขั้นนี้ไม่ครบ');
        }
        if (in_array($a->level, AdmissionForm::fullLevels(self::year(), $config), true)) {
            return redirect()->route('apply.step', 'student')->withErrors(['level' => 'ระดับชั้นนี้รับสมัครครบแล้ว']);
        }
        $fee = AdmissionForm::fee($a->level, $config);
        $a->update(['status' => 'submitted', 'submitted_at' => now(), 'fee_amount' => $fee ?: null, 'fee_status' => $fee > 0 ? 'unpaid' : 'none']);

        return redirect()->route('apply.status')->with('success', "ส่งใบสมัครเรียบร้อย เลขที่ใบสมัคร {$a->app_no}".($fee > 0 ? ' — กรุณาชำระค่าสมัครและแนบสลิปด้านล่าง' : ''));
    }

    /** ออก (ร่างยังอยู่ กลับมากรอกต่อได้) */
    public function leave(Request $request)
    {
        $request->session()->forget(self::SESSION);

        return redirect()->route('apply')->with('success', 'ออกแล้ว — กลับมากรอกต่อ/ดูสถานะได้ด้วยเลขบัตร วันเกิด และเบอร์โทรเดิม');
    }

    /** ตรวจสถานะด้วยเลขที่ใบสมัคร + เบอร์โทร หรือเปิดจาก session ที่ยืนยันตัวแล้ว */
    public function status(Request $request)
    {
        $a = null;
        if ($request->filled('app_no')) {
            $a = Admission::submitted()->where('app_no', strtoupper(trim($request->query('app_no'))))
                ->where('parent_phone', preg_replace('/\D/', '', (string) $request->query('phone')))->first();
            if ($a) {
                $request->session()->put(self::SESSION, $a->id);
            }
        } elseif (($cur = $this->current($request)) && ! $cur->isDraft()) {
            $a = $cur;
        }

        return view('admissions.status', [
            'a' => $a, 'searched' => $request->filled('app_no'), 'config' => AdmissionForm::config(),
            'draft' => ($cur = $this->current($request)) && $cur->isDraft() ? $cur : null,
        ]);
    }

    /** ผู้สมัครแนบสลิปค่าสมัคร */
    public function slip(Request $request)
    {
        $a = $this->draft($request);
        abort_unless($a->feeDue(), 422, 'ใบสมัครนี้ไม่มีค่าสมัครที่ต้องชำระ');
        $request->validate(['slip' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:'.AdmissionForm::FILE_MAX_KB]], [], ['slip' => 'สลิปโอนเงิน']);
        if ($a->fee_slip) {
            Storage::disk('local')->delete($a->fee_slip);
        }
        $a->update(['fee_slip' => $request->file('slip')->store('admissions/slips', 'local'), 'fee_status' => 'pending', 'fee_note' => null]);

        return back()->with('success', 'ส่งสลิปแล้ว เจ้าหน้าที่จะตรวจสอบและออกใบเสร็จให้');
    }

    /** ไฟล์ที่ผู้สมัครแนบเอง (เปิดดูได้เฉพาะเจ้าของใน session) */
    public function file(Request $request, string $question)
    {
        return AdmissionController::answerFileResponse($this->draft($request), $question);
    }

    /** เอกสาร: ใบสมัคร · ใบเสร็จค่าสมัคร · ใบมอบตัว */
    public function print(Request $request, string $doc)
    {
        $a = $this->draft($request);

        return AdmissionController::documentResponse($a, $doc, fn ($q) => route('apply.file', $q));
    }
}
