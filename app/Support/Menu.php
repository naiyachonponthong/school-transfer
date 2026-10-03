<?php

namespace App\Support;

use App\Models\Admission;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\ExamResponse;
use App\Models\PaymentSlip;
use App\Models\RepairRequest;
use App\Models\StaffLeave;
use App\Models\SupplyRequisition;
use App\Models\User;

/**
 * เมนูทั้งระบบนิยามที่เดียว ใช้ทั้งแถบไอคอนด้านซ้าย หน้า "เมนูทั้งหมด" และไอคอนบนหน้าแรกมือถือ
 * item: [key, label, icon, url, color, badge, active-route-patterns]
 */
class Menu
{
    public static function groups(User $user, int $pendingLeaves = 0): array
    {
        if ($user->isParent()) {
            return self::parentGroups($user);
        }
        if ($user->isStudent()) {
            return self::studentGroups();
        }

        $can = fn (string $permission) => $user->hasPermission($permission);
        $slips = $can('finance.manage') ? PaymentSlip::where('status', 'pending')->count() : 0;
        $staffLeaves = $can('staff.manage') ? StaffLeave::where('status', 'pending')->count() : 0;
        $admissions = $can('admissions.manage') ? Admission::where('status', 'submitted')->count() : 0;

        $groups = [
            'งานประจำวัน' => [
                self::item('attendance', 'เช็คชื่อ', 'bi-check2-square', route('attendance.index'), 'primary', 0, ['attendance.index']),
                self::item('period', 'เช็คชื่อรายคาบ', 'bi-clock-history', route('period-attendance.index'), 'primary', 0, ['period-attendance.*']),
                self::item('gate', 'สแกนหน้าประตู', 'bi-qr-code-scan', route('gate'), 'primary', 0, ['gate']),
                self::item('today', 'สรุปวันนี้', 'bi-clipboard-data', route('attendance.today'), 'primary', 0, ['attendance.today']),
                self::item('leaves', 'ใบลานักเรียน', 'bi-envelope-paper', route('leaves.index'), 'primary', $pendingLeaves, ['leaves.*']),
                self::item('behavior', 'ความประพฤติ', 'bi-award', route('behavior.index'), 'primary', 0, ['behavior.*']),
                self::item('health', 'ห้องพยาบาล', 'bi-heart-pulse', route('health.index'), 'primary', 0, ['health.*']),
            ],
            'วิชาการ' => [
                self::item('students', 'นักเรียน', 'bi-people', route('students.index'), 'primary', 0, ['students.*']),
                self::item('courses', 'คะแนน', 'bi-journal-check', route('courses.index'), 'primary', 0, ['courses.*', 'gradebook.*']),
                // ตัวเลขบนเมนู = แผ่นรอตรวจทาน
                self::item('exams', 'ตรวจข้อสอบ', 'bi-ui-checks-grid', route('exams.index'), 'primary',
                    ExamResponse::where('status', 'review')->whereHas('exam', fn ($q) => $q->managedBy($user))->count(), ['exams.*']),
                self::item('evaluations', 'คุณลักษณะ / อ่านคิดเขียน', 'bi-stars', route('evaluations.index'), 'primary', 0, ['evaluations.*']),
                self::item('timetable', 'ตารางเรียน', 'bi-calendar3-week', route('timetable.index'), 'primary', 0, ['timetable.index']),
                self::item('mytimetable', 'ตารางสอน', 'bi-calendar-check', route('timetable.mine'), 'primary', 0, ['timetable.mine']),
                self::item('calendar', 'ปฏิทินโรงเรียน', 'bi-calendar-event', route('calendar'), 'blue', 0, ['calendar']),
                self::item('homework', 'การบ้าน', 'bi-journal-text', route('homework.index'), 'primary', 0, ['homework.*']),
                self::item('surveys', 'แบบประเมิน', 'bi-clipboard-heart', route('surveys.index'), 'primary', 0, ['surveys.*']),
                self::item('library', 'ห้องสมุด', 'bi-book-half', route('library.loans'), 'teal', 0, ['library.*']),
                self::item('report', 'รายงาน / ปพ.', 'bi-file-earmark-text', route('reports.index'), 'teal', 0, ['reports.*', 'attendance.report']),
                self::item('cards', 'บัตรนักเรียน', 'bi-person-vcard', route('students.cards'), 'teal', 0, ['students.cards']),
            ],
            'บริหารทั่วไป' => array_values(array_filter([
                self::item('repairs', 'แจ้งซ่อม', 'bi-tools', route('repairs.index'), 'teal',
                    $user->canManageFacilities() ? RepairRequest::where('status', 'pending')->count() : 0, ['repairs.*']),
                self::item('bookings', 'จองห้อง/รถ', 'bi-calendar2-check', route('bookings.index'), 'teal',
                    $user->canManageFacilities() ? Booking::where('status', 'pending')->count() : 0, ['bookings.*']),
                self::item('requisitions', 'เบิกวัสดุ', 'bi-bag-check', route('requisitions.index'), 'teal',
                    $user->canManageFacilities() ? SupplyRequisition::where('status', 'pending')->count() : 0, ['requisitions.*']),
                $user->canManageFacilities() ? self::item('supplies', 'คลังวัสดุ', 'bi-boxes', route('supplies.index'), 'teal', 0, ['supplies.*']) : null,
                $user->canManageFacilities() ? self::item('assets', 'ครุภัณฑ์', 'bi-box-seam', route('assets.index'), 'teal', 0, ['assets.*']) : null,
                $user->canManageFacilities() ? self::item('assetcheck', 'ตรวจสอบพัสดุ', 'bi-clipboard-check', route('asset-checks.index'), 'teal', 0, ['asset-checks.*']) : null,
            ])),
            'บุคลากร' => [
                self::item('checkin', 'ลงเวลา', 'bi-fingerprint', route('checkin'), 'primary', 0, ['checkin']),
                self::item('staffleave', 'ลางาน', 'bi-briefcase', route('staff-leaves.index'), 'primary', $staffLeaves, ['staff-leaves.*']),
            ],
            'สื่อสารและการเงิน' => [
                self::item('chat', 'ข้อความ', 'bi-chat-dots', route('chat.index'), 'blue', Conversation::unreadTotal($user), ['chat.*']),
                self::item('feed', 'ฟีดข่าว', 'bi-newspaper', route('feed.index'), 'blue', 0, ['feed.*']),
                self::item('announcements', 'ประกาศ', 'bi-megaphone', route('announcements.index'), 'primary', 0, ['announcements.*']),
                self::item('invoices', 'ค่าธรรมเนียม', 'bi-wallet2', route('invoices.index'), 'teal', 0, ['invoices.*', 'payments.*']),
            ],
        ];

        {
            $groups['สื่อสารและการเงิน'][] = self::item('slips', 'ตรวจสลิป', 'bi-receipt-cutoff', route('slips.index'), 'teal', $slips, ['slips.*']);
            $groups['ผู้ดูแลระบบ'] = [
                self::item('admissions', 'รับสมัครนักเรียน', 'bi-person-plus', route('admissions.index'), 'slate', $admissions, ['admissions.*']),
                self::item('admissionexams', 'สอบคัดเลือก', 'bi-trophy', route('admission-exams.index'), 'slate', 0, ['admission-exams.*']),
                self::item('line', 'LINE แจ้งเตือน', 'bi-chat-dots', route('settings.messages'), 'slate', 0, ['settings.messages']),
                self::item('users', 'ผู้ใช้งาน', 'bi-person-gear', route('users.index'), 'slate', 0, ['users.*']),
                self::item('roles', 'ตำแหน่งและสิทธิ์', 'bi-shield-lock', route('roles.index'), 'slate', 0, ['roles.*']),
                self::item('classrooms', 'ห้องเรียน', 'bi-door-open', route('classrooms.index'), 'slate', 0, ['classrooms.*']),
                self::item('subjects', 'รายวิชา', 'bi-book', route('subjects.index'), 'slate', 0, ['subjects.*']),
                self::item('terms', 'ปีการศึกษา', 'bi-calendar-range', route('terms.index'), 'slate', 0, ['terms.*']),
                self::item('staff', 'เวลาทำงานครู', 'bi-person-check', route('staff-attendance.report'), 'slate', 0, ['staff-attendance.*']),
                self::item('backups', 'สำรองข้อมูล', 'bi-archive', route('backups.index'), 'slate', 0, ['backups.*']),
                self::item('audit', 'ประวัติการแก้ไข', 'bi-clock-history', route('audit.index'), 'slate', 0, ['audit.*']),
                self::item('settings', 'ตั้งค่า', 'bi-gear', route('settings'), 'slate', 0, ['settings*']),
            ];
        }
        $groups['ช่วยเหลือ'] = [self::manualItem()];

        // เมนูที่ต้องมีสิทธิ์ตามตำแหน่งงาน (ที่ไม่อยู่ในรายการนี้ บุคลากรทุกคนเห็น)
        $needs = [
            'gate' => 'gate.use', 'cards' => 'gate.use', 'health' => 'health.manage', 'library' => 'library.manage', 'report' => 'reports.view',
            'invoices' => 'finance.view', 'slips' => 'finance.manage', 'admissions' => 'admissions.manage', 'admissionexams' => 'admissions.manage',
            'line' => 'settings.manage', 'backups' => 'settings.manage', 'settings' => 'settings.manage', 'users' => 'users.manage', 'roles' => 'users.manage',
            'classrooms' => 'academics.manage', 'subjects' => 'academics.manage', 'terms' => 'academics.manage', 'staff' => 'staff.manage', 'audit' => 'audit.view',
        ];

        return array_filter(array_map(
            fn (array $items) => array_values(array_filter($items, fn (array $i) => ! isset($needs[$i['key']]) || $can($needs[$i['key']]))),
            $groups,
        ));
    }

    /** ไอคอนบนแถบซ้าย (เดสก์ท็อป) เรียงตามความถี่การใช้งาน */
    public static function rail(User $user, int $pendingLeaves = 0): array
    {
        if ($user->isParent()) {
            $items = [self::item('home', 'หน้าหลัก', 'bi-house-heart', route('parent.home'), 'primary', 0, ['parent.home'])];
            foreach ($user->children as $child) {
                $items[] = self::item('child'.$child->id, $child->nickname ?: $child->first_name, 'bi-person-badge', route('parent.child', $child), 'primary', 0, [], 'parent/child/'.$child->id);
            }
            $items[] = self::item('leave', 'ส่งใบลา', 'bi-envelope-paper', route('parent.leave'), 'primary', 0, ['parent.leave']);
            $items[] = self::item('homework', 'การบ้าน', 'bi-journal-text', route('parent.homework'), 'primary', 0, ['parent.homework']);
            $items[] = self::item('chat', 'ข้อความ', 'bi-chat-dots', route('chat.index'), 'primary', Conversation::unreadTotal($user), ['chat.*']);
            $items[] = self::item('feed', 'ฟีดข่าว', 'bi-newspaper', route('feed.index'), 'primary', 0, ['feed.*']);
            $items[] = self::item('announcements', 'ประกาศ', 'bi-megaphone', route('announcements.index'), 'primary', 0, ['announcements.*']);
            $items[] = self::manualItem();

            return $items;
        }
        if ($user->isStudent()) {
            $all = collect(self::studentGroups())->flatten(1)->keyBy('key');

            return array_merge(
                [self::item('home', 'หน้าหลัก', 'bi-house', route('student.home'), 'primary', 0, ['student.home'])],
                collect(['homework', 'grades', 'timetable', 'attendance', 'portfolio', 'calendar', 'feed', 'announcements', 'manual'])->map(fn ($k) => $all[$k])->all(),
            );
        }

        $all = collect(self::groups($user, $pendingLeaves))->flatten(1)->keyBy('key');
        $keys = ['attendance', 'period', 'gate', 'leaves', 'students', 'courses', 'exams', 'homework', 'chat', 'calendar', 'feed', 'invoices'];

        return array_merge(
            [self::item('home', 'ภาพรวม', 'bi-grid-1x2', route('home'), 'primary', 0, ['home'])],
            collect($keys)->filter(fn ($k) => isset($all[$k]))->map(fn ($k) => $all[$k])->values()->all(),
            [self::item('menu', 'ทั้งหมด', 'bi-grid-3x3-gap', route('menu'), 'primary', 0, ['menu'])],
        );
    }

    /** ไอคอนตารางบนหน้าแรกมือถือ */
    public static function launcher(User $user, int $pendingLeaves = 0): array
    {
        $all = collect(self::groups($user, $pendingLeaves))->flatten(1)->keyBy('key');
        $keys = match (true) {
            $user->isParent() => ['leave', 'homework', 'grades', 'attendance', 'chat', 'fees', 'portfolio', 'calendar'],
            $user->isStudent() => ['homework', 'grades', 'timetable', 'attendance', 'behavior', 'portfolio', 'transcript', 'calendar'],
            default => ['attendance', 'period', 'gate', 'leaves', 'homework', 'courses', 'exams', 'chat', 'students', 'behavior', 'mytimetable', 'health', 'calendar', 'staffleave', 'repairs', 'surveys', 'library', 'report'],
        };

        return collect($keys)->filter(fn ($k) => isset($all[$k]))->map(fn ($k) => $all[$k])->values()->all();
    }

    /** คู่มือการใช้งาน (ทุกบทบาท) */
    private static function manualItem(): array
    {
        return self::item('manual', 'คู่มือการใช้งาน', 'bi-book', route('manual.index'), 'teal', 0, ['manual.*']);
    }

    private static function studentGroups(): array
    {
        $me = auth()->user()?->studentProfile;
        $tab = fn (string $t) => route('student.info', ['tab' => $t]);

        return [
            'การเรียนของฉัน' => [
                self::item('homework', 'การบ้าน', 'bi-journal-text', route('student.homework'), 'primary', 0, ['student.homework']),
                self::item('grades', 'ผลการเรียน', 'bi-mortarboard', $tab('grades'), 'primary'),
                self::item('timetable', 'ตารางเรียน', 'bi-calendar3-week', $tab('timetable'), 'primary'),
                self::item('attendance', 'การมาเรียน', 'bi-calendar-check', $tab('overview'), 'primary'),
                self::item('behavior', 'ความประพฤติ', 'bi-award', $tab('behavior'), 'primary'),
                self::item('portfolio', 'แฟ้มผลงาน', 'bi-folder2-open', $me ? route('portfolio.show', $me) : route('student.home'), 'primary', 0, ['portfolio.*']),
                self::item('transcript', 'ปพ.1', 'bi-file-earmark-text', $me ? route('transcript', $me) : route('student.home'), 'teal'),
            ],
            'ข่าวสาร' => [
                self::item('calendar', 'ปฏิทิน', 'bi-calendar-event', route('calendar'), 'blue', 0, ['calendar']),
                self::item('feed', 'ฟีดข่าว', 'bi-newspaper', route('feed.index'), 'blue', 0, ['feed.*']),
                self::item('announcements', 'ประกาศ', 'bi-megaphone', route('announcements.index'), 'primary', 0, ['announcements.*']),
                self::item('notifications', 'แจ้งเตือน', 'bi-bell', route('notifications'), 'primary', 0, ['notifications']),
                self::manualItem(),
            ],
        ];
    }

    private static function parentGroups(User $user): array
    {
        $child = $user->children->first();
        $tab = fn (string $t) => $child ? route('parent.child', ['student' => $child, 'tab' => $t]) : route('parent.home');

        return [
            'บุตรหลาน' => [
                self::item('leave', 'ส่งใบลา', 'bi-envelope-paper', route('parent.leave'), 'primary', 0, ['parent.leave']),
                self::item('homework', 'การบ้าน', 'bi-journal-text', route('parent.homework'), 'primary', 0, ['parent.homework']),
                self::item('chat', 'คุยกับครู', 'bi-chat-dots', route('chat.index'), 'blue', Conversation::unreadTotal($user), ['chat.*']),
                self::item('portfolio', 'แฟ้มผลงาน', 'bi-folder2-open', $child ? route('portfolio.show', $child) : route('parent.home'), 'primary', 0, ['portfolio.*']),
                self::item('survey', 'แบบประเมิน', 'bi-clipboard-heart', $tab('survey'), 'primary'),
                self::item('attendance', 'การมาเรียน', 'bi-calendar-check', $tab('overview'), 'primary'),
                self::item('grades', 'ผลการเรียน', 'bi-mortarboard', $tab('grades'), 'primary'),
                self::item('timetable', 'ตารางเรียน', 'bi-calendar3-week', $tab('timetable'), 'primary'),
                self::item('behavior', 'ความประพฤติ', 'bi-award', $tab('behavior'), 'primary'),
                self::item('fees', 'ค่าเทอม', 'bi-wallet2', $tab('fees'), 'teal'),
                self::item('health', 'สุขภาพ', 'bi-heart-pulse', $tab('health'), 'primary'),
                self::item('transcript', 'ปพ.1', 'bi-file-earmark-text', $child ? route('transcript', $child) : route('parent.home'), 'teal'),
            ],
            'ข่าวสาร' => [
                self::item('calendar', 'ปฏิทิน', 'bi-calendar-event', route('calendar'), 'blue', 0, ['calendar']),
                self::item('feed', 'ฟีดข่าว', 'bi-newspaper', route('feed.index'), 'blue', 0, ['feed.*']),
                self::item('announcements', 'ประกาศ', 'bi-megaphone', route('announcements.index'), 'primary', 0, ['announcements.*']),
                self::item('notifications', 'แจ้งเตือน', 'bi-bell', route('notifications'), 'primary', 0, ['notifications']),
                self::manualItem(),
            ],
        ];
    }

    private static function item(string $key, string $label, string $icon, string $url, string $color = 'primary', int $badge = 0, array $routes = [], ?string $path = null): array
    {
        return compact('key', 'label', 'icon', 'url', 'color', 'badge', 'routes', 'path');
    }

    public static function isActive(array $item): bool
    {
        if ($item['path'] && request()->is($item['path'])) {
            return true;
        }

        return $item['routes'] && request()->routeIs(...$item['routes']);
    }
}
