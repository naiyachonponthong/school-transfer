<?php

namespace App\Support;

use App\Models\Admission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * ฟอร์มรับสมัครที่โรงเรียนปรับเองได้ (เก็บเป็น JSON ใน settings.admission_form)
 *
 * ช่องหลัก (ชื่อ เพศ วันเกิด เลขบัตร ระดับชั้น ผู้ปกครอง เบอร์โทร) ล็อกไว้เสมอ เพราะใช้ตอน "มอบตัว" สร้างนักเรียน+บัญชีผู้ปกครอง
 * และใช้ยืนยันตัวตนตอนกลับมากรอกต่อ/ตรวจสถานะ — ส่วนช่องเสริมเลือก แสดง/บังคับ/ซ่อน ได้ และเพิ่มคำถามของโรงเรียนเองได้
 *
 * ผู้ปกครองกรอกทีละขั้น (STEPS) ทุกขั้นบันทึกลงร่างทันที กลับมากรอกต่อได้ด้วย เลขบัตร + วันเกิด + เบอร์โทร
 */
class AdmissionForm
{
    public const TYPES = [
        'text' => ['ข้อความสั้น', 'bi-input-cursor-text'],
        'textarea' => ['ข้อความยาว', 'bi-text-paragraph'],
        'number' => ['ตัวเลข', 'bi-123'],
        'date' => ['วันที่', 'bi-calendar-date'],
        'select' => ['ตัวเลือก (ดรอปดาวน์)', 'bi-menu-button-wide'],
        'radio' => ['ตัวเลือก (เลือกข้อเดียว)', 'bi-ui-radios'],
        'checkbox' => ['ตัวเลือก (เลือกได้หลายข้อ)', 'bi-ui-checks'],
        'file' => ['แนบไฟล์ (รูป/PDF)', 'bi-paperclip'],
    ];

    public const CHOICE_TYPES = ['select', 'radio', 'checkbox'];

    /** ขั้นตอนของฟอร์ม: [ชื่อขั้น, ไอคอน, ช่องของระบบในขั้นนี้] · ขั้นที่ไม่มีอะไรให้กรอกจะถูกข้ามอัตโนมัติ */
    public const STEPS = [
        'student' => ['ข้อมูลผู้สมัคร', 'bi-person', ['level', 'prefix', 'first_name', 'last_name', 'nickname', 'gender']],
        'education' => ['การศึกษาเดิม', 'bi-mortarboard', ['previous_school', 'gpa']],
        'family' => ['ผู้ปกครองและที่อยู่', 'bi-person-hearts', ['parent_name', 'relation', 'address']],
        'extra' => ['ข้อมูลเพิ่มเติม', 'bi-ui-checks', ['note']],
        'documents' => ['เอกสารแนบ', 'bi-paperclip', []],
    ];

    /** ช่องเสริมของระบบ: [ป้ายชื่อ, ค่าเริ่มต้น] · show = แสดง (ไม่บังคับ) · required = บังคับ · hidden = ซ่อน */
    public const OPTIONAL_FIELDS = [
        'nickname' => ['ชื่อเล่น', 'show'],
        'previous_school' => ['โรงเรียนเดิม', 'show'],
        'gpa' => ['เกรดเฉลี่ยเดิม', 'show'],
        'relation' => ['ความสัมพันธ์กับผู้สมัคร', 'show'],
        'address' => ['ที่อยู่', 'show'],
        'note' => ['ข้อมูลเพิ่มเติม (ข้อความอิสระ)', 'show'],
    ];

    /** ช่องหลักที่บังคับเสมอในแต่ละขั้น (level ตรวจแยกเพราะขึ้นกับชั้นที่เปิดรับ/เต็ม) */
    private const CORE = [
        'prefix' => ['required', 'string', 'max:20'],
        'first_name' => ['required', 'string', 'max:100'],
        'last_name' => ['required', 'string', 'max:100'],
        'gender' => ['required', 'in:M,F'],
        'parent_name' => ['required', 'string', 'max:255'],
    ];

    private const OPTIONAL_RULES = [
        'nickname' => ['string', 'max:50'], 'previous_school' => ['string', 'max:255'], 'gpa' => ['numeric', 'between:0,4'],
        'relation' => ['string', 'max:30'], 'address' => ['string', 'max:1000'], 'note' => ['string', 'max:1000'],
    ];

    public const LABELS = [
        'level' => 'ระดับชั้นที่สมัคร', 'prefix' => 'คำนำหน้า', 'first_name' => 'ชื่อ', 'last_name' => 'นามสกุล', 'gender' => 'เพศ',
        'birthdate' => 'วันเกิด', 'citizen_id' => 'เลขประจำตัวประชาชน', 'parent_name' => 'ชื่อผู้ปกครอง', 'parent_phone' => 'เบอร์โทรผู้ปกครอง',
    ];

    public const MAX_QUESTIONS = 40;

    public const FILE_MAX_KB = 6144;

    /** คำที่ชี้ว่าเป็นข้อมูลอ่อนไหวตาม PDPA มาตรา 26 — เตือนให้ถามเท่าที่จำเป็น */
    public const SENSITIVE = ['ศาสนา', 'เชื้อชาติ', 'เผ่าพันธุ์', 'โรค', 'สุขภาพ', 'พิการ', 'ความพิการ', 'ประวัติอาชญากรรม', 'คดี', 'ลายนิ้วมือ', 'ใบหน้า', 'พฤติกรรมทางเพศ', 'ความคิดเห็นทางการเมือง', 'พรรค', 'สหภาพ', 'พันธุกรรม', 'กรุ๊ปเลือด', 'ยาที่ใช้', 'รายได้'];

    public const DEFAULT_PLEDGE = 'ข้าพเจ้าขอรับรองว่า จะตักเตือนให้นักเรียนหมั่นศึกษาเล่าเรียนอย่างสม่ำเสมอ ให้ประพฤติตนเป็นคนดี เรียบร้อย ตามระเบียบข้อบังคับของโรงเรียน ทั้งนี้จะเป็นผู้อุปถัมภ์ในเรื่องค่าใช้จ่ายต่าง ๆ ให้ถูกต้องตามระเบียบข้อบังคับของโรงเรียนทุกประการ หากปรากฏว่านักเรียนในความปกครองของข้าพเจ้าไม่ปฏิบัติตามระเบียบข้อบังคับของโรงเรียน ข้าพเจ้ายินดีปฏิบัติตามผลการพิจารณาตัดสินของโรงเรียนทุกประการ';

    /* ================================================================ ตั้งค่า */

    public static function config(): array
    {
        $raw = json_decode((string) Settings::get('admission_form', ''), true);

        return self::normalize(is_array($raw) ? $raw : self::defaults());
    }

    public static function defaults(): array
    {
        return [
            'intro' => '', 'open_from' => null, 'open_until' => null, 'caps' => [],
            'fees' => [], 'fee_note' => '', 'pledge' => self::DEFAULT_PLEDGE, 'exam_slip' => true,
            'optional' => array_map(fn ($f) => $f[1], self::OPTIONAL_FIELDS),
            'questions' => [
                ['id' => 'q_photo', 'type' => 'file', 'label' => 'รูปถ่ายผู้สมัคร (หน้าตรง)', 'help' => 'ใช้พิมพ์ลงใบสมัครและใบมอบตัว (JPG/PNG)', 'required' => false, 'photo' => true, 'step' => 'documents'],
                ['id' => 'q_docs', 'type' => 'file', 'label' => 'เอกสารประกอบ', 'help' => 'ใบรับรองผลการเรียน หรือสำเนาทะเบียนบ้าน (รูปหรือ PDF ไม่เกิน 6 MB)', 'required' => false, 'step' => 'documents'],
            ],
        ];
    }

    /** ทำความสะอาดโครงสร้าง (ใช้ทั้งตอนอ่านและตอนบันทึก) */
    public static function normalize(array $c): array
    {
        $opt = [];
        foreach (self::OPTIONAL_FIELDS as $k => [, $def]) {
            $v = $c['optional'][$k] ?? $def;
            $opt[$k] = in_array($v, ['show', 'required', 'hidden'], true) ? $v : $def;
        }
        $questions = [];
        $seen = [];
        foreach (array_slice($c['questions'] ?? [], 0, self::MAX_QUESTIONS) as $q) {
            $type = isset(self::TYPES[$q['type'] ?? '']) ? $q['type'] : 'text';
            $label = Str::limit(trim((string) ($q['label'] ?? '')), 200, '');
            if ($label === '') {
                continue;
            }
            // id คงที่ตลอดอายุคำถาม (ใช้จับคู่คำตอบ/ไฟล์/ส่งออก) — ใหม่หรือซ้ำ → สุ่มให้
            $id = preg_match('/^q_[A-Za-z0-9_]{2,30}$/', (string) ($q['id'] ?? '')) && ! isset($seen[$q['id']]) ? $q['id'] : 'q_'.Str::lower(Str::random(8));
            $seen[$id] = true;
            $options = in_array($type, self::CHOICE_TYPES, true)
                ? array_values(array_unique(array_filter(array_map(fn ($o) => Str::limit(trim((string) $o), 120, ''), (array) ($q['options'] ?? [])), 'strlen')))
                : [];
            if (in_array($type, self::CHOICE_TYPES, true) && ! $options) {
                continue; // คำถามตัวเลือกที่ไม่มีตัวเลือก ใช้งานไม่ได้
            }
            $step = isset(self::STEPS[$q['step'] ?? '']) ? $q['step'] : ($type === 'file' ? 'documents' : 'extra');
            $questions[] = [
                'id' => $id, 'type' => $type, 'label' => $label,
                'help' => Str::limit(trim((string) ($q['help'] ?? '')), 300, ''),
                'required' => (bool) ($q['required'] ?? false),
                'options' => array_slice($options, 0, 50),
                'levels' => array_values(array_filter(array_map('strval', (array) ($q['levels'] ?? [])))),
                'step' => $step,
                'photo' => $type === 'file' && ! empty($q['photo']),
            ];
        }
        // รูปถ่ายในเอกสารมีได้คำถามเดียว (ข้อแรกที่ติ๊กไว้)
        $photoSeen = false;
        foreach ($questions as &$q) {
            $q['photo'] = $q['photo'] && ! $photoSeen;
            $photoSeen = $photoSeen || $q['photo'];
        }
        unset($q);
        $positive = function ($map, bool $int) {
            $out = [];
            foreach ((array) $map as $level => $n) {
                if ((float) $n > 0) {
                    $out[(string) $level] = $int ? (int) $n : round((float) $n, 2);
                }
            }

            return $out;
        };
        $date = fn ($d) => $d && strtotime((string) $d) ? Carbon::parse($d)->toDateString() : null;

        return [
            'intro' => Str::limit(trim((string) ($c['intro'] ?? '')), 2000, ''),
            'open_from' => $date($c['open_from'] ?? null),
            'open_until' => $date($c['open_until'] ?? null),
            'caps' => $positive($c['caps'] ?? [], true),
            'fees' => $positive($c['fees'] ?? [], false),
            'fee_note' => Str::limit(trim((string) ($c['fee_note'] ?? '')), 1000, ''),
            'pledge' => Str::limit(trim((string) ($c['pledge'] ?? self::DEFAULT_PLEDGE)), 3000, ''),
            'exam_slip' => (bool) ($c['exam_slip'] ?? true),
            'optional' => $opt,
            'questions' => $questions,
        ];
    }

    public static function save(array $config): array
    {
        $config = self::normalize($config);
        Settings::set(['admission_form' => json_encode($config, JSON_UNESCAPED_UNICODE)]);

        return $config;
    }

    public static function levels(): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) Settings::get('admission_levels')))));
    }

    /* ================================================================ การเปิดรับ */

    /** สวิตช์เปิดรับ + อยู่ในช่วงวันที่กำหนด (ถ้ากำหนด) */
    public static function isOpen(?array $c = null): bool
    {
        return self::closedReason($c) === null;
    }

    /** เหตุผลที่ปิดรับ (ไว้แสดงให้ผู้ปกครองเห็น) · null = เปิดอยู่ */
    public static function closedReason(?array $c = null): ?string
    {
        $c ??= self::config();
        $today = today()->toDateString();
        if (! Settings::get('admission_open')) {
            return 'ขณะนี้ปิดรับสมัคร กรุณาติดตามประกาศของโรงเรียน';
        }
        if ($c['open_from'] && $today < $c['open_from']) {
            return 'เปิดรับสมัครวันที่ '.thai_date(Carbon::parse($c['open_from']));
        }
        if ($c['open_until'] && $today > $c['open_until']) {
            return 'ปิดรับสมัครแล้วเมื่อวันที่ '.thai_date(Carbon::parse($c['open_until']));
        }

        return null;
    }

    /** ระดับชั้นที่ใบสมัครครบจำนวนแล้ว (นับเฉพาะใบที่ส่งแล้วและไม่ใช่ "ไม่ผ่าน") */
    public static function fullLevels(int $year, ?array $c = null): array
    {
        $c ??= self::config();
        if (! $c['caps']) {
            return [];
        }
        $counts = Admission::where('year', $year)->whereNotIn('status', ['rejected', 'draft'])->selectRaw('level, count(*) c')->groupBy('level')->pluck('c', 'level');

        return array_keys(array_filter($c['caps'], fn ($cap, $level) => ($counts[$level] ?? 0) >= $cap, ARRAY_FILTER_USE_BOTH));
    }

    public static function fee(string $level, ?array $c = null): float
    {
        return (float) (($c ?? self::config())['fees'][$level] ?? 0);
    }

    /* ================================================================ ขั้นตอน */

    public static function questionsFor(string $level, ?array $c = null, ?string $step = null): array
    {
        $c ??= self::config();

        return array_values(array_filter($c['questions'], fn ($q) => (! $q['levels'] || in_array($level, $q['levels'], true)) && (! $step || $q['step'] === $step)));
    }

    /** ช่องของระบบที่แสดงในขั้นนี้ (ตัดช่องที่ตั้งเป็นซ่อน) */
    public static function stepFields(string $step, ?array $c = null): array
    {
        $c ??= self::config();

        return array_values(array_filter(self::STEPS[$step][2], fn ($f) => ($c['optional'][$f] ?? 'show') !== 'hidden'));
    }

    /** ขั้นที่มีอะไรให้กรอกสำหรับชั้นนี้ (ขั้นว่างข้ามไป) */
    public static function steps(string $level, ?array $c = null): array
    {
        $c ??= self::config();

        return array_values(array_filter(array_keys(self::STEPS), fn ($s) => self::stepFields($s, $c) || self::questionsFor($level, $c, $s)));
    }

    /**
     * กฎตรวจของขั้นหนึ่ง · $strict = false (กด "ย้อนกลับ"/"บันทึกไว้ก่อน") ตรวจแค่รูปแบบ ไม่บังคับกรอก
     * $answers = คำตอบเดิม (ไฟล์ที่แนบไว้แล้วไม่ต้องแนบซ้ำ)
     */
    public static function stepRules(string $step, string $level, array $answers, bool $strict, ?array $c = null): array
    {
        $c ??= self::config();
        $req = fn (bool $required) => $strict && $required ? 'required' : 'nullable';
        $rules = [];
        foreach (self::stepFields($step, $c) as $f) {
            if ($f === 'level') {
                continue; // ตรวจใน controller (ชั้นที่เปิด/ไม่เต็ม)
            }
            $rules[$f] = isset(self::CORE[$f])
                ? array_merge([$req(true)], array_slice(self::CORE[$f], 1))
                : array_merge([$req($c['optional'][$f] === 'required')], self::OPTIONAL_RULES[$f]);
        }
        $has = collect($answers)->keyBy('id');
        foreach (self::questionsFor($level, $c, $step) as $q) {
            $key = 'answers.'.$q['id'];
            $r = $req($q['required'] && ! ($q['type'] === 'file' && $has->has($q['id'])));
            $rules[$key] = match ($q['type']) {
                'number' => [$r, 'numeric', 'between:-999999999,999999999'],
                'date' => [$r, 'date'],
                'select', 'radio' => [$r, 'string', Rule::in($q['options'])],
                'checkbox' => [$r, 'array', 'max:50'],
                // รูปถ่ายที่พิมพ์ลงเอกสารต้องเป็นรูปภาพ
                'file' => [$r, 'file', $q['photo'] ? 'mimes:jpg,jpeg,png' : 'mimes:jpg,jpeg,png,pdf', 'max:'.self::FILE_MAX_KB],
                'textarea' => [$r, 'string', 'max:3000'],
                default => [$r, 'string', 'max:500'],
            };
            if ($q['type'] === 'checkbox') {
                $rules[$key.'.*'] = ['string', Rule::in($q['options'])];
            }
        }

        return $rules;
    }

    /** ป้ายชื่อช่องในข้อความผิดพลาด */
    public static function attributes(string $level, ?array $c = null): array
    {
        $out = self::LABELS;
        foreach (self::OPTIONAL_FIELDS as $f => [$label]) {
            $out[$f] = $label;
        }
        foreach (self::questionsFor($level, $c) as $q) {
            $out['answers.'.$q['id']] = $q['label'];
            $out['answers.'.$q['id'].'.*'] = $q['label'];
        }

        return $out;
    }

    /**
     * รวมคำตอบของขั้นนี้เข้ากับคำตอบเดิม (snapshot ข้อความคำถาม ณ ตอนนี้) · ไฟล์เก็บบนดิสก์ส่วนตัว
     * ไฟล์: แนบใหม่ = แทนที่ (ลบไฟล์เก่า) · ไม่แนบ = ใช้ไฟล์เดิม · $remove[id] = ลบไฟล์
     *
     * @return list<array{id:string, label:string, type:string, value:mixed}>
     */
    public static function mergeAnswers(array $existing, string $step, string $level, array $input, array $remove = [], ?array $c = null): array
    {
        $c ??= self::config();
        $old = collect($existing)->keyBy('id');
        $out = $old->all();
        foreach (self::questionsFor($level, $c, $step) as $q) {
            $v = $input[$q['id']] ?? null;
            $prev = $old->get($q['id']);
            if ($q['type'] === 'file') {
                if ($v instanceof UploadedFile || ! empty($remove[$q['id']])) {
                    if ($prev && ! empty($prev['value']['path'])) {
                        Storage::disk('local')->delete($prev['value']['path']);
                    }
                    $v = $v instanceof UploadedFile ? ['path' => $v->store('admissions', 'local'), 'name' => Str::limit($v->getClientOriginalName(), 120, '')] : null;
                } else {
                    $v = $prev['value'] ?? null;
                }
            } elseif ($q['type'] === 'checkbox') {
                $v = array_values(array_intersect($q['options'], (array) $v)); // เรียงตามลำดับตัวเลือก
            } elseif (is_string($v)) {
                $v = trim($v);
            }
            if ($v === null || $v === '' || $v === []) {
                unset($out[$q['id']]);

                continue;
            }
            $out[$q['id']] = ['id' => $q['id'], 'label' => $q['label'], 'type' => $q['type'], 'value' => $v] + ($q['photo'] ? ['photo' => true] : []);
        }
        // เรียงตามลำดับคำถามในฟอร์ม (คำตอบของคำถามที่ถูกลบไปแล้วอยู่ท้าย)
        $order = array_flip(array_column($c['questions'], 'id'));

        return collect($out)->sortBy(fn ($a) => $order[$a['id']] ?? PHP_INT_MAX)->values()->all();
    }

    /**
     * ตรวจความครบก่อนส่งใบสมัคร (ใช้ข้อมูลในร่าง ไม่ใช่ข้อมูลจากฟอร์ม)
     *
     * @return array{step: string, errors: array}|null ขั้นแรกที่ยังไม่ครบ
     */
    public static function incomplete(Admission $a, ?array $c = null): ?array
    {
        $c ??= self::config();
        $answers = collect($a->answers ?? [])->mapWithKeys(fn ($x) => [$x['id'] => $x['type'] === 'file' ? 'แนบแล้ว' : $x['value']])->all();
        $data = $a->only(['prefix', 'first_name', 'last_name', 'nickname', 'gender', 'previous_school', 'gpa', 'parent_name', 'relation', 'address', 'note'])
            + ['answers' => $answers];
        foreach (self::steps($a->level, $c) as $step) {
            $rules = self::stepRules($step, $a->level, [], true, $c);
            foreach (self::questionsFor($a->level, $c, $step) as $q) {
                if ($q['type'] === 'file') {
                    $rules['answers.'.$q['id']] = [$q['required'] ? 'required' : 'nullable']; // เก็บเป็นข้อความ "แนบแล้ว" ในการตรวจนี้
                }
            }
            $v = Validator::make($data, $rules, [], self::attributes($a->level, $c));
            if ($v->fails()) {
                return ['step' => $step, 'errors' => $v->errors()->toArray()];
            }
        }

        return null;
    }

    /* ================================================================ แสดงผล */

    /** ค่าคำตอบเป็นข้อความสำหรับแสดง/ส่งออก */
    public static function display(array $answer): string
    {
        return match ($answer['type']) {
            'checkbox' => implode(', ', (array) $answer['value']),
            'file' => (string) ($answer['value']['name'] ?? 'ไฟล์แนบ'),
            'date' => strtotime((string) $answer['value']) ? thai_date(Carbon::parse($answer['value'])) : (string) $answer['value'],
            default => (string) $answer['value'],
        };
    }

    public static function isSensitive(string $label): bool
    {
        foreach (self::SENSITIVE as $w) {
            if (str_contains($label, $w)) {
                return true;
            }
        }

        return false;
    }
}
