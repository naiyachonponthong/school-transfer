<?php

use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AdmissionExamController;
use App\Http\Controllers\AdmissionFormController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AssetCheckController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\NumberingController;
use App\Http\Controllers\ApplyController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\BehaviorController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamScanController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\GateController;
use App\Http\Controllers\GraduateController;
use App\Http\Controllers\GradebookController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeworkController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\ParentController;
use App\Http\Controllers\PeriodAttendanceController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SlipController;
use App\Http\Controllers\StaffAttendanceController;
use App\Http\Controllers\StaffLeaveController;
use App\Http\Controllers\StudentAccountController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\TermController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\TranscriptController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ---- สาธารณะ: รับสมัครนักเรียนออนไลน์ + webhook ของ LINE ----
Route::get('/app.webmanifest', ManifestController::class)->name('app.manifest');
Route::get('/apply', [ApplyController::class, 'start'])->name('apply');
Route::post('/apply/start', [ApplyController::class, 'begin'])->name('apply.begin')->middleware('throttle:10,1');
Route::get('/apply/form/{step}', [ApplyController::class, 'step'])->name('apply.step')->where('step', '[a-z]+');
Route::post('/apply/form/{step}', [ApplyController::class, 'saveStep'])->name('apply.save')->where('step', '[a-z]+')->middleware('throttle:60,1');
Route::post('/apply/submit', [ApplyController::class, 'submit'])->name('apply.submit')->middleware('throttle:10,1');
Route::post('/apply/leave', [ApplyController::class, 'leave'])->name('apply.leave');
Route::get('/apply/status', [ApplyController::class, 'status'])->name('apply.status')->middleware('throttle:30,1');
Route::post('/apply/slip', [ApplyController::class, 'slip'])->name('apply.slip')->middleware('throttle:10,1');
Route::get('/apply/print/{doc}', [ApplyController::class, 'print'])->name('apply.print');
Route::get('/apply/files/{question}', [ApplyController::class, 'file'])->name('apply.file');
Route::post('/line/webhook', [LineController::class, 'webhook'])->name('line.webhook');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');
    // ลืมรหัสผ่าน: ส่งรหัสยืนยันทาง LINE
    Route::get('/forgot-password', [PasswordController::class, 'showForgot'])->name('password.forgot');
    Route::post('/forgot-password', [PasswordController::class, 'sendCode'])->name('password.forgot.send')->middleware('throttle:5,1');
    Route::get('/reset-password', [PasswordController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordController::class, 'reset'])->name('password.reset.save')->middleware('throttle:10,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/password/change', [PasswordController::class, 'showChange'])->name('password.change');
    Route::post('/password/change', [PasswordController::class, 'change'])->name('password.change.save');
    Route::get('/', [DashboardController::class, 'index'])->name('home');

    Route::get('/menu', [MenuController::class, 'index'])->name('menu');
    Route::get('/notifications', [MenuController::class, 'notifications'])->name('notifications');

    Route::get('/feed', [FeedController::class, 'index'])->name('feed.index');
    Route::post('/feed', [FeedController::class, 'store'])->name('feed.store');
    Route::delete('/feed/{post}', [FeedController::class, 'destroy'])->name('feed.destroy');
    Route::post('/feed/{post}/react', [FeedController::class, 'react'])->name('feed.react');

    Route::post('/profile/line', [LineController::class, 'createCode'])->name('profile.line');
    Route::delete('/profile/line', [LineController::class, 'unlink'])->name('profile.line.unlink');
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::get('/transcript/{student}', [TranscriptController::class, 'show'])->name('transcript');
    Route::post('/invoices/{invoice}/slips', [SlipController::class, 'store'])->name('slips.store');

    // แชท (ครู ↔ ผู้ปกครอง) — นักเรียนไม่ใช้
    Route::middleware('role:admin,teacher,parent')->group(function () {
        Route::get('/chat', [ChatController::class, 'index'])->name('chat.index');
        Route::post('/chat', [ChatController::class, 'start'])->name('chat.start');
        Route::get('/chat/{conversation}/poll', [ChatController::class, 'poll'])->name('chat.poll');
        Route::post('/chat/{conversation}', [ChatController::class, 'send'])->name('chat.send')->middleware('throttle:40,1');
    });

    // แฟ้มผลงาน + แบบประเมิน (ครูและผู้ปกครองของนักเรียนคนนั้น)
    Route::get('/portfolio/{student}', [PortfolioController::class, 'show'])->name('portfolio.show');
    Route::post('/portfolio/{student}', [PortfolioController::class, 'store'])->name('portfolio.store');
    Route::delete('/portfolio-items/{work}', [PortfolioController::class, 'destroy'])->name('portfolio.destroy');
    Route::get('/surveys/{survey}/students/{student}', [SurveyController::class, 'fill'])->name('surveys.fill');
    Route::post('/surveys/{survey}/students/{student}', [SurveyController::class, 'save'])->name('surveys.save');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // ประกาศ: ทุกบทบาทอ่านได้ (กรองตามกลุ่มเป้าหมาย)
    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/{announcement}', [AnnouncementController::class, 'show'])->name('announcements.show')->whereNumber('announcement');

    // ใบแจ้งหนี้/ใบเสร็จ: ผู้ปกครองดูของลูกตัวเองได้
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show')->whereNumber('invoice');
    Route::get('/payments/{payment}/receipt', [InvoiceController::class, 'receipt'])->name('payments.receipt');
    Route::get('/report-card/{student}', [ReportCardController::class, 'show'])->name('report-card');

    /* ---------------- ผู้ปกครอง ---------------- */
    Route::middleware('role:parent')->prefix('parent')->name('parent.')->group(function () {
        Route::get('/', [ParentController::class, 'home'])->name('home');
        Route::get('/child/{student}', [ParentController::class, 'child'])->name('child');
        Route::get('/leave', [ParentController::class, 'leaveForm'])->name('leave');
        Route::post('/leave', [ParentController::class, 'leaveStore'])->name('leave.store');
        Route::get('/homework', [HomeworkController::class, 'parentIndex'])->name('homework');
        Route::post('/homework/{assignment}', [HomeworkController::class, 'submit'])->name('homework.submit');
    });

    /* ---------------- นักเรียน ---------------- */
    Route::middleware('role:student')->prefix('me')->name('student.')->group(function () {
        Route::get('/', [StudentPortalController::class, 'home'])->name('home');
        Route::get('/info', [StudentPortalController::class, 'info'])->name('info');
        Route::get('/homework', [HomeworkController::class, 'parentIndex'])->name('homework');
        Route::post('/homework/{assignment}', [HomeworkController::class, 'submit'])->name('homework.submit');
    });

    /* ---------------- ครู + ผู้ดูแล ---------------- */
    Route::middleware('role:admin,teacher')->group(function () {
        Route::get('/search', SearchController::class)->name('search');

        // เช็คชื่อรายคาบ
        Route::get('/period-attendance', [PeriodAttendanceController::class, 'index'])->name('period-attendance.index');
        Route::get('/period-attendance/{course}', [PeriodAttendanceController::class, 'sheet'])->name('period-attendance.sheet');
        Route::post('/period-attendance/{course}', [PeriodAttendanceController::class, 'save'])->name('period-attendance.save');
        Route::get('/period-attendance/{course}/report', [PeriodAttendanceController::class, 'report'])->name('period-attendance.report');

        // ตรวจข้อสอบด้วยมือถือ (ScanGrade)
        Route::get('/exams', [ExamController::class, 'index'])->name('exams.index');
        Route::post('/exams', [ExamController::class, 'store'])->name('exams.store');
        Route::get('/exams/{exam}', [ExamController::class, 'show'])->name('exams.show');
        Route::put('/exams/{exam}', [ExamController::class, 'update'])->name('exams.update');
        Route::delete('/exams/{exam}', [ExamController::class, 'destroy'])->name('exams.destroy');
        Route::post('/exams/{exam}/key', [ExamController::class, 'saveKey'])->name('exams.key');
        Route::post('/exams/{exam}/duplicate', [ExamController::class, 'duplicate'])->name('exams.duplicate');
        Route::get('/exams/{exam}/sheets', [ExamController::class, 'sheets'])->name('exams.sheets');
        Route::get('/exams/{exam}/results', [ExamController::class, 'results'])->name('exams.results');
        Route::get('/exams/{exam}/export', [ExamController::class, 'export'])->name('exams.export');
        Route::post('/exams/{exam}/sync', [ExamController::class, 'sync'])->name('exams.sync');
        Route::get('/exams/{exam}/analysis', [ExamController::class, 'analysis'])->name('exams.analysis');
        Route::get('/exams/{exam}/scan', [ExamScanController::class, 'scan'])->name('exams.scan');
        Route::get('/exams/{exam}/roster', [ExamScanController::class, 'roster'])->name('exams.roster');
        Route::post('/exams/{exam}/responses', [ExamScanController::class, 'submit'])->name('exams.submit')->middleware('throttle:120,1');
        Route::get('/exams/{exam}/responses/{response}', [ExamScanController::class, 'review'])->name('exams.review');
        Route::put('/exams/{exam}/responses/{response}', [ExamScanController::class, 'update'])->name('exams.review.update');
        Route::get('/exams/{exam}/responses/{response}/image', [ExamScanController::class, 'image'])->name('exams.image');

        // บัญชีนักเรียน
        Route::post('/student-accounts', [StudentAccountController::class, 'createForClassroom'])->name('student-accounts.create');
        Route::get('/student-accounts/slips', [StudentAccountController::class, 'slips'])->name('student-accounts.slips');
        Route::post('/student-accounts/{student}/reset', [StudentAccountController::class, 'reset'])->name('student-accounts.reset');
        Route::post('/student-accounts/{student}/toggle', [StudentAccountController::class, 'disable'])->name('student-accounts.toggle');

        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::get('/attendance/today', [AttendanceController::class, 'today'])->name('attendance.today');
        Route::get('/attendance/report', [AttendanceController::class, 'report'])->name('attendance.report');

        Route::get('/students/import', [StudentImportController::class, 'form'])->name('students.import');
        Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import.store');
        Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template');
        Route::resource('students', StudentController::class);
        Route::post('/students/{student}/guardians', [StudentController::class, 'addGuardian'])->name('students.guardians.store');
        Route::delete('/students/{student}/guardians/{user}', [StudentController::class, 'removeGuardian'])->name('students.guardians.destroy');

        Route::get('/leaves', [LeaveRequestController::class, 'index'])->name('leaves.index');
        Route::post('/leaves', [LeaveRequestController::class, 'store'])->name('leaves.store');
        Route::get('/leaves/{leave}', [LeaveRequestController::class, 'show'])->name('leaves.show');
        Route::post('/leaves/{leave}/approve', [LeaveRequestController::class, 'approve'])->name('leaves.approve');
        Route::post('/leaves/{leave}/reject', [LeaveRequestController::class, 'reject'])->name('leaves.reject');

        Route::get('/behavior', [BehaviorController::class, 'index'])->name('behavior.index');
        Route::post('/behavior', [BehaviorController::class, 'store'])->name('behavior.store');
        Route::delete('/behavior/{record}', [BehaviorController::class, 'destroy'])->name('behavior.destroy');

        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('/courses/{course}/grades', [GradebookController::class, 'show'])->name('gradebook.show');
        Route::post('/courses/{course}/grades', [GradebookController::class, 'save'])->name('gradebook.save');
        Route::post('/courses/{course}/outcomes/ms', [GradebookController::class, 'applyMs'])->name('gradebook.ms');
        Route::post('/courses/{course}/outcomes/{student}', [GradebookController::class, 'saveOutcome'])->name('gradebook.outcome');
        Route::post('/courses/{course}/assessments', [GradebookController::class, 'storeAssessment'])->name('assessments.store');
        Route::put('/assessments/{assessment}', [GradebookController::class, 'updateAssessment'])->name('assessments.update');
        Route::delete('/assessments/{assessment}', [GradebookController::class, 'destroyAssessment'])->name('assessments.destroy');
        Route::get('/courses/{course}/export', [GradebookController::class, 'export'])->name('gradebook.export');
        Route::get('/courses/{course}/pp5', [GradebookController::class, 'pp5'])->name('gradebook.pp5');

        // แจ้งซ่อม (ครูทุกคนแจ้งได้ · งานอาคารสถานที่จัดการ)
        Route::get('/repairs', [RepairController::class, 'index'])->name('repairs.index');
        Route::get('/repairs/create', [RepairController::class, 'create'])->name('repairs.create');
        Route::post('/repairs', [RepairController::class, 'store'])->name('repairs.store');
        Route::get('/repairs/report', [RepairController::class, 'report'])->name('repairs.report');
        Route::get('/repairs/{repair}', [RepairController::class, 'show'])->name('repairs.show');
        Route::put('/repairs/{repair}', [RepairController::class, 'update'])->name('repairs.update');
        Route::post('/repairs/{repair}/cancel', [RepairController::class, 'cancel'])->name('repairs.cancel');

        // จองห้อง / รถ / อุปกรณ์
        Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::get('/bookings/create', [BookingController::class, 'create'])->name('bookings.create');
        Route::get('/bookings/day', [BookingController::class, 'day'])->name('bookings.day');
        Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
        Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');
        Route::post('/bookings/{booking}/review', [BookingController::class, 'review'])->name('bookings.review');
        Route::get('/booking-resources', [BookingController::class, 'resources'])->name('bookings.resources');
        Route::post('/booking-resources', [BookingController::class, 'saveResource'])->name('bookings.resources.store');
        Route::get('/booking-resources/create', [BookingController::class, 'resourceForm'])->name('bookings.resources.create');
        Route::get('/booking-resources/{resource}/edit', [BookingController::class, 'resourceForm'])->name('bookings.resources.edit');
        Route::put('/booking-resources/{resource}', [BookingController::class, 'saveResource'])->name('bookings.resources.update');

        // วัสดุสิ้นเปลือง: ใบเบิก (ครูทุกคน) + คลัง/บัญชีวัสดุ (งานพัสดุ)
        Route::get('/requisitions', [RequisitionController::class, 'index'])->name('requisitions.index');
        Route::get('/requisitions/create', [RequisitionController::class, 'create'])->name('requisitions.create');
        Route::post('/requisitions', [RequisitionController::class, 'store'])->name('requisitions.store');
        Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show'])->name('requisitions.show');
        Route::post('/requisitions/{requisition}/issue', [RequisitionController::class, 'issue'])->name('requisitions.issue');
        Route::post('/requisitions/{requisition}/reject', [RequisitionController::class, 'reject'])->name('requisitions.reject');
        Route::post('/requisitions/{requisition}/cancel', [RequisitionController::class, 'cancel'])->name('requisitions.cancel');
        Route::get('/supplies', [SupplyController::class, 'index'])->name('supplies.index');
        Route::post('/supplies', [SupplyController::class, 'store'])->name('supplies.store');
        Route::get('/supplies/create', [SupplyController::class, 'create'])->name('supplies.create');
        Route::get('/supplies/next-number', [SupplyController::class, 'nextNumber'])->name('supplies.next-number');
        Route::get('/supplies/numbering', [NumberingController::class, 'show'])->defaults('kind', 'supplies')->name('supplies.numbering');
        Route::put('/supplies/numbering', [NumberingController::class, 'save'])->defaults('kind', 'supplies')->name('supplies.numbering.update');
        Route::get('/supplies/{supply}/edit', [SupplyController::class, 'edit'])->name('supplies.edit');
        Route::get('/supplies/report', [SupplyController::class, 'report'])->name('supplies.report');
        Route::get('/supplies/{supply}', [SupplyController::class, 'show'])->name('supplies.show');
        Route::put('/supplies/{supply}', [SupplyController::class, 'update'])->name('supplies.update');
        Route::post('/supplies/{supply}/move', [SupplyController::class, 'move'])->name('supplies.move');

        // ครุภัณฑ์ + ตรวจสอบพัสดุประจำปี (งานพัสดุ) · QR บนสติกเกอร์เปิด /a/{token}
        // ใช้ /inventory ไม่ใช่ /assets เพราะชนกับโฟลเดอร์ public/assets (เว็บเซิร์ฟเวอร์จะเสิร์ฟโฟลเดอร์แทนหน้าเว็บ)
        Route::get('/a/{token}', [AssetController::class, 'go'])->name('assets.go')->where('token', '[A-Za-z0-9]+');
        Route::get('/inventory/labels', [AssetController::class, 'labels'])->name('assets.labels');
        Route::get('/inventory/import', [AssetController::class, 'importForm'])->name('assets.import');
        Route::post('/inventory/import', [AssetController::class, 'import'])->name('assets.import.store');
        Route::get('/inventory/next-number', [AssetController::class, 'nextNumber'])->name('assets.next-number');
        Route::get('/inventory/numbering', [NumberingController::class, 'show'])->defaults('kind', 'assets')->name('assets.numbering');
        Route::put('/inventory/numbering', [NumberingController::class, 'save'])->defaults('kind', 'assets')->name('assets.numbering.update');
        Route::get('/asset-checks', [AssetCheckController::class, 'index'])->name('asset-checks.index');
        Route::get('/asset-checks/scan', [AssetCheckController::class, 'scan'])->name('asset-checks.scan');
        Route::post('/asset-checks', [AssetCheckController::class, 'record'])->name('asset-checks.record');
        Route::resource('inventory', AssetController::class)->names('assets')->parameters(['inventory' => 'asset']);

        // คุณลักษณะอันพึงประสงค์ + อ่าน คิดวิเคราะห์ และเขียน (ครูประจำชั้น)
        Route::get('/evaluations', [EvaluationController::class, 'index'])->name('evaluations.index');
        Route::post('/evaluations/{classroom}', [EvaluationController::class, 'save'])->name('evaluations.save');

        Route::get('/timetable', [TimetableController::class, 'index'])->name('timetable.index');
        Route::get('/timetable/mine', [TimetableController::class, 'mine'])->name('timetable.mine');

        Route::get('/checkin', [StaffAttendanceController::class, 'index'])->name('checkin');
        Route::post('/checkin', [StaffAttendanceController::class, 'store'])->name('checkin.store');

        Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');

        // ประตูโรงเรียน / บัตรนักเรียน
        Route::get('/gate', [GateController::class, 'index'])->name('gate');
        Route::post('/gate/scan', [GateController::class, 'scan'])->name('gate.scan')->middleware('throttle:240,1');
        Route::get('/student-cards', [GateController::class, 'cards'])->name('students.cards');

        // ห้องพยาบาล
        Route::get('/health', [HealthController::class, 'index'])->name('health.index');
        Route::post('/health', [HealthController::class, 'store'])->name('health.store');
        Route::put('/health/{visit}', [HealthController::class, 'update'])->name('health.update');
        Route::get('/health/measure', [HealthController::class, 'measure'])->name('health.measure');
        Route::post('/health/measure', [HealthController::class, 'saveMeasure'])->name('health.measure.save');

        // ห้องสมุด
        Route::get('/library', [LibraryController::class, 'index'])->name('library.index');
        Route::post('/library', [LibraryController::class, 'store'])->name('library.store');
        Route::put('/library/{book}', [LibraryController::class, 'update'])->name('library.update');
        Route::delete('/library/{book}', [LibraryController::class, 'destroy'])->name('library.destroy');
        Route::get('/library-loans', [LibraryController::class, 'circulation'])->name('library.loans');
        Route::post('/library-loans/borrow', [LibraryController::class, 'borrow'])->name('library.borrow');
        Route::post('/library-loans/return', [LibraryController::class, 'returnByCode'])->name('library.return.code');
        Route::post('/library-loans/{loan}/return', [LibraryController::class, 'return'])->name('library.return');
        Route::post('/library-loans/remind', [LibraryController::class, 'remindOverdue'])->name('library.remind');

        // ลางานบุคลากร
        Route::get('/staff-leaves', [StaffLeaveController::class, 'index'])->name('staff-leaves.index');
        Route::post('/staff-leaves', [StaffLeaveController::class, 'store'])->name('staff-leaves.store');
        Route::delete('/staff-leaves/{leave}', [StaffLeaveController::class, 'destroy'])->name('staff-leaves.destroy');

        // การบ้าน
        Route::get('/homework', [HomeworkController::class, 'index'])->name('homework.index');
        Route::post('/homework', [HomeworkController::class, 'store'])->name('homework.store');
        Route::get('/homework/{assignment}', [HomeworkController::class, 'show'])->name('homework.show');
        Route::delete('/homework/{assignment}', [HomeworkController::class, 'destroy'])->name('homework.destroy');
        Route::post('/homework/{assignment}/grade', [HomeworkController::class, 'grade'])->name('homework.grade');
        Route::post('/homework/{assignment}/sync', [HomeworkController::class, 'sync'])->name('homework.sync');

        // แบบประเมิน
        Route::get('/surveys', [SurveyController::class, 'index'])->name('surveys.index');
        Route::get('/surveys/{survey}/classroom', [SurveyController::class, 'classroom'])->name('surveys.classroom');
        Route::post('/portfolio-items/{work}/verify', [PortfolioController::class, 'verify'])->name('portfolio.verify');

        // รายงาน
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/report-cards', [ReportCardController::class, 'classroom'])->name('report-cards.classroom');
        Route::get('/reports/dmc', [ReportController::class, 'dmc'])->name('reports.dmc');
    });

    /* ---------------- ผู้ดูแลระบบ ---------------- */
    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class)->except('show');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

        Route::get('/classrooms', [ClassroomController::class, 'index'])->name('classrooms.index');
        Route::post('/classrooms', [ClassroomController::class, 'store'])->name('classrooms.store');
        Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update');
        Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy');
        Route::post('/classrooms/promote', [ClassroomController::class, 'promote'])->name('classrooms.promote');

        Route::resource('subjects', SubjectController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index');
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/backups', [BackupController::class, 'run'])->name('backups.run');
        Route::get('/backups/{name}', [BackupController::class, 'download'])->name('backups.download')->where('name', '[A-Za-z0-9._-]+');

        // ปพ.7 ใบรับรองผลการศึกษา + ทะเบียนคุม
        Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index');
        Route::get('/certificates/{issue}', [CertificateController::class, 'show'])->name('certificates.show');
        Route::get('/students/{student}/certificate', [CertificateController::class, 'create'])->name('certificates.create');
        Route::post('/students/{student}/certificate', [CertificateController::class, 'store'])->name('certificates.store');
        Route::post('/students/{student}/transcript-issue', [TranscriptController::class, 'issue'])->name('transcript.issue');

        // ปพ.3 รายงานผู้สำเร็จการศึกษา
        Route::get('/graduates', [GraduateController::class, 'index'])->name('graduates.index');
        Route::post('/graduates/approve', [GraduateController::class, 'approve'])->name('graduates.approve');

        Route::post('/courses', [CourseController::class, 'store'])->name('courses.store');
        Route::post('/courses/bulk', [CourseController::class, 'bulk'])->name('courses.bulk');
        Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update');
        Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');

        Route::post('/timetable', [TimetableController::class, 'save'])->name('timetable.save');

        Route::get('/terms', [TermController::class, 'index'])->name('terms.index');
        Route::post('/terms', [TermController::class, 'store'])->name('terms.store');
        Route::put('/terms/{term}', [TermController::class, 'update'])->name('terms.update');
        Route::post('/terms/{term}/current', [TermController::class, 'makeCurrent'])->name('terms.current');

        Route::get('/settings', [SettingController::class, 'edit'])->name('settings');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/rules', [SettingController::class, 'storeRule'])->name('settings.rules.store');
        Route::put('/settings/rules/{rule}', [SettingController::class, 'updateRule'])->name('settings.rules.update');
        Route::delete('/settings/rules/{rule}', [SettingController::class, 'destroyRule'])->name('settings.rules.destroy');

        Route::get('/staff-attendance', [StaffAttendanceController::class, 'report'])->name('staff-attendance.report');

        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'pay'])->name('invoices.pay');
        Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');

        Route::get('/slips', [SlipController::class, 'index'])->name('slips.index');
        Route::post('/slips/{slip}/approve', [SlipController::class, 'approve'])->name('slips.approve');
        Route::post('/slips/{slip}/reject', [SlipController::class, 'reject'])->name('slips.reject');

        Route::post('/staff-leaves/{leave}/approve', [StaffLeaveController::class, 'approve'])->name('staff-leaves.approve');
        Route::post('/staff-leaves/{leave}/reject', [StaffLeaveController::class, 'reject'])->name('staff-leaves.reject');

        // สอบคัดเลือก: ห้องสอบ/เลขประจำตัวสอบ · วิชาสอบ (ตรวจด้วยระบบตรวจข้อสอบ) · จัดอันดับ · ประกาศผล
        Route::get('/admission-exams', [AdmissionExamController::class, 'index'])->name('admission-exams.index');
        Route::post('/admission-exams', [AdmissionExamController::class, 'store'])->name('admission-exams.store');
        Route::get('/admission-exams/{round}', [AdmissionExamController::class, 'show'])->name('admission-exams.show');
        Route::put('/admission-exams/{round}', [AdmissionExamController::class, 'update'])->name('admission-exams.update');
        Route::post('/admission-exams/{round}/seats', [AdmissionExamController::class, 'seats'])->name('admission-exams.seats');
        Route::post('/admission-exams/{round}/subjects', [AdmissionExamController::class, 'addSubject'])->name('admission-exams.subjects');
        Route::post('/admission-exams/{round}/publish', [AdmissionExamController::class, 'publish'])->name('admission-exams.publish');
        Route::delete('/admission-exams/{round}/publish', [AdmissionExamController::class, 'unpublish'])->name('admission-exams.unpublish');
        Route::get('/admission-exams/{round}/print/{doc}', [AdmissionExamController::class, 'print'])->name('admission-exams.print');
        Route::get('/admission-exams/{round}/export', [AdmissionExamController::class, 'export'])->name('admission-exams.export');

        Route::get('/admissions', [AdmissionController::class, 'index'])->name('admissions.index');
        Route::get('/admissions/form', [AdmissionFormController::class, 'edit'])->name('admissions.form');
        Route::put('/admissions/form', [AdmissionFormController::class, 'update'])->name('admissions.form.update');
        Route::get('/admissions/export', [AdmissionController::class, 'export'])->name('admissions.export');
        Route::get('/admissions/{admission}', [AdmissionController::class, 'show'])->name('admissions.show');
        Route::get('/admissions/{admission}/document', [AdmissionController::class, 'document'])->name('admissions.document');
        Route::get('/admissions/{admission}/files/{question}', [AdmissionController::class, 'answerFile'])->name('admissions.file');
        Route::put('/admissions/{admission}', [AdmissionController::class, 'update'])->name('admissions.update');
        Route::put('/admissions/{admission}/exam', [AdmissionController::class, 'exam'])->name('admissions.exam');
        Route::post('/admissions/{admission}/fee', [AdmissionController::class, 'fee'])->name('admissions.fee');
        Route::get('/admissions/{admission}/print/{doc}', [AdmissionController::class, 'print'])->name('admissions.print');
        Route::post('/admissions/{admission}/enroll', [AdmissionController::class, 'enroll'])->name('admissions.enroll');

        Route::post('/calendar', [CalendarController::class, 'store'])->name('calendar.store');
        Route::put('/calendar/{event}', [CalendarController::class, 'update'])->name('calendar.update');
        Route::delete('/calendar/{event}', [CalendarController::class, 'destroy'])->name('calendar.destroy');

        Route::get('/surveys/create', [SurveyController::class, 'create'])->name('surveys.create');
        Route::post('/surveys', [SurveyController::class, 'store'])->name('surveys.store');
        Route::get('/surveys/{survey}/edit', [SurveyController::class, 'edit'])->name('surveys.edit');
        Route::put('/surveys/{survey}', [SurveyController::class, 'update'])->name('surveys.update');

        Route::get('/settings/messages', [LineController::class, 'logs'])->name('settings.messages');
        Route::post('/settings/line-test', [LineController::class, 'test'])->name('settings.line-test');
    });
});
