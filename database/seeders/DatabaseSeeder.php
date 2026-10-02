<?php

namespace Database\Seeders;

use App\Http\Controllers\CourseController;
use App\Models\Admission;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\HealthMeasurement;
use App\Models\HealthVisit;
use App\Models\SchoolEvent;
use App\Models\StaffLeave;
use App\Models\Announcement;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\BehaviorRecord;
use App\Models\BehaviorRule;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\CourseResult;
use App\Models\FeedPost;
use App\Models\FeedReaction;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\Payment;
use App\Models\PeriodAttendance;
use App\Models\Score;
use App\Models\StaffAttendance;
use App\Models\Student;
use App\Models\StudentEvaluation;
use App\Models\Subject;
use App\Models\Term;
use App\Models\TimetableSlot;
use App\Models\User;
use App\Support\Evaluation;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * ข้อมูลตัวอย่างสำหรับทดลองใช้งาน
 *   ผู้ดูแล   admin / admin1234
 *   ครู      teacher / teacher1234  (ครูประจำชั้น ม.1/1 สอนคณิตศาสตร์)
 *   ผู้ปกครอง 0812345678 / 345678
 *   นักเรียน  69001 / student1234  (ลูกของผู้ปกครองตัวอย่าง ม.1/1)
 */
class DatabaseSeeder extends Seeder
{
    private array $boys = ['ธนกร', 'ภูมิพัฒน์', 'กิตติพัศ', 'ณัฐวุฒิ', 'ปัณณวิชญ์', 'ธีรภัทร', 'ศุภกฤต', 'พีรพัฒน์', 'ชยพล', 'อชิรวัฒน์', 'วรเมธ', 'กฤษฎา', 'ภาคิน', 'ธนภัทร', 'สิรวิชญ์', 'ปกรณ์', 'ณภัทร', 'รัชชานนท์'];

    private array $girls = ['ณัฐธิดา', 'ปุณยาพร', 'กัญญาณัฐ', 'พิมพ์ชนก', 'ชนัญชิดา', 'ธัญชนก', 'สุพิชญา', 'วรัญญา', 'อริสรา', 'ปวีณ์ธิดา', 'ภัทรวดี', 'ณิชาภัทร', 'กมลชนก', 'ศิริกานดา', 'พรนภัส', 'รินรดา', 'ขวัญข้าว', 'มนัสนันท์'];

    private array $lastNames = ['ใจดี', 'สุขสวัสดิ์', 'วงศ์ไทย', 'ศรีสมบูรณ์', 'แก้วประเสริฐ', 'ทองมา', 'บุญมี', 'พรหมวงศ์', 'จันทร์เพ็ญ', 'รัตนพันธ์', 'มั่นคง', 'เพชรรัตน์', 'สมบัติทอง', 'อินทรสุวรรณ', 'ชัยมงคล', 'ปัญญาดี', 'เจริญสุข', 'ศักดิ์สิทธิ์', 'กิจเจริญ', 'นาคสุข'];

    private array $nicks = ['ต้น', 'บอส', 'ภูมิ', 'ไอซ์', 'มิว', 'แพรว', 'ใบเตย', 'ปาย', 'ฟ้า', 'น้ำ', 'เจได', 'ปั๊บ', 'ข้าวหอม', 'ออม', 'มายด์', 'กัปตัน', 'พีช', 'ต้นข้าว', 'เนย', 'โฟกัส', 'บีม', 'แบม'];

    public function run(): void
    {
        // รวบเป็น transaction เดียว SQLite จะเร็วขึ้นหลายสิบเท่า
        // bcrypt rounds ต่ำเฉพาะข้อมูลตัวอย่าง (Laravel rehash ให้เองเมื่อผู้ใช้ล็อกอิน)
        config(['hashing.bcrypt.rounds' => 4]);
        DB::transaction(fn () => $this->seed());
    }

    private function seed(): void
    {
        mt_srand(2569);

        Settings::set([
            'school_name' => 'โรงเรียนสาธิตวิทยา',
            'school_short' => 'สาธิตวิทยา',
            'school_address' => '99 ถนนการศึกษา ตำบลในเมือง อำเภอเมือง จังหวัดขอนแก่น 40000',
            'school_phone' => '043-123-456',
            'director_name' => 'นายประเสริฐ วิทยาคม',
            'promptpay_id' => '0994000123456',
            'bank_info' => "ธนาคารกรุงไทย สาขาขอนแก่น\nเลขที่บัญชี 123-4-56789-0\nชื่อบัญชี โรงเรียนสาธิตวิทยา",
        ]);

        // ---------- ผู้ใช้ ----------
        $admin = User::create(['name' => 'นางสาววิไลวรรณ ศรีบริหาร', 'username' => 'admin', 'phone' => '0800000001', 'role' => 'admin', 'position' => 'รองผู้อำนวยการฝ่ายวิชาการ', 'password' => Hash::make('admin1234')]);
        $teacher = User::create(['name' => 'นายสมชาย รักการสอน', 'username' => 'teacher', 'phone' => '0800000002', 'role' => 'teacher', 'position' => 'ครูชำนาญการ', 'password' => Hash::make('teacher1234')]);

        $teacherNames = [
            ['นางสาวกนกพร ภาษาดี', 'ครูภาษาไทย'], ['นายวีระ วิทย์ก้าวหน้า', 'ครูวิทยาศาสตร์'], ['นางมาลี สังคมสุข', 'ครูสังคมศึกษา'],
            ['Mr. John Carter', 'ครูภาษาอังกฤษ'], ['นายเอกชัย แข็งแรง', 'ครูพลศึกษา'], ['นางสาวศิริพร ศิลป์งาม', 'ครูศิลปะ'],
            ['นายประยุทธ ช่างคิด', 'ครูการงานอาชีพ'], ['นางสาวอรุณี แนะนำดี', 'ครูแนะแนว'],
        ];
        $teachers = collect([$teacher]);
        foreach ($teacherNames as $i => [$name, $pos]) {
            $teachers->push(User::create(['name' => $name, 'username' => 't'.($i + 2), 'phone' => '08000000'.str_pad((string) ($i + 3), 2, '0', STR_PAD_LEFT), 'role' => 'teacher', 'position' => $pos, 'password' => Hash::make('teacher1234')]));
        }

        // ---------- ปีการศึกษา ----------
        Term::create(['year' => 2568, 'term' => 2, 'start_date' => '2025-11-01', 'end_date' => '2026-03-31']);
        $term = Term::create(['year' => 2569, 'term' => 1, 'start_date' => '2026-05-16', 'end_date' => '2026-10-10']);
        $term->makeCurrent();

        // ---------- ห้องเรียน ----------
        $rooms = [['ม.1', 1, 0], ['ม.1', 2, 1], ['ม.2', 1, 2], ['ม.3', 1, 3]];
        $classrooms = collect();
        foreach ($rooms as [$level, $room, $homeroomIdx]) {
            $classrooms->push(Classroom::create(['year' => 2569, 'level' => $level, 'room' => $room, 'homeroom_teacher_id' => $teachers[$homeroomIdx]->id]));
        }

        // ---------- นักเรียน + ผู้ปกครอง ----------
        $code = 69001;
        $demoParent = null;
        foreach ($classrooms as $ci => $classroom) {
            $n = 24 + $ci;
            $list = [];
            for ($i = 0; $i < $n; $i++) {
                $male = $i % 2 === 0;
                $first = $male ? $this->boys[array_rand($this->boys)] : $this->girls[array_rand($this->girls)];
                $list[] = [$male, $first, $this->lastNames[array_rand($this->lastNames)]];
            }
            // เรียงเลขที่ ชายก่อนหญิง ตามแบบโรงเรียนไทย
            usort($list, fn ($a, $b) => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

            foreach ($list as $i => [$male, $first, $last]) {
                $age = 12 + (int) substr($classroom->level, -1);
                $student = Student::create([
                    'student_code' => (string) $code++,
                    'prefix' => $male ? 'เด็กชาย' : 'เด็กหญิง',
                    'first_name' => $first,
                    'last_name' => $last,
                    'nickname' => $this->nicks[array_rand($this->nicks)],
                    'gender' => $male ? 'M' : 'F',
                    'birthdate' => Carbon::create(2026 - $age, mt_rand(1, 12), mt_rand(1, 28)),
                    'classroom_id' => $classroom->id,
                    'number' => $i + 1,
                    'blood_type' => ['A', 'B', 'O', 'AB'][mt_rand(0, 3)],
                    'medical_note' => mt_rand(1, 12) === 1 ? ['แพ้ยาเพนิซิลลิน', 'หอบหืด', 'แพ้อาหารทะเล', 'แพ้ถั่ว'][mt_rand(0, 3)] : null,
                ]);

                $phone = '08'.str_pad((string) mt_rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
                if (! $demoParent) {
                    $phone = '0812345678';
                }
                $parent = User::create([
                    'name' => ($male ? 'นาย' : 'นาง').$this->boys[array_rand($this->boys)].' '.$last,
                    'username' => $phone,
                    'phone' => $phone,
                    'role' => 'parent',
                    'password' => Hash::make(substr($phone, -6)),
                ]);
                $student->guardians()->attach($parent->id, ['relation' => $male ? 'บิดา' : 'มารดา']);
                $demoParent ??= $parent;
            }
        }
        // ผู้ปกครองตัวอย่างมีลูก 2 คน (พี่น้อง)
        $sibling = Student::where('classroom_id', $classrooms[3]->id)->where('number', 3)->first();
        $sibling->update(['last_name' => $demoParent->children()->first()->last_name]);
        $sibling->guardians()->syncWithoutDetaching([$demoParent->id => ['relation' => 'บิดา']]);
        $demoParent->update(['name' => 'นายวิชัย '.$sibling->last_name]);

        // บัญชีนักเรียนตัวอย่าง = ลูกของผู้ปกครองตัวอย่างที่อยู่ ม.1/1 (ชื่อผู้ใช้ = รหัสนักเรียน)
        $demoStudent = $demoParent->children()->where('classroom_id', $classrooms[0]->id)->first();
        $demoStudent->update(['user_id' => User::create([
            'name' => $demoStudent->fullName(), 'username' => $demoStudent->student_code, 'role' => 'student', 'password' => Hash::make('student1234'),
        ])->id]);

        // ---------- รายวิชา ----------
        $subjectTpl = [
            ['ท', 'ภาษาไทย', 1.5, 'basic', 'ภาษาไทย', 1],
            ['ค', 'คณิตศาสตร์', 1.5, 'basic', 'คณิตศาสตร์', 0],
            ['ว', 'วิทยาศาสตร์และเทคโนโลยี', 1.5, 'basic', 'วิทยาศาสตร์และเทคโนโลยี', 2],
            ['ส', 'สังคมศึกษา ศาสนา และวัฒนธรรม', 1.5, 'basic', 'สังคมศึกษา ศาสนา และวัฒนธรรม', 3],
            ['อ', 'ภาษาอังกฤษ', 1.5, 'basic', 'ภาษาต่างประเทศ', 4],
            ['พ', 'สุขศึกษาและพลศึกษา', 1.0, 'basic', 'สุขศึกษาและพลศึกษา', 5],
            ['ศ', 'ศิลปะ', 0.5, 'basic', 'ศิลปะ', 6],
            ['ง', 'การงานอาชีพ', 0.5, 'basic', 'การงานอาชีพ', 7],
            ['ค', 'คณิตศาสตร์เพิ่มเติม', 1.0, 'extra', 'คณิตศาสตร์', 0],
        ];
        foreach ($classrooms->unique('level') as $classroom) {
            $grade = substr($classroom->level, -1);
            foreach ($subjectTpl as $i => [$prefix, $name, $credit, $type, $group]) {
                $num = $type === 'extra' ? "2{$grade}201" : "2{$grade}101";
                Subject::create(['code' => $prefix.$num, 'name' => $name.' '.$grade, 'credit' => $credit, 'type' => $type, 'group' => $group]);
            }
        }

        // ---------- เปิดรายวิชา + คะแนน ----------
        foreach ($classrooms as $classroom) {
            $grade = substr($classroom->level, -1);
            $students = $classroom->students()->get();
            $ability = $students->mapWithKeys(fn ($s) => [$s->id => mt_rand(45, 98) / 100]);

            foreach ($subjectTpl as $i => [$prefix, , , $type, , $teacherIdx]) {
                $num = $type === 'extra' ? "2{$grade}201" : "2{$grade}101";
                $subject = Subject::where('code', $prefix.$num)->first();
                $course = Course::create(['term_id' => $term->id, 'classroom_id' => $classroom->id, 'subject_id' => $subject->id, 'teacher_id' => $teachers[$teacherIdx]->id]);
                foreach (CourseController::DEFAULT_ASSESSMENTS as $k => [$aname, $max]) {
                    $a = Assessment::create(['course_id' => $course->id, 'name' => $aname, 'max_score' => $max, 'sort' => $k + 1]);
                    // ตอนนี้ใกล้ปลายภาค: กรอกคะแนนแล้ว 3 ช่องแรก ช่องปลายภาคยังไม่สอบ
                    if ($k >= 3) {
                        continue;
                    }
                    foreach ($students as $s) {
                        $v = $max * min(1, max(0.2, $ability[$s->id] + mt_rand(-12, 12) / 100));
                        Score::create(['assessment_id' => $a->id, 'student_id' => $s->id, 'score' => round($v * 2) / 2]);
                    }
                }
            }
        }
        // ภาคเรียนที่แล้วของ ม.1/1: กรอกครบ มีเกรดให้ดู (ใช้ห้องเดียวกันเพื่อความง่ายของข้อมูลตัวอย่าง)
        $prevTerm = Term::where('year', 2568)->first();
        $m11 = $classrooms[0];
        $m11students = $m11->students()->get();
        foreach (Subject::where('code', 'like', '%21101')->get() as $subject) {
            $course = Course::create(['term_id' => $prevTerm->id, 'classroom_id' => $m11->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id, 'locked' => true]);
            $a = Assessment::create(['course_id' => $course->id, 'name' => 'คะแนนรวม', 'max_score' => 100, 'sort' => 1]);
            foreach ($m11students as $s) {
                Score::create(['assessment_id' => $a->id, 'student_id' => $s->id, 'score' => mt_rand(48, 97)]);
            }
        }

        // ---------- ตารางเรียน ----------
        $busy = []; // [teacher_id][day][period] กันครูสอนชนกัน
        foreach ($classrooms as $classroom) {
            $courses = Course::where('term_id', $term->id)->where('classroom_id', $classroom->id)->get()->values();
            for ($day = 1; $day <= 5; $day++) {
                for ($p = 1; $p <= 7; $p++) {
                    if ($day === 5 && $p >= 6) {
                        TimetableSlot::create(['term_id' => $term->id, 'classroom_id' => $classroom->id, 'day' => $day, 'period' => $p, 'label' => $p === 6 ? 'ลูกเสือ/เนตรนารี' : 'ชุมนุม']);

                        continue;
                    }
                    $start = ($day * 7 + $p + $classroom->id * 3) % $courses->count();
                    $course = null;
                    for ($k = 0; $k < $courses->count(); $k++) {
                        $c = $courses[($start + $k) % $courses->count()];
                        if (empty($busy[$c->teacher_id][$day][$p])) {
                            $course = $c;
                            break;
                        }
                    }
                    if ($course) {
                        $busy[$course->teacher_id][$day][$p] = true;
                    }
                    TimetableSlot::create(['term_id' => $term->id, 'classroom_id' => $classroom->id, 'day' => $day, 'period' => $p, 'course_id' => $course?->id, 'label' => $course ? null : 'ศึกษาค้นคว้าด้วยตนเอง']);
                }
            }
        }

        // ---------- เช็คชื่อย้อนหลัง 15 วันทำการ ----------
        $day = today()->subDay();
        $days = [];
        while (count($days) < 15) {
            if ($day->isWeekday()) {
                $days[] = $day->copy();
            }
            $day->subDay();
        }
        $allStudents = Student::active()->get();
        $rows = [];
        foreach ($days as $d) {
            foreach ($allStudents as $s) {
                $r = mt_rand(1, 100);
                $status = $r <= 91 ? 'present' : ($r <= 95 ? 'late' : ($r <= 97 ? 'absent' : ($r <= 99 ? 'sick' : 'leave')));
                $rows[] = [
                    'student_id' => $s->id, 'classroom_id' => $s->classroom_id, 'date' => $d->toDateString(), 'status' => $status,
                    'checked_at' => $status === 'late' ? '08:'.mt_rand(10, 40).':00' : '07:'.mt_rand(30, 59).':00',
                    'recorded_by' => $teacher->id, 'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }
        // วันนี้: บางห้องเช็คแล้ว ม.1/1 (ห้องของครูตัวอย่าง) ยังไม่เช็ค เพื่อให้เห็นการแจ้งเตือน
        if (today()->isWeekday()) {
            foreach ($allStudents->whereIn('classroom_id', [$classrooms[1]->id, $classrooms[2]->id]) as $s) {
                $r = mt_rand(1, 100);
                $rows[] = [
                    'student_id' => $s->id, 'classroom_id' => $s->classroom_id, 'date' => today()->toDateString(),
                    'status' => $r <= 92 ? 'present' : ($r <= 96 ? 'late' : 'absent'),
                    'checked_at' => '07:45:00', 'recorded_by' => $teachers[1]->id, 'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            Attendance::insert($chunk);
        }

        // ---------- เช็คชื่อรายคาบ (ตามตารางสอน ย้อนหลังเท่ากับเช็คชื่อรายวัน) ----------
        // ขาด/ลาทั้งวัน → ทุกคาบตามนั้น, มาโรงเรียน → ส่วนใหญ่เข้าเรียน มีโดดบ้างเล็กน้อย
        // นักเรียนตัวอย่าง 1 คนใน ม.1/1 โดดคณิตศาสตร์บ่อย ให้เห็นกรณีเสี่ยง มส.
        $dailyStatus = [];
        foreach ($rows as $r) {
            $dailyStatus[$r['student_id']][$r['date']] = $r['status'];
        }
        $truant = $m11students[6];
        $studentsByRoom = $allStudents->groupBy('classroom_id');
        $periodRows = [];
        $slotsByDay = TimetableSlot::with('course')->where('term_id', $term->id)->whereNotNull('course_id')->get()->groupBy('day');
        // ย้อนหลัง ~8 สัปดาห์ (ยาวกว่าเช็คชื่อรายวัน) ให้แต่ละวิชามีหลายสิบคาบ ร้อยละเวลาเรียนจะสมจริง
        // ไม่ใช่ขาดครั้งเดียวก็ตกเกณฑ์เพราะเพิ่งสอนไป 3 คาบ
        $periodDays = [];
        for ($d = today()->subDay(); count($periodDays) < 40; $d->subDay()) {
            if ($d->isWeekday()) {
                $periodDays[] = $d->copy();
            }
        }
        foreach ($periodDays as $d) {
            $date = $d->toDateString();
            foreach ($slotsByDay[$d->dayOfWeekIso] ?? [] as $slot) {
                foreach ($studentsByRoom[$slot->classroom_id] ?? [] as $s) {
                    $daily = $dailyStatus[$s->id][$date] ?? 'present';
                    if (in_array($daily, ['absent', 'leave', 'sick'], true)) {
                        $status = $daily;
                    } elseif ($s->id === $truant->id && $slot->course->teacher_id === $teacher->id) {
                        $status = mt_rand(1, 100) <= 35 ? 'absent' : 'present';
                    } else {
                        $status = mt_rand(1, 200) === 1 ? 'absent' : ($slot->period === 1 && $daily === 'late' ? 'late' : 'present');
                    }
                    $periodRows[] = [
                        'course_id' => $slot->course_id, 'student_id' => $s->id, 'date' => $date, 'period' => $slot->period,
                        'status' => $status, 'recorded_by' => $slot->course->teacher_id, 'created_at' => now(), 'updated_at' => now(),
                    ];
                }
            }
        }
        foreach (array_chunk($periodRows, 500) as $chunk) {
            PeriodAttendance::insert($chunk);
        }

        // ---------- ข้อมูล ปพ.1 + กิจกรรมพัฒนาผู้เรียน + คุณลักษณะ ----------
        foreach ($classrooms as $classroom) {
            $grade = (int) substr($classroom->level, -1);
            Student::where('classroom_id', $classroom->id)->update([
                'nationality' => 'ไทย', 'ethnicity' => 'ไทย', 'religion' => 'พุทธ',
                'admitted_on' => Carbon::create(2026 - ($grade - 1), 5, 16)->toDateString(),
                'previous_school' => 'โรงเรียนบ้านหนองบัว', 'previous_school_province' => 'ขอนแก่น', 'previous_level' => 'ป.6',
            ]);
        }
        $activityTpl = [['แนะแนว', 'guidance', 20], ['ลูกเสือ-เนตรนารี', 'scout', 20], ['ชุมนุม', 'club', 20], ['กิจกรรมเพื่อสังคมและสาธารณประโยชน์', 'social', 10]];
        foreach ($classrooms->unique('level') as $classroom) {
            $grade = substr($classroom->level, -1);
            foreach ($activityTpl as $k => [$name, $kind, $hours]) {
                Subject::create(['code' => "ก2{$grade}90".($k + 1), 'name' => $name, 'credit' => 0, 'hours' => $hours, 'type' => 'activity', 'activity_kind' => $kind, 'group' => 'กิจกรรมพัฒนาผู้เรียน']);
            }
        }
        foreach ($classrooms as $classroom) {
            $grade = substr($classroom->level, -1);
            foreach ($activityTpl as $k => $_) {
                $course = Course::create(['term_id' => $term->id, 'classroom_id' => $classroom->id, 'subject_id' => Subject::where('code', "ก2{$grade}90".($k + 1))->value('id'), 'teacher_id' => $classroom->homeroom_teacher_id]);
                Assessment::create(['course_id' => $course->id, 'name' => 'ผลการประเมิน', 'max_score' => 100, 'sort' => 1]);
            }
        }
        // ภาคที่แล้วของ ม.1/1: กิจกรรมประเมินครบ (คนสุดท้ายไม่ผ่านชุมนุมแล้วซ่อมผ่าน) + คุณลักษณะ/อ่านคิดเขียน
        foreach ($activityTpl as $k => $_) {
            $course = Course::create(['term_id' => $prevTerm->id, 'classroom_id' => $m11->id, 'subject_id' => Subject::where('code', 'ก2190'.($k + 1))->value('id'), 'teacher_id' => $teacher->id, 'locked' => true]);
            $a = Assessment::create(['course_id' => $course->id, 'name' => 'ผลการประเมิน', 'max_score' => 100, 'sort' => 1]);
            foreach ($m11students as $i => $s) {
                $failed = $k === 2 && $i === $m11students->count() - 1;
                Score::create(['assessment_id' => $a->id, 'student_id' => $s->id, 'score' => $failed ? 35 : mt_rand(70, 100)]);
                if ($failed) {
                    CourseResult::create(['course_id' => $course->id, 'student_id' => $s->id, 'remedial_grade' => 'ผ', 'remedied_on' => $prevTerm->end_date, 'note' => 'ทำกิจกรรมซ่อมครบ', 'recorded_by' => $teacher->id]);
                }
            }
        }
        foreach ($m11students as $s) {
            $traits = [];
            foreach (array_keys(Evaluation::TRAITS) as $no) {
                $traits[$no] = [1, 2, 2, 3, 3, 3][mt_rand(0, 5)];
            }
            StudentEvaluation::create(['term_id' => $prevTerm->id, 'student_id' => $s->id, 'traits' => $traits, 'rtw' => [2, 3, 3][mt_rand(0, 2)], 'recorded_by' => $teacher->id]);
        }

        // ---------- ความประพฤติ ----------
        $rules = [
            ['ช่วยเหลืองานครู/โรงเรียน', 5], ['เก็บของได้ส่งคืน', 10], ['เป็นตัวแทนแข่งขัน', 10], ['จิตอาสา', 5],
            ['มาสาย', -2], ['แต่งกายผิดระเบียบ', -5], ['ไม่ส่งงาน', -3], ['ใช้โทรศัพท์ในเวลาเรียน', -5], ['ทะเลาะวิวาท', -20], ['หนีเรียน', -10],
        ];
        $ruleModels = collect($rules)->map(fn ($r) => BehaviorRule::create(['name' => $r[0], 'points' => $r[1]]));
        foreach ($allStudents->random(45) as $s) {
            $rule = $ruleModels->random();
            BehaviorRecord::create(['student_id' => $s->id, 'behavior_rule_id' => $rule->id, 'title' => $rule->name, 'points' => $rule->points, 'date' => today()->subDays(mt_rand(0, 30)), 'recorded_by' => $teachers->random()->id]);
        }
        // นักเรียนที่ต้องติดตาม
        foreach ($allStudents->random(2) as $s) {
            foreach (['ทะเลาะวิวาท' => -20, 'หนีเรียน' => -10, 'ใช้โทรศัพท์ในเวลาเรียน' => -5] as $t => $p) {
                BehaviorRecord::create(['student_id' => $s->id, 'title' => $t, 'points' => $p, 'date' => today()->subDays(mt_rand(1, 20)), 'recorded_by' => $teacher->id]);
            }
        }

        // ---------- ใบลา ----------
        $m11first = $m11students->take(3);
        foreach ($m11first as $i => $s) {
            LeaveRequest::create([
                'student_id' => $s->id, 'requested_by' => $s->guardians()->first()->id, 'type' => $i === 1 ? 'personal' : 'sick',
                'start_date' => today(), 'end_date' => today()->addWeekdays($i === 2 ? 1 : 0),
                'reason' => ['มีไข้ ไอ ไปพบแพทย์', 'ไปงานบวชญาติที่ต่างจังหวัด', 'ปวดท้อง อาเจียน'][$i], 'status' => 'pending',
            ]);
        }

        // ---------- ประกาศ ----------
        Announcement::create(['title' => 'กำหนดการสอบปลายภาคเรียนที่ 1/2569', 'body' => "สอบปลายภาคระหว่างวันที่ 1-3 ตุลาคม 2569\nนักเรียนทุกคนต้องแต่งกายถูกระเบียบ และมาถึงโรงเรียนก่อนเวลา 07.45 น.\n\nตารางสอบแยกตามห้องจะแจ้งผ่านครูประจำชั้น", 'audience' => 'all', 'pinned' => true, 'author_id' => $admin->id, 'created_at' => now()->subDays(2)]);
        Announcement::create(['title' => 'ประชุมผู้ปกครองชั้นเรียน', 'body' => "ขอเชิญผู้ปกครองทุกท่านเข้าร่วมประชุมผู้ปกครองชั้นเรียน\nวันเสาร์ที่ 26 กันยายน 2569 เวลา 09.00 น. ณ ห้องเรียนของบุตรหลาน", 'audience' => 'parents', 'author_id' => $admin->id, 'created_at' => now()->subDays(4)]);
        Announcement::create(['title' => 'ห้อง ม.1/1 ส่งแบบฝึกหัดคณิตศาสตร์', 'body' => 'นักเรียนห้อง ม.1/1 ส่งแบบฝึกหัดบทที่ 4 ภายในวันศุกร์นี้ ผู้ปกครองช่วยกำกับดูแลด้วยนะคะ', 'audience' => 'classroom', 'classroom_id' => $m11->id, 'author_id' => $teacher->id, 'created_at' => now()->subDay()]);
        Announcement::create(['title' => 'ประชุมครูประจำเดือนกันยายน', 'body' => 'ประชุมครูทุกท่าน วันจันทร์ 28 ก.ย. เวลา 15.40 น. ห้องประชุม 1', 'audience' => 'staff', 'author_id' => $admin->id, 'created_at' => now()->subHours(5)]);

        // ---------- ฟีดข่าว ----------
        foreach (Announcement::all() as $a) {
            FeedPost::create([
                'type' => 'announcement', 'author_id' => $a->author_id, 'announcement_id' => $a->id, 'headline' => 'ประกาศ',
                'title' => $a->title, 'body' => $a->body, 'audience' => $a->audience, 'classroom_id' => $a->classroom_id,
                'created_at' => $a->created_at, 'updated_at' => $a->created_at,
            ]);
        }
        $star1 = $m11students[4];
        $star2 = $allStudents->where('classroom_id', $classrooms[2]->id)->values()[7];
        $star3 = $allStudents->where('classroom_id', $classrooms[3]->id)->values()[1];
        $feed = [
            ['achievement', $teacher, $star1, 'ได้รับรางวัล', 'เหรียญทอง คณิตศาสตร์โอลิมปิก ระดับจังหวัด', "ด้วยความมุ่งมั่นและตั้งใจฝึกฝนอย่างต่อเนื่อง\nขอแสดงความยินดีกับความสำเร็จครั้งนี้ 🎉", 'bi-trophy-fill', 'all', now()->subHours(10)],
            ['post', $admin, null, null, 'ยินดีต้อนรับคุณครูใหม่', 'ขอต้อนรับ Mr. John Carter ครูภาษาอังกฤษเจ้าของภาษา ที่จะมาร่วมสอนนักเรียนระดับมัธยมต้นตั้งแต่ภาคเรียนนี้ ✨', null, 'all', now()->subDays(1)],
            ['achievement', $teachers[2], $star2, 'ได้รับรางวัล', 'ชนะเลิศโครงงานวิทยาศาสตร์ งานศิลปหัตถกรรมนักเรียน', 'โครงงาน "ถุงเพาะชำย่อยสลายได้จากเปลือกกล้วย" เป็นตัวแทนเขตไปแข่งระดับภาคต่อไป', 'bi-trophy-fill', 'all', now()->subDays(2)],
            ['achievement', $teacher, $star3, 'ได้รับคำชมเชย', 'เก็บของได้ส่งคืน', 'เก็บกระเป๋าสตางค์ได้ที่โรงอาหาร นำส่งครูเวรทันที เป็นแบบอย่างที่ดี 👏', 'bi-star-fill', 'all', now()->subDays(3)],
            ['post', $teachers[5], null, null, 'กิจกรรมวันวิทยาศาสตร์', "ขอบคุณผู้ปกครองทุกท่านที่มาร่วมชมนิทรรศการวันวิทยาศาสตร์ นักเรียนนำเสนอผลงานได้ยอดเยี่ยมมากครับ\nภาพกิจกรรมทั้งหมดดูได้ที่เพจโรงเรียน", null, 'all', now()->subDays(5)],
        ];
        $reactors = $teachers->concat([$admin])->concat(User::where('role', 'parent')->limit(40)->get());
        foreach ($feed as [$type, $author, $student, $headline, $title, $body, $icon, $aud, $at]) {
            $post = FeedPost::create(['type' => $type, 'author_id' => $author->id, 'student_id' => $student?->id, 'headline' => $headline, 'title' => $title, 'body' => $body, 'icon' => $icon, 'audience' => $aud, 'created_at' => $at, 'updated_at' => $at]);
            foreach ($reactors->random(mt_rand(4, 18)) as $who) {
                FeedReaction::firstOrCreate(['feed_post_id' => $post->id, 'user_id' => $who->id, 'emoji' => FeedPost::REACTIONS[mt_rand(0, 3)]]);
            }
        }

        // ---------- ค่าธรรมเนียม ----------
        foreach ($allStudents as $s) {
            $inv = Invoice::create([
                'invoice_no' => Invoice::nextNumber(), 'student_id' => $s->id, 'term_id' => $term->id,
                'title' => 'ค่าธรรมเนียมการศึกษา ภาคเรียนที่ 1/2569', 'due_date' => '2026-06-30', 'total' => 2500, 'created_by' => $admin->id,
            ]);
            $inv->items()->createMany([
                ['description' => 'ค่าบำรุงการศึกษา', 'amount' => 1500],
                ['description' => 'ค่าเอกสารและแบบฝึกหัด', 'amount' => 600],
                ['description' => 'ค่าประกันอุบัติเหตุ', 'amount' => 400],
            ]);
            $r = mt_rand(1, 100);
            if ($r <= 75 || $s->guardians->contains('id', $demoParent->id) && $s->classroom_id === $m11->id) {
                $inv->payments()->create(['receipt_no' => Payment::nextNumber(), 'amount' => 2500, 'method' => ['cash', 'transfer', 'promptpay'][mt_rand(0, 2)], 'paid_at' => Carbon::create(2026, 6, mt_rand(1, 28), 10), 'received_by' => $admin->id]);
            } elseif ($r <= 85) {
                $inv->payments()->create(['receipt_no' => Payment::nextNumber(), 'amount' => 1000, 'method' => 'cash', 'paid_at' => Carbon::create(2026, 6, mt_rand(1, 28), 10), 'received_by' => $admin->id]);
            }
            $inv->refreshTotals();
        }
        // ค่าทัศนศึกษา ม.1 (เพิ่งออก ยังไม่มีใครจ่าย)
        foreach ($allStudents->whereIn('classroom_id', [$classrooms[0]->id, $classrooms[1]->id]) as $s) {
            $inv = Invoice::create(['invoice_no' => Invoice::nextNumber(), 'student_id' => $s->id, 'term_id' => $term->id, 'title' => 'ค่าทัศนศึกษา ม.1', 'due_date' => today()->addDays(10), 'total' => 450, 'created_by' => $admin->id]);
            $inv->items()->create(['description' => 'ค่ารถและค่าเข้าชม', 'amount' => 450]);
        }

        // ---------- ปฏิทิน ----------
        $cal = [
            ['ประชุมผู้ปกครองชั้นเรียน', 2, 2, 'meeting', 'all', 'ห้องเรียนของบุตรหลาน 09.00 น.'],
            ['สอบปลายภาคเรียนที่ 1/2569', 7, 9, 'exam', 'all', 'แต่งกายถูกระเบียบ มาก่อน 07.45 น.'],
            ['ปิดภาคเรียนที่ 1', 16, 16, 'holiday', 'all', null],
            ['วันปิยมหาราช (หยุดราชการ)', 29, 29, 'holiday', 'all', null],
            ['ส่งผลการเรียนภาคเรียนที่ 1', 14, 14, 'meeting', 'staff', 'ครูส่ง ปพ.5 ที่ฝ่ายวิชาการ'],
            ['กีฬาสีภายใน', 1, 1, 'activity', 'all', 'แต่งชุดกีฬาตามสีของตัวเอง'],
        ];
        foreach ($cal as [$title, $from, $to, $type, $aud, $desc]) {
            SchoolEvent::create(['title' => $title, 'start_date' => today()->addDays($from), 'end_date' => today()->addDays($to), 'type' => $type, 'audience' => $aud, 'description' => $desc, 'created_by' => $admin->id]);
        }

        // ---------- ห้องสมุด ----------
        $books = [
            ['เจ้าชายน้อย', 'อ็องตวน เดอ แซ็งแตก-ซูเปรี', 'นวนิยาย', 3], ['แฮร์รี่ พอตเตอร์กับศิลาอาถรรพ์', 'J.K. Rowling', 'นวนิยาย', 2],
            ['ความลับของจักรวาล', 'ทีมวิทย์สนุก', 'วิทยาศาสตร์', 2], ['คณิตคิดสนุก ม.ต้น', 'ครูสมชาย', 'คณิตศาสตร์', 4],
            ['ประวัติศาสตร์ไทยฉบับการ์ตูน', 'สำนักพิมพ์เด็กดี', 'การ์ตูนความรู้', 3], ['English Grammar in Use', 'Raymond Murphy', 'ภาษาอังกฤษ', 2],
            ['สัตว์โลกน่ารู้', 'สารคดีเด็ก', 'สารคดี', 2], ['หนูน้อยนักประดิษฐ์', 'ทีมวิทย์สนุก', 'วิทยาศาสตร์', 1],
        ];
        $bookModels = collect($books)->map(fn ($b, $i) => Book::create(['code' => 'B'.str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT), 'title' => $b[0], 'author' => $b[1], 'category' => $b[2], 'copies' => $b[3], 'location' => 'ชั้น '.chr(65 + $i % 4)]));
        foreach ($allStudents->random(9) as $i => $s) {
            $borrowed = today()->subDays(mt_rand(1, 14));
            BookLoan::create(['book_id' => $bookModels[$i % 8]->id, 'student_id' => $s->id, 'borrowed_on' => $borrowed, 'due_on' => $borrowed->copy()->addWeekdays(7), 'returned_on' => $i % 3 === 0 ? today() : null, 'recorded_by' => $teachers[7]->id]);
        }

        // ---------- ห้องพยาบาล ----------
        foreach ([[3, 'ปวดหัว', 37.2, 'returned'], [1, 'บาดแผล/หกล้ม', null, 'returned'], [0, 'มีไข้', 38.4, 'sent_home'], [5, 'ปวดท้อง', null, 'rest']] as $i => [$daysAgo, $sym, $temp, $act]) {
            HealthVisit::create(['student_id' => $allStudents[$i * 7 + 2]->id, 'visited_at' => now()->subDays($daysAgo)->setTime(10 + $i, 15), 'symptom' => $sym, 'temperature' => $temp,
                'treatment' => $sym === 'บาดแผล/หกล้ม' ? 'ทำแผล ใส่ยาฆ่าเชื้อ' : 'นอนพัก 30 นาที', 'medicine' => $temp ? 'พาราเซตามอล 1 เม็ด' : null, 'action' => $act, 'recorded_by' => $teachers[4]->id]);
        }
        foreach ($m11students as $s) {
            $h = 150 + mt_rand(-8, 12);
            HealthMeasurement::create(['student_id' => $s->id, 'measured_on' => '2026-06-10', 'height' => $h, 'weight' => round(($h / 100) ** 2 * mt_rand(160, 260) / 10, 1), 'recorded_by' => $teachers[4]->id]);
        }

        // ---------- รับสมัคร / ลางาน / สลิป ----------
        foreach ([['เด็กชาย', 'ปัณณวัฒน์', 'ศรีสุข', 'M', 'ม.1', 3.65, 'submitted'], ['เด็กหญิง', 'ชนิดา', 'แก้วมณี', 'F', 'ม.1', 3.92, 'accepted'], ['นางสาว', 'พิมพ์มาดา', 'รุ่งเรือง', 'F', 'ม.4', 3.40, 'reviewing'], ['เด็กชาย', 'ธีรเดช', 'ใจงาม', 'M', 'ม.1', 2.85, 'submitted']] as $i => [$p, $f, $l, $g, $lv, $gpa, $st]) {
            Admission::create(['app_no' => Admission::nextNumber(2570), 'year' => 2570, 'level' => $lv, 'prefix' => $p, 'first_name' => $f, 'last_name' => $l, 'gender' => $g,
                'birthdate' => $lv === 'ม.1' ? '2014-0'.($i + 3).'-12' : '2011-08-20', 'citizen_id' => '11037000'.str_pad((string) (12345 + $i), 5, '0', STR_PAD_LEFT),
                'previous_school' => 'โรงเรียนบ้านหนองแวง', 'gpa' => $gpa, 'parent_name' => 'นางสมใจ '.$l, 'parent_phone' => '089123456'.$i, 'relation' => 'มารดา', 'status' => $st, 'created_at' => now()->subDays(3 - $i)]);
        }
        StaffLeave::create(['user_id' => $teachers[3]->id, 'type' => 'personal', 'start_date' => today()->addWeekdays(2), 'end_date' => today()->addWeekdays(2), 'reason' => 'ธุระที่อำเภอ ทำบัตรประชาชน']);
        StaffLeave::create(['user_id' => $teacher->id, 'type' => 'sick', 'start_date' => today()->subDays(20), 'end_date' => today()->subDays(19), 'reason' => 'ไข้หวัด', 'status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => today()->subDays(20)]);
        $unpaidInv = Invoice::where('title', 'ค่าทัศนศึกษา ม.1')->first();
        if ($unpaidInv) {
            // รูปสลิปตัวอย่าง (SVG) ให้หน้าตรวจสลิปมีข้อมูลให้ดู
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="360" height="560"><rect width="100%" height="100%" fill="#f0fdf4"/><text x="180" y="80" font-size="26" text-anchor="middle" fill="#15803d" font-family="sans-serif">โอนเงินสำเร็จ</text><text x="180" y="200" font-size="42" text-anchor="middle" font-family="sans-serif">450.00</text><text x="180" y="260" font-size="18" text-anchor="middle" fill="#555" font-family="sans-serif">ตัวอย่างสลิป (ข้อมูลทดลอง)</text></svg>';
            \Illuminate\Support\Facades\Storage::disk('public')->put('slips/demo-slip.svg', $svg);
            \App\Models\PaymentSlip::create(['invoice_id' => $unpaidInv->id, 'amount' => 450, 'image' => 'slips/demo-slip.svg', 'transferred_at' => now()->subHours(3), 'uploaded_by' => $unpaidInv->student->guardians()->first()?->id]);
        }

        // ---------- แบบประเมิน (ตัวอย่างที่เขียนขึ้นเอง) ----------
        $def = app(\App\Http\Controllers\SurveyController::class)->parse(\App\Http\Controllers\SurveyController::EXAMPLE);
        $survey = \App\Models\Survey::create(['title' => 'แบบคัดกรองพฤติกรรมนักเรียน (ตัวอย่าง)', 'description' => 'แบบสาธิตสำหรับระบบดูแลช่วยเหลือนักเรียน ครูประจำชั้นและผู้ปกครองตอบได้ — ไม่ใช่เครื่องมือมาตรฐาน',
            'respondent' => 'both', 'scale' => $def['scale'], 'subscales' => $def['subscales'], 'total_bands' => $def['total']]);
        foreach ($def['items'] as $i => $item) {
            $survey->items()->create($item + ['sort' => $i + 1]);
        }
        $survey->load('items');
        foreach ($m11students->take(18) as $s) {
            $answers = $survey->items->mapWithKeys(fn ($it) => [$it->id => mt_rand(0, 10) > 7 ? 2 : mt_rand(0, 1)])->all();
            \App\Models\SurveyResponse::create(['survey_id' => $survey->id, 'student_id' => $s->id, 'term_id' => $term->id, 'user_id' => $teacher->id,
                'respondent_role' => 'teacher', 'answers' => $answers, 'scores' => $survey->score($answers)]);
        }

        // ---------- การบ้าน ----------
        $mathCourse = Course::where('term_id', $term->id)->where('classroom_id', $m11->id)->where('teacher_id', $teacher->id)->first();
        $hw = \App\Models\Assignment::create(['course_id' => $mathCourse->id, 'title' => 'แบบฝึกหัดบทที่ 4 เรื่องสมการ ข้อ 1-10', 'description' => "ทำในสมุด แล้วถ่ายรูปส่ง\nแสดงวิธีทำทุกข้อ",
            'due_at' => today()->addDays(2)->setTime(16, 0), 'max_score' => 10, 'created_by' => $teacher->id, 'created_at' => now()->subDay()]);
        foreach ($m11students->skip(1)->take(15) as $i => $s) {
            \App\Models\Submission::create(['assignment_id' => $hw->id, 'student_id' => $s->id, 'text' => 'ส่งงานครับ/ค่ะ', 'submitted_at' => now()->subHours(20 - $i),
                'channel' => $i % 4 ? 'online' : 'paper', 'score' => $i < 8 ? mt_rand(6, 10) : null, 'feedback' => $i < 3 ? 'ดีมาก' : null, 'graded_at' => $i < 8 ? now() : null, 'submitted_by' => $s->guardians()->first()?->id]);
        }
        \App\Models\Assignment::create(['course_id' => $mathCourse->id, 'title' => 'ใบงานทบทวนก่อนสอบปลายภาค', 'due_at' => today()->addDays(6)->setTime(16, 0), 'max_score' => 5, 'created_by' => $teacher->id]);

        // ---------- แชท ----------
        $demoChild = $demoParent->children()->where('classroom_id', $m11->id)->first();
        $conv = \App\Models\Conversation::create(['student_id' => $demoChild->id, 'topic' => 'เรื่องของน้อง'.$demoChild->nickname, 'created_by' => $demoParent->id, 'last_message_at' => now()->subMinutes(30)]);
        $conv->participants()->attach([$demoParent->id => ['last_read_at' => now()->subMinutes(40)], $teacher->id => ['last_read_at' => now()->subHours(3)]]);
        foreach ([[$demoParent, 'สวัสดีค่ะคุณครู วันนี้น้องมีไข้ ขอลาป่วย 1 วันนะคะ', 180], [$teacher, 'รับทราบครับ ขอให้น้องหายไวๆ นะครับ ส่งใบลาในระบบได้เลยครับ', 170],
            [$demoParent, 'ส่งใบลาแล้วค่ะ ขอบคุณค่ะ 🙏', 165], [$teacher, 'พรุ่งนี้มีส่งแบบฝึกหัดบทที่ 4 นะครับ ถ้ายังไม่หายดีส่งวันจันทร์ได้ครับ', 30]] as [$who, $text, $ago]) {
            \App\Models\Message::create(['conversation_id' => $conv->id, 'user_id' => $who->id, 'body' => $text, 'created_at' => now()->subMinutes($ago), 'updated_at' => now()->subMinutes($ago)]);
        }

        // ---------- แฟ้มผลงาน ----------
        foreach ([['award', 'เหรียญทอง คณิตศาสตร์โอลิมปิก', 'province', null], ['activity', 'ค่ายวิทยาศาสตร์ภาคฤดูร้อน', 'school', 12], ['volunteer', 'จิตอาสาทำความสะอาดวัด', null, 4], ['work', 'โครงงานเครื่องกรองน้ำจากวัสดุธรรมชาติ', 'district', null]] as $i => [$cat, $title, $lvl, $hrs]) {
            \App\Models\StudentWork::create(['student_id' => $star1->id, 'category' => $cat, 'title' => $title, 'level' => $lvl, 'hours' => $hrs, 'date' => today()->subDays(10 + $i * 20), 'recorded_by' => $teacher->id, 'verified' => true]);
        }
        \App\Models\StudentWork::create(['student_id' => $demoChild->id, 'category' => 'volunteer', 'title' => 'ช่วยงานบุญที่วัดใกล้บ้าน', 'hours' => 3, 'date' => today()->subDays(4), 'recorded_by' => $demoParent->id, 'verified' => false]);

        // ---------- ตรวจข้อสอบ: สอบกลางภาคคณิต ม.1/1 30 ข้อ สแกนแล้วเกือบครบ มี 2 แผ่นรอตรวจทาน ----------
        $exam = \App\Models\Exam::create(['term_id' => $term->id, 'subject_id' => $mathCourse->subject_id, 'title' => 'สอบกลางภาค', 'n_items' => 30,
            'exam_date' => today()->subDays(5), 'answer_key' => array_map(fn () => (string) mt_rand(1, 4), range(1, 30)), 'cancelled' => [],
            'key_version' => 1, 'assessment_name' => 'สอบกลางภาค', 'created_by' => $teacher->id]);
        $exam->courses()->attach($mathCourse->id);
        $key = $exam->key();
        // ข้อ 7 ง่ายเกิน (ทุกคนตอบถูก) ข้อ 19 ตัวลวงไม่ทำงาน — ให้หน้าวิเคราะห์มีตัวอย่างข้อที่ควรปรับปรุง
        foreach ($m11students->values() as $i => $s) {
            if ($i === 5) {
                continue; // ขาดสอบ 1 คน
            }
            $ability = mt_rand(35, 95) / 100;
            $ans = '';
            foreach ($key as $q => $k) {
                $ans .= $q === 6 || mt_rand(1, 100) <= $ability * 100 ? $k : (string) (($k + mt_rand(1, $q === 18 ? 1 : 3) - 1) % 4 + 1);
            }
            $review = in_array($i, [2, 9], true);
            if ($review) {
                $ans[12] = '9';
            }
            $sc = $exam->score($ans);
            $exam->responses()->create([
                'student_id' => $s->id, 'request_id' => 'seed-'.$s->id, 'answers' => $ans, 'score' => $sc['score'], 'max_score' => $sc['max'],
                'status' => $review ? 'review' : 'ok', 'flags' => $review ? ['multi:13'] : [], 'confidence' => $review ? 0.3 : 0.9,
                'code_read' => $s->student_code, 'seat_read' => $s->number.'ก', 'source' => 'camera', 'key_version' => 1,
                'scanned_by' => $teacher->id, 'scanned_at' => today()->subDays(5)->setTime(14, 0)->addMinutes($i),
            ]);
        }

        // ---------- ครุภัณฑ์ + แจ้งซ่อม (ครูประยุทธ = งานอาคารสถานที่) ----------
        $facility = $teachers[7];
        Settings::set(['facility_manager_ids' => (string) $facility->id]);
        $assetTpl = [
            ['7440-001-%04d', 'เครื่องคอมพิวเตอร์ตั้งโต๊ะ', 'ครุภัณฑ์คอมพิวเตอร์', 'Dell OptiPlex 3000', 18500, 'ห้องคอมพิวเตอร์ 1', 6],
            ['7440-007-%04d', 'เครื่องพิมพ์เลเซอร์', 'ครุภัณฑ์คอมพิวเตอร์', 'Brother HL-L2370DN', 6900, 'ห้องธุรการ', 1],
            ['4110-001-%04d', 'เครื่องปรับอากาศ 24,000 BTU', 'ครุภัณฑ์งานบ้านงานครัว', 'Daikin FTKC24', 32000, 'ห้องประชุม', 2],
            ['5820-005-%04d', 'เครื่องฉายภาพ (โปรเจกเตอร์)', 'ครุภัณฑ์โฆษณาและเผยแพร่', 'Epson EB-X51', 15900, 'ห้อง ม.1/1', 1],
            ['7110-006-%04d', 'โต๊ะทำงานครู', 'ครุภัณฑ์สำนักงาน', 'ไม้ ขนาด 120 ซม.', 3500, 'ห้องพักครู', 4],
        ];
        $assets = collect();
        $no = 1;
        foreach ($assetTpl as [$codeFmt, $aname, $cat, $brand, $price, $loc, $qty]) {
            for ($i = 0; $i < $qty; $i++) {
                $assets->push(\App\Models\Asset::create([
                    'code' => sprintf($codeFmt, $no++), 'name' => $aname, 'category' => $cat, 'brand' => $brand, 'price' => $price,
                    'acquired_on' => now()->subYears(mt_rand(0, 4))->subDays(mt_rand(0, 300))->toDateString(), 'budget_source' => 'เงินอุดหนุน',
                    'location' => $loc, 'responsible_id' => $facility->id, 'status' => 'normal',
                ]));
            }
        }
        $aircon = $assets->firstWhere('name', 'เครื่องปรับอากาศ 24,000 BTU');
        $repairTpl = [
            [$aircon, 'แอร์ไม่เย็น มีน้ำหยด', 'urgent', 'in_progress', $teacher, 1],
            [$assets->firstWhere('name', 'เครื่องพิมพ์เลเซอร์'), 'เครื่องพิมพ์กระดาษติดบ่อย', 'normal', 'pending', $teachers[2], 0],
            [null, 'หลอดไฟหน้าห้อง ม.2/1 ดับ 2 หลอด', 'normal', 'done', $teachers[3], 6],
        ];
        foreach ($repairTpl as $i => [$asset, $title, $priority, $status, $reporter, $daysAgo]) {
            $r = \App\Models\RepairRequest::create([
                'ticket_no' => 'R2569-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'asset_id' => $asset?->id, 'location' => $asset?->location ?? 'อาคาร 1 ชั้น 2',
                'title' => $title, 'priority' => $priority, 'status' => $status, 'reporter_id' => $reporter->id,
                'assignee_id' => $status === 'pending' ? null : $facility->id, 'cost' => $status === 'done' ? 240 : null,
                'result_note' => $status === 'done' ? 'เปลี่ยนหลอด LED 2 หลอด' : null, 'finished_at' => $status === 'done' ? now()->subDays($daysAgo - 1) : null,
            ]);
            $r->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
            $r->updates()->create(['user_id' => $reporter->id, 'status' => 'pending', 'note' => 'แจ้งซ่อม']);
            if ($status !== 'pending') {
                $r->updates()->create(['user_id' => $facility->id, 'status' => $status, 'note' => $status === 'done' ? 'เปลี่ยนหลอดเรียบร้อย' : 'รับเรื่องแล้ว ช่างจะเข้าตรวจพรุ่งนี้']);
            }
        }
        $aircon->update(['status' => 'repairing']);

        // ---------- จองห้อง/รถ + วัสดุสิ้นเปลือง ----------
        $meeting = \App\Models\BookableResource::create(['name' => 'ห้องประชุม 1', 'type' => 'room', 'capacity' => 30, 'description' => 'มีโปรเจกเตอร์และไมค์']);
        \App\Models\BookableResource::create(['name' => 'หอประชุม', 'type' => 'room', 'capacity' => 400, 'requires_approval' => true]);
        $van = \App\Models\BookableResource::create(['name' => 'รถตู้โรงเรียน (นข 1234)', 'type' => 'vehicle', 'capacity' => 12, 'requires_approval' => true]);
        \App\Models\BookableResource::create(['name' => 'โปรเจกเตอร์พกพา', 'type' => 'equipment']);
        \App\Models\Booking::create(['resource_id' => $meeting->id, 'user_id' => $teachers[1]->id, 'title' => 'ประชุมกลุ่มสาระภาษาไทย',
            'starts_at' => today()->setTime(13, 0), 'ends_at' => today()->setTime(15, 0), 'attendees' => 8, 'status' => 'approved']);
        \App\Models\Booking::create(['resource_id' => $van->id, 'user_id' => $teacher->id, 'title' => 'พานักเรียนแข่งขันคณิตศาสตร์', 'destination' => 'มหาวิทยาลัยขอนแก่น',
            'starts_at' => today()->addDays(3)->setTime(7, 0), 'ends_at' => today()->addDays(3)->setTime(17, 0), 'attendees' => 6, 'status' => 'pending']);

        $paper = \App\Models\Supply::create(['name' => 'กระดาษ A4 80 แกรม', 'unit' => 'รีม', 'category' => 'วัสดุสำนักงาน', 'min_stock' => 20]);
        $toner = \App\Models\Supply::create(['name' => 'ผงหมึกเครื่องพิมพ์ Brother TN-2460', 'unit' => 'กล่อง', 'category' => 'วัสดุคอมพิวเตอร์', 'min_stock' => 2]);
        $marker = \App\Models\Supply::create(['name' => 'ปากกาไวท์บอร์ด', 'unit' => 'ด้าม', 'category' => 'วัสดุการศึกษา', 'min_stock' => 24]);
        auth()->setUser($facility);
        $paper->move('in', 120, 'ยอดยกมา');
        $toner->move('in', 2, 'ยอดยกมา');
        $marker->move('in', 100, 'ยอดยกมา');
        $req = \App\Models\SupplyRequisition::create(['req_no' => 'S2569-0001', 'requester_id' => $teacher->id, 'department' => 'คณิตศาสตร์', 'purpose' => 'จัดทำข้อสอบกลางภาค', 'status' => 'issued', 'reviewed_by' => $facility->id, 'reviewed_at' => now()->subDays(5)]);
        $req->items()->create(['supply_id' => $paper->id, 'quantity' => 10, 'issued' => 10]);
        $paper->move('out', -10, 'จ่ายตามใบเบิก S2569-0001', $req->id);
        $req2 = \App\Models\SupplyRequisition::create(['req_no' => 'S2569-0002', 'requester_id' => $teachers[2]->id, 'department' => 'วิทยาศาสตร์และเทคโนโลยี', 'purpose' => 'ใช้สอน', 'status' => 'pending']);
        $req2->items()->createMany([['supply_id' => $marker->id, 'quantity' => 12], ['supply_id' => $toner->id, 'quantity' => 1]]);
        auth()->logout();

        // ---------- ครูลงเวลา ----------
        $staffAll = $teachers->concat([$admin]);
        foreach ($days as $d) {
            foreach ($staffAll as $u) {
                $late = mt_rand(1, 12) === 1;
                StaffAttendance::create(['user_id' => $u->id, 'date' => $d->toDateString(), 'check_in' => $late ? '08:'.mt_rand(5, 30).':00' : '07:'.mt_rand(20, 55).':00', 'check_out' => '16:'.mt_rand(30, 59).':00', 'status' => $late ? 'late' : 'present']);
            }
        }
    }
}
