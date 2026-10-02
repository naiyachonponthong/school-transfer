<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** แบบประเมิน/คัดกรองพฤติกรรม ที่โรงเรียนสร้างเองได้ */
class SurveyController extends Controller
{
    public function index(Request $request)
    {
        $term = Term::current();

        return view('surveys.index', [
            'surveys' => Survey::withCount(['items', 'responses' => fn ($q) => $q->where('term_id', $term?->id)])->orderByDesc('is_active')->orderBy('title')->get(),
            'classrooms' => $request->user()->isAdmin() ? Classroom::currentYear()->ordered()->get() : $request->user()->myClassrooms(),
        ]);
    }

    public function create()
    {
        return view('surveys.form', ['survey' => new Survey(['respondent' => 'teacher', 'is_active' => true]), 'text' => self::EXAMPLE]);
    }

    public function edit(Survey $survey)
    {
        return view('surveys.form', ['survey' => $survey, 'text' => $this->toText($survey)]);
    }

    public function store(Request $request)
    {
        $survey = DB::transaction(fn () => $this->saveFromRequest($request, new Survey));

        return redirect()->route('surveys.index')->with('success', "สร้างแบบประเมิน \"{$survey->title}\" แล้ว");
    }

    public function update(Request $request, Survey $survey)
    {
        abort_if($survey->responses()->exists() && $this->itemsChanged($request, $survey), 422,
            'แบบประเมินนี้มีผู้ตอบแล้ว แก้ข้อคำถามไม่ได้ (แก้ได้เฉพาะชื่อ/เกณฑ์) — สร้างแบบใหม่แทน');
        DB::transaction(fn () => $this->saveFromRequest($request, $survey));

        return redirect()->route('surveys.index')->with('success', 'บันทึกแล้ว');
    }

    /** รายชื่อทั้งห้อง + สถานะการประเมิน + ผล */
    public function classroom(Request $request, Survey $survey)
    {
        $term = Term::current();
        $classrooms = $request->user()->isAdmin() ? Classroom::currentYear()->ordered()->get() : $request->user()->myClassrooms();
        $classroom = $classrooms->firstWhere('id', (int) $request->query('classroom')) ?? $classrooms->first();
        $students = $classroom ? $classroom->students()->get() : collect();
        $responses = SurveyResponse::where('survey_id', $survey->id)->where('term_id', $term?->id)
            ->whereIn('student_id', $students->pluck('id'))->get()->groupBy('student_id');

        // สรุปจำนวนตามกลุ่มผลรวม
        $summary = $responses->flatten()->where('respondent_role', 'teacher')
            ->countBy(fn ($r) => $r->scores['total_band']['label'] ?? '-');

        return view('surveys.classroom', compact('survey', 'classrooms', 'classroom', 'students', 'responses', 'summary', 'term'));
    }

    public function fill(Request $request, Survey $survey, Student $student)
    {
        $role = $this->roleFor($request, $survey, $student);
        $term = Term::current();
        $survey->load('items');
        $existing = SurveyResponse::where(['survey_id' => $survey->id, 'student_id' => $student->id, 'term_id' => $term?->id, 'respondent_role' => $role])->first();

        return view('surveys.fill', compact('survey', 'student', 'role', 'existing', 'term'));
    }

    public function save(Request $request, Survey $survey, Student $student)
    {
        $role = $this->roleFor($request, $survey, $student);
        $survey->load('items');
        $values = collect($survey->scale)->pluck('value')->map(fn ($v) => (string) $v)->all();
        $rules = [];
        foreach ($survey->items as $item) {
            $rules["a.{$item->id}"] = ['required', Rule::in($values)];
        }
        $request->validate($rules, ['a.*.required' => 'กรุณาตอบให้ครบทุกข้อ']);

        $answers = collect($survey->items)->mapWithKeys(fn ($i) => [$i->id => (int) $request->input("a.{$i->id}")])->all();
        $term = Term::current();
        SurveyResponse::updateOrCreate(
            ['survey_id' => $survey->id, 'student_id' => $student->id, 'term_id' => $term?->id, 'respondent_role' => $role],
            ['user_id' => $request->user()->id, 'answers' => $answers, 'scores' => $survey->score($answers)]
        );

        return $request->user()->isParent()
            ? redirect()->route('parent.child', ['student' => $student, 'tab' => 'survey'])->with('success', 'ส่งแบบประเมินแล้ว ขอบคุณครับ')
            : redirect()->route('surveys.classroom', ['survey' => $survey, 'classroom' => $student->classroom_id])->with('success', "บันทึกผลของ {$student->fullName()} แล้ว");
    }

    private function roleFor(Request $request, Survey $survey, Student $student): string
    {
        $user = $request->user();
        abort_unless($survey->is_active, 404);
        abort_if($user->isStudent(), 403, 'แบบประเมินนี้สำหรับครูและผู้ปกครอง');
        if ($user->isParent()) {
            abort_unless($survey->allows('parent') && $student->isGuardedBy($user), 403);

            return 'parent';
        }
        abort_unless($survey->allows('teacher'), 403, 'แบบประเมินนี้ให้ผู้ปกครองเป็นผู้ตอบ');

        return 'teacher';
    }

    /* ---------- ตัวแก้ไขแบบประเมินแบบข้อความ ---------- */

    public const EXAMPLE = <<<'TXT'
[ตัวเลือก]
ไม่จริง=0
จริงบางครั้ง=1
จริงแน่นอน=2

[ด้าน]
# รหัส | ชื่อด้าน | นับรวม(1/0) | เกณฑ์ (ชื่อ<=คะแนนสูงสุด สี)
emo | อารมณ์ | 1 | ปกติ<=2 success; เฝ้าระวัง<=3 warning; มีปัญหา<=6 danger
beh | พฤติกรรม | 1 | ปกติ<=2 success; เฝ้าระวัง<=3 warning; มีปัญหา<=6 danger
soc | สัมพันธภาพกับเพื่อน | 1 | ปกติ<=2 success; เฝ้าระวัง<=3 warning; มีปัญหา<=6 danger
pro | ความมีน้ำใจ (จุดแข็ง) | 0 | ควรส่งเสริม<=2 warning; ดี<=6 success

[รวม]
ปกติ<=6 success; เฝ้าระวัง<=9 warning; มีปัญหา<=18 danger

[ข้อคำถาม]
# ข้อความ | รหัสด้าน | R = กลับคะแนน
มักกังวลหรือดูเศร้าบ่อยครั้ง | emo
บ่นปวดหัว ปวดท้อง บ่อยโดยไม่มีสาเหตุชัดเจน | emo
ร่าเริงแจ่มใสเป็นส่วนใหญ่ | emo | R
หงุดหงิดง่ายหรือโกรธรุนแรง | beh
ทำตามกติกาของห้องเรียนได้ | beh | R
ไม่อยู่นิ่ง ลุกจากที่นั่งบ่อย | beh
มีเพื่อนสนิทอย่างน้อยหนึ่งคน | soc | R
มักเล่นคนเดียวหรือแยกตัวจากกลุ่ม | soc
ถูกเพื่อนล้อหรือแกล้งบ่อย | soc
ช่วยเหลือเพื่อนเมื่อเพื่อนมีปัญหา | pro
แบ่งปันของให้ผู้อื่น | pro
อาสาช่วยงานครูหรือส่วนรวม | pro
TXT;

    private function saveFromRequest(Request $request, Survey $survey): Survey
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'respondent' => ['required', Rule::in(array_keys(Survey::RESPONDENTS))],
            'definition' => ['required', 'string', 'max:30000'],
        ]);
        $def = $this->parse($data['definition']);
        $survey->fill([
            'title' => $data['title'], 'description' => $data['description'] ?? null, 'respondent' => $data['respondent'],
            'scale' => $def['scale'], 'subscales' => $def['subscales'], 'total_bands' => $def['total'], 'is_active' => $request->boolean('is_active'),
        ])->save();

        if (! $survey->responses()->exists()) {
            $survey->items()->delete();
            foreach ($def['items'] as $i => $item) {
                $survey->items()->create($item + ['sort' => $i + 1]);
            }
        }

        return $survey;
    }

    private function itemsChanged(Request $request, Survey $survey): bool
    {
        $new = collect($this->parse((string) $request->input('definition'))['items'])->map(fn ($i) => $i['text'].'|'.$i['subscale'].'|'.(int) $i['reverse'])->all();
        $old = $survey->items->map(fn ($i) => $i->text.'|'.$i->subscale.'|'.(int) $i->reverse)->all();

        return $new !== $old;
    }

    /** แปลงข้อความตัวแก้ไขเป็นโครงสร้างแบบประเมิน */
    public function parse(string $text): array
    {
        $section = null;
        $out = ['scale' => [], 'subscales' => [], 'total' => [], 'items' => []];
        foreach (preg_split('/\R/', $text) as $n => $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('/^\[(.+)\]$/u', $line, $m)) {
                $section = trim($m[1]);

                continue;
            }
            $parts = array_map('trim', explode('|', $line));
            switch ($section) {
                case 'ตัวเลือก':
                    [$label, $value] = array_pad(explode('=', $line, 2), 2, null);
                    if (! is_numeric($value)) {
                        throw ValidationException::withMessages(['definition' => 'บรรทัด '.($n + 1).': ตัวเลือกต้องเป็นรูปแบบ ข้อความ=ตัวเลข']);
                    }
                    $out['scale'][] = ['label' => trim($label), 'value' => (int) $value];
                    break;
                case 'ด้าน':
                    $out['subscales'][] = ['key' => $parts[0], 'name' => $parts[1] ?? $parts[0], 'in_total' => ($parts[2] ?? '1') === '1', 'bands' => $this->parseBands($parts[3] ?? '', $n)];
                    break;
                case 'รวม':
                    $out['total'] = $this->parseBands($line, $n);
                    break;
                case 'ข้อคำถาม':
                    $out['items'][] = ['text' => $parts[0], 'subscale' => $parts[1] ?? null, 'reverse' => strtoupper($parts[2] ?? '') === 'R'];
                    break;
            }
        }
        if (count($out['scale']) < 2 || ! $out['items']) {
            throw ValidationException::withMessages(['definition' => 'ต้องมี [ตัวเลือก] อย่างน้อย 2 ตัว และ [ข้อคำถาม] อย่างน้อย 1 ข้อ']);
        }
        $keys = collect($out['subscales'])->pluck('key')->all();
        foreach ($out['items'] as $i => $item) {
            if ($item['subscale'] && ! in_array($item['subscale'], $keys, true)) {
                throw ValidationException::withMessages(['definition' => 'ข้อที่ '.($i + 1).": ไม่พบรหัสด้าน \"{$item['subscale']}\" ใน [ด้าน]"]);
            }
        }

        return $out;
    }

    private function parseBands(string $text, int $line): array
    {
        $colors = ['success', 'warning', 'danger'];
        $bands = [];
        foreach (array_filter(array_map('trim', explode(';', $text))) as $i => $part) {
            if (! preg_match('/^(.+?)\s*<=\s*(\d+)\s*(success|warning|danger|info|secondary)?$/u', $part, $m)) {
                throw ValidationException::withMessages(['definition' => 'บรรทัด '.($line + 1).": เกณฑ์ \"{$part}\" ต้องเป็นรูปแบบ ชื่อ<=คะแนน สี"]);
            }
            $bands[] = ['label' => trim($m[1]), 'max' => (int) $m[2], 'color' => $m[3] ?? ($colors[$i] ?? 'danger')];
        }

        return collect($bands)->sortBy('max')->values()->all();
    }

    private function toText(Survey $s): string
    {
        $bands = fn ($bs) => collect($bs ?? [])->map(fn ($b) => "{$b['label']}<={$b['max']} {$b['color']}")->implode('; ');
        $t = "[ตัวเลือก]\n".collect($s->scale)->map(fn ($o) => "{$o['label']}={$o['value']}")->implode("\n");
        $t .= "\n\n[ด้าน]\n".collect($s->subscales)->map(fn ($x) => "{$x['key']} | {$x['name']} | ".(! empty($x['in_total']) ? 1 : 0).' | '.$bands($x['bands'] ?? []))->implode("\n");
        $t .= "\n\n[รวม]\n".$bands($s->total_bands);
        $t .= "\n\n[ข้อคำถาม]\n".$s->items->map(fn ($i) => $i->text.($i->subscale ? " | {$i->subscale}" : '').($i->reverse ? ' | R' : ''))->implode("\n");

        return $t;
    }
}
