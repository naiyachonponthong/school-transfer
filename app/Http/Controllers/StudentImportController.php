<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Student;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * นำเข้านักเรียนจาก CSV หรือ "คัดลอกจาก Excel แล้ววาง" ก็ได้
 * รหัสนักเรียนซ้ำ = อัปเดตข้อมูลเดิม
 */
class StudentImportController extends Controller
{
    public const COLUMNS = [
        'student_code' => 'รหัสนักเรียน',
        'prefix' => 'คำนำหน้า',
        'first_name' => 'ชื่อ',
        'last_name' => 'นามสกุล',
        'nickname' => 'ชื่อเล่น',
        'gender' => 'เพศ',
        'birthdate' => 'วันเกิด',
        'classroom' => 'ห้อง',
        'number' => 'เลขที่',
        'citizen_id' => 'เลขประจำตัวประชาชน',
        'guardian_name' => 'ชื่อผู้ปกครอง',
        'guardian_phone' => 'เบอร์ผู้ปกครอง',
        'relation' => 'ความสัมพันธ์',
    ];

    public function form()
    {
        return view('students.import', ['columns' => self::COLUMNS]);
    }

    public function template()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_values(self::COLUMNS));
            fputcsv($out, ['10001', 'เด็กชาย', 'สมชาย', 'ใจดี', 'ต้น', 'ช', '15/05/2557', 'ป.4/1', '1', '', 'นายสมศักดิ์ ใจดี', '0812345678', 'บิดา']);
            fclose($out);
        }, 'แบบฟอร์มนำเข้านักเรียน.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['nullable', 'file', 'mimes:csv,txt', 'max:5120'],
            'paste' => ['nullable', 'string'],
        ]);

        $text = $request->hasFile('file') ? file_get_contents($request->file('file')->getRealPath()) : (string) $request->input('paste');
        $text = $this->toUtf8($text);
        $rows = $this->parse($text);

        if (count($rows) < 2) {
            return back()->withErrors(['file' => 'ไม่พบข้อมูล กรุณาเลือกไฟล์หรือวางข้อมูลที่มีหัวตาราง']);
        }

        $header = array_map(fn ($h) => trim((string) $h), array_shift($rows));
        $map = $this->mapHeader($header);
        if (! isset($map['student_code'], $map['first_name'])) {
            return back()->withErrors(['file' => 'หัวตารางต้องมีอย่างน้อย "รหัสนักเรียน" และ "ชื่อ"']);
        }

        $year = Term::current()?->year ?? (now()->year + 543);
        $result = ['created' => 0, 'updated' => 0, 'guardians' => 0, 'errors' => []];

        DB::transaction(function () use ($rows, $map, $year, &$result) {
            foreach ($rows as $i => $row) {
                $line = $i + 2;
                $get = fn ($key) => isset($map[$key]) ? trim((string) ($row[$map[$key]] ?? '')) : '';

                $code = $get('student_code');
                $first = $get('first_name');
                if ($code === '' && $first === '') {
                    continue;
                }
                if ($code === '' || $first === '') {
                    $result['errors'][] = "แถว {$line}: ไม่มีรหัสหรือชื่อ";

                    continue;
                }

                $classroomId = null;
                if ($room = $get('classroom')) {
                    if (preg_match('/^(.+?)\s*\/\s*(\d+)$/u', $room, $m)) {
                        $classroomId = Classroom::firstOrCreate(['year' => $year, 'level' => trim($m[1]), 'room' => (int) $m[2]])->id;
                    } else {
                        $result['errors'][] = "แถว {$line}: รูปแบบห้อง \"{$room}\" ไม่ถูกต้อง (ตัวอย่าง ม.1/2)";
                    }
                }

                $gender = match (mb_substr($get('gender'), 0, 1)) {
                    'ช', 'M', 'm' => 'M',
                    'ญ', 'F', 'f' => 'F',
                    default => null,
                };
                $prefix = $get('prefix');
                $gender ??= match (true) {
                    in_array($prefix, ['เด็กชาย', 'ด.ช.', 'นาย'], true) => 'M',
                    in_array($prefix, ['เด็กหญิง', 'ด.ญ.', 'นางสาว', 'น.ส.'], true) => 'F',
                    default => null,
                };

                $values = array_filter([
                    'prefix' => $prefix ?: null,
                    'first_name' => $first,
                    'last_name' => $get('last_name') ?: '-',
                    'nickname' => $get('nickname') ?: null,
                    'gender' => $gender,
                    'birthdate' => $this->parseDate($get('birthdate')),
                    'classroom_id' => $classroomId,
                    'number' => ctype_digit($get('number')) ? (int) $get('number') : null,
                    'citizen_id' => preg_replace('/\D/', '', $get('citizen_id')) ?: null,
                ], fn ($v) => $v !== null);

                $student = Student::firstOrNew(['student_code' => $code]);
                $student->exists ? $result['updated']++ : $result['created']++;
                $student->fill($values + ['status' => $student->status ?? 'active'])->save();

                $phone = preg_replace('/\D/', '', $get('guardian_phone'));
                if ($phone !== '') {
                    $parent = User::firstOrCreate(
                        ['phone' => $phone, 'role' => 'parent'],
                        ['name' => $get('guardian_name') ?: 'ผู้ปกครอง '.$first, 'username' => $phone, 'password' => Hash::make(substr($phone, -6)), 'must_change_password' => true]
                    );
                    if ($parent->wasRecentlyCreated) {
                        $result['guardians']++;
                    }
                    $student->guardians()->syncWithoutDetaching([$parent->id => ['relation' => $get('relation') ?: null]]);
                }
            }
        });

        $msg = "นำเข้าเสร็จ: เพิ่มใหม่ {$result['created']} คน, อัปเดต {$result['updated']} คน";
        if ($result['guardians']) {
            $msg .= ", สร้างบัญชีผู้ปกครอง {$result['guardians']} บัญชี (รหัสผ่าน = 6 หลักท้ายเบอร์โทร)";
        }

        return redirect()->route('students.import')->with('success', $msg)->with('import_errors', $result['errors']);
    }

    private function toUtf8(string $text): string
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (! mb_check_encoding($text, 'UTF-8')) {
            $converted = @iconv('CP874', 'UTF-8//IGNORE', $text) ?: @iconv('TIS-620', 'UTF-8//IGNORE', $text);
            $text = $converted ?: $text;
        }

        return $text;
    }

    /** @return list<list<string>> */
    private function parse(string $text): array
    {
        $firstLine = strtok($text, "\r\n") ?: '';
        $delimiter = substr_count($firstLine, "\t") > substr_count($firstLine, ',') ? "\t" : ',';

        $rows = [];
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $text);
        rewind($fh);
        while (($row = fgetcsv($fh, 0, $delimiter, '"', '')) !== false) {
            if ($row === [null]) {
                continue;
            }
            $rows[] = $row;
        }
        fclose($fh);

        return $rows;
    }

    private function mapHeader(array $header): array
    {
        $aliases = [
            'student_code' => ['รหัสนักเรียน', 'รหัส', 'เลขประจำตัว', 'เลขประจำตัวนักเรียน', 'student_code'],
            'prefix' => ['คำนำหน้า', 'คำนำหน้าชื่อ', 'prefix'],
            'first_name' => ['ชื่อ', 'first_name'],
            'last_name' => ['นามสกุล', 'สกุล', 'last_name'],
            'nickname' => ['ชื่อเล่น', 'nickname'],
            'gender' => ['เพศ', 'gender'],
            'birthdate' => ['วันเกิด', 'วันเดือนปีเกิด', 'birthdate'],
            'classroom' => ['ห้อง', 'ชั้น/ห้อง', 'ห้องเรียน', 'classroom'],
            'number' => ['เลขที่', 'number'],
            'citizen_id' => ['เลขประจำตัวประชาชน', 'เลขบัตรประชาชน', 'citizen_id'],
            'guardian_name' => ['ชื่อผู้ปกครอง', 'ผู้ปกครอง', 'guardian_name'],
            'guardian_phone' => ['เบอร์ผู้ปกครอง', 'เบอร์โทรผู้ปกครอง', 'โทรศัพท์ผู้ปกครอง', 'guardian_phone'],
            'relation' => ['ความสัมพันธ์', 'relation'],
        ];

        $map = [];
        foreach ($header as $idx => $name) {
            foreach ($aliases as $key => $names) {
                if (! isset($map[$key]) && in_array($name, $names, true)) {
                    $map[$key] = $idx;
                }
            }
        }

        return $map;
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{4})$#', $value, $m)) {
            $y = (int) $m[3];
            $y = $y > 2400 ? $y - 543 : $y;

            return checkdate((int) $m[2], (int) $m[1], $y) ? sprintf('%04d-%02d-%02d', $y, $m[2], $m[1]) : null;
        }
        try {
            $d = Carbon::parse($value);

            return ($d->year > 2400 ? $d->subYears(543) : $d)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
