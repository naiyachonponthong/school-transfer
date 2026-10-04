<?php

namespace App\Http\Controllers;

use App\Models\ConsentForm;
use App\Models\ConsentResponse;
use App\Models\GateDevice;
use App\Models\GateEvent;
use App\Models\Student;
use App\Models\User;
use App\Services\GateRecorder;
use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * เครื่องสแกนใบหน้า/บัตรที่ประตู: จัดการเครื่อง (ได้หลายเครื่อง) และรับเหตุการณ์ที่เครื่องส่งเข้ามา
 *
 * เครื่องระบุตัวนักเรียนเองแล้วส่ง "รหัสนักเรียน" มาให้ ระบบบันทึกเข้า-ออกผ่าน GateRecorder เหมือนการสแกน QR
 * ถ้ารหัสเป็นรหัสบุคลากร (หรือชื่อผู้ใช้) ของครู/บุคลากร จะลงเป็นเวลาทำงานครูแทน
 * รูปแบบที่รับ: JSON ทั่วไป {code, time?, mode?} หรือเหตุการณ์ AccessControllerEvent ของ Hikvision (ISAPI HTTP listening)
 */
class GateDeviceController extends Controller
{
    public function index()
    {
        $today = today()->startOfDay();

        return view('gate.devices', [
            'devices' => GateDevice::withCount(['events as today_count' => fn ($q) => $q->where('occurred_at', '>=', $today)->whereIn('result', ['present', 'late', 'out'])])
                ->orderBy('name')->get(),
            'events' => GateEvent::with(['device', 'student.classroom', 'user'])->latest('occurred_at')->limit(40)->get(),
            'faces' => $this->faceStatus(),
            'staff' => $this->staffStatus(),
            'consentForms' => ConsentForm::latest()->get(['id', 'title']),
            'unknownToday' => GateEvent::where('result', 'unknown')->where('occurred_at', '>=', $today)->count(),
        ]);
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'mode' => ['required', Rule::in(array_keys(GateDevice::MODES))],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(), [], ['name' => 'ชื่อเครื่อง', 'mode' => 'ทิศทาง']);
        $device = GateDevice::create(['token' => Str::random(48), 'is_active' => true, 'created_by' => $request->user()->id] + $data);
        Audit::log('setting.update', $device, "เพิ่มเครื่องสแกน {$device->name}");

        return back()->with('success', "เพิ่มเครื่อง {$device->name} แล้ว คัดลอกที่อยู่รับข้อมูลไปตั้งในเครื่อง");
    }

    public function update(Request $request, GateDevice $device)
    {
        $data = $request->validate($this->rules(), [], ['name' => 'ชื่อเครื่อง', 'mode' => 'ทิศทาง']);
        $device->update(['is_active' => $request->boolean('is_active')] + $data);

        return back()->with('success', 'บันทึกเครื่องแล้ว');
    }

    /** ออกที่อยู่รับข้อมูลใหม่ (ที่อยู่เดิมใช้ไม่ได้ทันที) เช่น เมื่อสงสัยว่าที่อยู่รั่ว */
    public function rotate(GateDevice $device)
    {
        $device->update(['token' => Str::random(48)]);
        Audit::log('setting.update', $device, "ออกที่อยู่รับข้อมูลใหม่ของเครื่องสแกน {$device->name}");

        return back()->with('success', 'ออกที่อยู่รับข้อมูลใหม่แล้ว ต้องนำไปตั้งในเครื่องอีกครั้ง');
    }

    public function destroy(GateDevice $device)
    {
        Audit::log('setting.update', $device, "ลบเครื่องสแกน {$device->name}");
        $device->delete();

        return back()->with('success', 'ลบเครื่องแล้ว');
    }

    /** ส่งเหตุการณ์จำลอง (ทดสอบโดยไม่ต้องมีเครื่องจริง) ผ่านเส้นทางเดียวกับเครื่องจริง */
    public function simulate(Request $request, GateDevice $device)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:60']], [], ['code' => 'รหัส']);
        $event = $this->handle($device, ['code' => $data['code']]);
        [$label] = GateEvent::RESULTS[$event->result];

        return back()->with($event->result === 'unknown' ? 'warning' : 'success',
            "ทดสอบ {$device->name}: {$label}".' · '.($event->student?->fullName() ?? ($event->user ? $event->user->name.' (ครู/บุคลากร)' : "รหัส {$data['code']}")));
    }

    /* ---------------- รูปใบหน้าสำหรับลงทะเบียนในเครื่อง ---------------- */

    /** เลือกหนังสือขออนุญาตที่ใช้เป็นความยินยอมให้ใช้ใบหน้า (ใบหน้าเป็นข้อมูลชีวภาพ ต้องได้รับความยินยอมก่อน) */
    public function consent(Request $request)
    {
        $data = $request->validate(['consent_form_id' => ['nullable', 'integer', 'exists:consent_forms,id']]);
        Settings::set(['gate_face_consent_form_id' => $data['consent_form_id'] ?? '']);

        return back()->with('success', 'บันทึกหนังสือยินยอมสำหรับการสแกนใบหน้าแล้ว');
    }

    /**
     * นักเรียนที่ผู้ปกครองยินยอมแล้ว แยกเป็นพร้อมส่งออก (มีรูป) กับยังไม่มีรูป
     *
     * @return array{form: ?ConsentForm, ready: \Illuminate\Support\Collection, noPhoto: \Illuminate\Support\Collection, total: int}
     */
    private function faceStatus(): array
    {
        $form = ConsentForm::find((int) Settings::get('gate_face_consent_form_id'));
        $agreed = $form ? ConsentResponse::where('consent_form_id', $form->id)->where('agreed', true)->pluck('student_id') : collect();
        $students = Student::active()->with('classroom')->whereIn('id', $agreed)->orderBy('classroom_id')->orderBy('number')->get();
        [$ready, $noPhoto] = $students->partition(fn (Student $s) => $s->photo && Storage::disk('public')->exists($s->photo));

        return ['form' => $form, 'ready' => $ready->values(), 'noPhoto' => $noPhoto->values(), 'total' => Student::active()->count()];
    }

    /** ผู้ดูแลบันทึกว่าบุคลากรคนใดยินยอมให้ใช้ใบหน้าแล้ว (โรงเรียนเก็บเอกสารยินยอมเอง) */
    public function staffConsent(Request $request)
    {
        $ids = collect($request->input('staff', []))->map(fn ($v) => (int) $v)->all();
        $staff = User::whereIn('role', ['admin', 'teacher'])->where('is_active', true);
        (clone $staff)->whereIn('id', $ids)->whereNull('face_consent_at')->update(['face_consent_at' => now()]);
        (clone $staff)->whereNotIn('id', $ids)->whereNotNull('face_consent_at')->update(['face_consent_at' => null]);
        Audit::log('setting.update', null, 'บันทึกความยินยอมใช้ใบหน้าของบุคลากร '.count($ids).' คน');

        return back()->with('success', 'บันทึกความยินยอมของบุคลากรแล้ว');
    }

    /**
     * บุคลากร: ทั้งหมด (สำหรับติ๊กความยินยอม) · ยินยอมและมีรูป · ยินยอมแต่ไม่มีรูป · ชื่อผู้ใช้ที่ซ้ำกับรหัสนักเรียน
     *
     * @return array{all: \Illuminate\Support\Collection, ready: \Illuminate\Support\Collection, noPhoto: \Illuminate\Support\Collection, clashes: \Illuminate\Support\Collection}
     */
    private function staffStatus(): array
    {
        $all = User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->orderBy('name')->get();
        [$ready, $noPhoto] = $all->whereNotNull('face_consent_at')
            ->partition(fn (User $u) => $u->avatar && Storage::disk('public')->exists($u->avatar));
        $codes = $all->map->gateCode();
        $taken = Student::whereIn('student_code', $codes)->pluck('student_code')->all();
        $clashes = $all->filter(fn (User $u) => in_array($u->gateCode(), $taken, true));

        return ['all' => $all, 'ready' => $ready->values(), 'noPhoto' => $noPhoto->values(), 'clashes' => $clashes->values()];
    }

    /** ไฟล์ zip รูปนักเรียน (ตั้งชื่อตามรหัสนักเรียน) + รายชื่อ สำหรับนำเข้าเครื่องสแกนผ่านโปรแกรมของผู้ผลิต */
    public function faces()
    {
        $status = $this->faceStatus();
        $staff = $this->staffStatus()['ready'];
        if (! $status['form'] && $staff->isEmpty()) {
            return back()->with('warning', 'เลือกหนังสือยินยอมสำหรับการสแกนใบหน้าก่อน จึงจะส่งออกรูปได้');
        }
        if ($status['ready']->isEmpty() && $staff->isEmpty()) {
            return back()->with('warning', 'ยังไม่มีนักเรียนหรือบุคลากรที่ยินยอมและมีรูปในระบบ');
        }
        if (! class_exists(\ZipArchive::class)) {
            return back()->with('warning', 'เซิร์ฟเวอร์ไม่มีส่วนเสริม zip ของ PHP จึงสร้างไฟล์ไม่ได้');
        }

        @set_time_limit(300);
        $dir = storage_path('app/tmp');
        is_dir($dir) || mkdir($dir, 0775, true);
        $file = $dir.'/faces-'.Str::random(12).'.zip';
        $zip = new \ZipArchive;
        abort_unless($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true, 500);

        $rows = [['employee_no', 'name', 'classroom', 'number', 'photo']];
        foreach ($status['ready'] as $s) {
            [$bytes, $ext] = self::facePhoto(Storage::disk('public')->get($s->photo), pathinfo($s->photo, PATHINFO_EXTENSION));
            $name = $s->student_code.'.'.$ext;
            $zip->addFromString('photos/'.$name, $bytes);
            $rows[] = [$s->student_code, $s->fullName(), $s->classroom?->name(), $s->number, $name];
        }
        $csv = "\u{FEFF}".implode("\r\n", array_map(fn (array $r) => implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $r)), $rows))."\r\n";
        $zip->addFromString('students.csv', $csv);
        if ($staff->isNotEmpty()) {
            $rows = [['employee_no', 'name', 'position', 'photo']];
            foreach ($staff as $u) {
                [$bytes, $ext] = self::facePhoto(Storage::disk('public')->get($u->avatar), pathinfo($u->avatar, PATHINFO_EXTENSION));
                $name = $u->gateCode().'.'.$ext;
                $zip->addFromString('staff/'.$name, $bytes);
                $rows[] = [$u->gateCode(), $u->name, $u->position, $name];
            }
            $zip->addFromString('staff.csv', "\u{FEFF}".implode("\r\n", array_map(fn (array $r) => implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $r)), $rows))."\r\n");
        }
        $zip->addFromString('README.txt', implode("\r\n", [
            'รูปใบหน้าสำหรับลงทะเบียนในเครื่องสแกน',
            'ส่งออกเมื่อ '.now()->format('Y-m-d H:i').' นักเรียน '.$status['ready']->count().' คน บุคลากร '.$staff->count().' คน (เฉพาะผู้ที่ยินยอมและมีรูปในระบบ)',
            '',
            '- photos/  รูปของนักเรียนแต่ละคน ชื่อไฟล์ = รหัสนักเรียน',
            '- students.csv  รายชื่อ: employee_no (รหัสนักเรียน), ชื่อ, ห้อง, เลขที่, ชื่อไฟล์รูป',
            '- staff/ และ staff.csv  ครูและบุคลากร (ถ้ามี) ชื่อไฟล์และ employee_no = รหัสบุคลากร (ถ้ายังไม่มีรหัสใช้ชื่อผู้ใช้)',
            '',
            'ตอนเพิ่มบุคคลในเครื่อง/โปรแกรมของผู้ผลิต ให้ใช้รหัสบุคคล (Employee No.) = employee_no เสมอ',
            'ไฟล์นี้เป็นข้อมูลส่วนบุคคล ลบทิ้งเมื่อนำเข้าเครื่องเสร็จแล้ว',
        ])."\r\n");
        $zip->close();
        Audit::log('setting.update', null, 'ส่งออกรูปใบหน้าสำหรับเครื่องสแกน นักเรียน '.$status['ready']->count().' คน บุคลากร '.$staff->count().' คน');

        return response()->download($file, 'gate-faces-'.now()->format('Ymd-Hi').'.zip')->deleteFileAfterSend();
    }

    /** แปลงรูปเป็น JPEG ด้านยาวไม่เกิน 960px (เครื่องสแกนส่วนใหญ่รับ JPG ขนาดเล็ก) ถ้าแปลงไม่ได้ใช้ไฟล์เดิม */
    private static function facePhoto(string $bytes, string $ext): array
    {
        $img = function_exists('imagecreatefromstring') ? @imagecreatefromstring($bytes) : false;
        if (! $img) {
            return [$bytes, strtolower($ext) ?: 'jpg'];
        }
        $scale = min(1, 960 / max(imagesx($img), imagesy($img)));
        $w = max(1, (int) round(imagesx($img) * $scale));
        $h = max(1, (int) round(imagesy($img) * $scale));
        $out = imagecreatetruecolor($w, $h);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255)); // พื้นโปร่งใสของ PNG ให้เป็นสีขาว
        imagecopyresampled($out, $img, 0, 0, 0, 0, $w, $h, imagesx($img), imagesy($img));
        ob_start();
        imagejpeg($out, null, 85);

        return [ob_get_clean(), 'jpg'];
    }

    /* ---------------- รับข้อมูลจากเครื่อง (ไม่ต้องเข้าสู่ระบบ ยืนยันด้วย token ในที่อยู่) ---------------- */

    public function hook(Request $request, string $token)
    {
        $device = GateDevice::where('token', $token)->first();
        abort_unless($device, 404);
        if (! $device->is_active) {
            return response()->json(['ok' => false, 'message' => 'device disabled'], 403);
        }

        $payload = $this->payload($request);
        $device->forceFill(['last_seen_at' => now()])->save();
        // เหตุการณ์ที่ไม่มีรหัสบุคคล (สัญญาณตรวจการเชื่อมต่อ ประตูเปิด สแกนไม่ผ่าน ฯลฯ) ไม่ต้องบันทึก
        if (self::code($payload) === null) {
            return response()->json(['ok' => true, 'result' => 'ignored']);
        }
        $event = $this->handle($device, $payload);

        return response()->json(['ok' => true, 'result' => $event->result]);
    }

    /** ข้อมูลจากเครื่องมาได้ทั้ง JSON ตรง ๆ และ multipart ที่มีช่องข้อความเป็น JSON (แบบ Hikvision) */
    private function payload(Request $request): array
    {
        if ($json = $request->json()->all()) {
            return $json;
        }
        foreach ($request->post() as $value) {
            if (is_string($value) && str_starts_with(ltrim($value), '{') && is_array($decoded = json_decode($value, true))) {
                return $decoded;
            }
        }

        return $request->post();
    }

    private static function code(array $payload): ?string
    {
        $ace = is_array($payload['AccessControllerEvent'] ?? null) ? $payload['AccessControllerEvent'] : [];
        $code = $payload['code'] ?? $payload['employee_no'] ?? $ace['employeeNoString'] ?? $ace['employeeNo'] ?? null;
        $code = is_scalar($code) ? trim((string) $code) : '';

        return $code === '' ? null : mb_substr($code, 0, 60);
    }

    private function handle(GateDevice $device, array $payload): GateEvent
    {
        $code = self::code($payload);
        $at = $this->time($payload['time'] ?? $payload['dateTime'] ?? null);
        $student = Student::with('classroom')->active()
            ->where(fn ($q) => $q->where('student_code', $code)->orWhere('qr_token', $code))->first();

        $mode = $device->mode === 'auto' ? (in_array($payload['mode'] ?? null, ['in', 'out'], true) ? $payload['mode'] : null) : $device->mode;
        if ($student) {
            $result = GateRecorder::record($student, $mode, $device->created_by, $at);

            return $device->events()->create(['student_id' => $student->id, 'code' => $code, 'result' => $result['kind'], 'occurred_at' => $at]);
        }

        // ไม่ใช่นักเรียน: รหัสบุคลากร (หรือชื่อผู้ใช้) ของครู/บุคลากร = ลงเวลาทำงาน
        $staff = User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)
            ->where(fn ($q) => $q->where('staff_code', $code)->orWhere('username', $code))->orderByRaw('staff_code = ? desc', [$code])->first();
        if ($staff) {
            $result = GateRecorder::recordStaff($staff, $mode, $at);

            return $device->events()->create(['user_id' => $staff->id, 'code' => $code, 'result' => $result['kind'], 'occurred_at' => $at]);
        }

        return $device->events()->create(['code' => $code, 'result' => 'unknown', 'occurred_at' => $at, 'payload' => self::trim($payload)]);
    }

    /** เวลาจากเครื่อง: ใช้เมื่ออ่านได้และห่างจากเวลาของระบบไม่เกิน 1 ชั่วโมง (นาฬิกาเครื่องเพี้ยนบ่อย) */
    private function time(mixed $value): Carbon
    {
        try {
            $at = is_string($value) && $value !== '' ? Carbon::parse($value)->setTimezone(config('app.timezone')) : null;
        } catch (\Throwable) {
            $at = null;
        }

        return $at && abs($at->diffInMinutes(now())) <= 60 ? $at : now();
    }

    /** เก็บข้อมูลดิบแบบย่อ (ไม่เก็บรูปหรือข้อความยาว) */
    private static function trim(array $payload): array
    {
        $flat = [];
        array_walk_recursive($payload, function ($v, $k) use (&$flat) {
            if (count($flat) < 30 && is_scalar($v) && strlen((string) $v) <= 120) {
                $flat[$k] = $v;
            }
        });

        return $flat;
    }
}
