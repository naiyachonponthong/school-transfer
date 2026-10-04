<?php

namespace App\Http\Controllers;

use App\Models\GateDevice;
use App\Models\GateEvent;
use App\Models\Student;
use App\Services\GateRecorder;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * เครื่องสแกนใบหน้า/บัตรที่ประตู: จัดการเครื่อง (ได้หลายเครื่อง) และรับเหตุการณ์ที่เครื่องส่งเข้ามา
 *
 * เครื่องระบุตัวนักเรียนเองแล้วส่ง "รหัสนักเรียน" มาให้ ระบบบันทึกเข้า-ออกผ่าน GateRecorder เหมือนการสแกน QR
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
            'events' => GateEvent::with(['device', 'student.classroom'])->latest('occurred_at')->limit(40)->get(),
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
        $data = $request->validate(['code' => ['required', 'string', 'max:60']], [], ['code' => 'รหัสนักเรียน']);
        $event = $this->handle($device, ['code' => $data['code']]);
        [$label] = GateEvent::RESULTS[$event->result];

        return back()->with($event->result === 'unknown' ? 'warning' : 'success',
            "ทดสอบ {$device->name}: {$label}".($event->student ? ' · '.$event->student->fullName() : " · รหัส {$data['code']}"));
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

        if (! $student) {
            return $device->events()->create(['code' => $code, 'result' => 'unknown', 'occurred_at' => $at, 'payload' => self::trim($payload)]);
        }

        $mode = $device->mode === 'auto' ? (in_array($payload['mode'] ?? null, ['in', 'out'], true) ? $payload['mode'] : null) : $device->mode;
        $result = GateRecorder::record($student, $mode, $device->created_by, $at);

        return $device->events()->create(['student_id' => $student->id, 'code' => $code, 'result' => $result['kind'], 'occurred_at' => $at]);
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
