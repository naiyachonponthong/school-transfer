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
use App\Http\Controllers\CareController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\ConsentController;
use App\Http\Controllers\CourseApprovalController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseMemberController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamScanController;
use App\Http\Controllers\ExecutiveController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FeePlanController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\GateController;
use App\Http\Controllers\GraduateController;
use App\Http\Controllers\GradebookController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeworkController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\LibraryController;
use App\Http\Controllers\LibraryLabelController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\OfficeDocumentController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\ParentController;
use App\Http\Controllers\PeriodAttendanceController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportCardController;
use App\Http\Controllers\RepairController;
use App\Http\Controllers\RequisitionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SlipController;
use App\Http\Controllers\StaffAttendanceController;
use App\Http\Controllers\StaffLeaveController;
use App\Http\Controllers\StaffProfileController;
use App\Http\Controllers\StudentAccountController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentImportController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SubstitutionController;
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

Route::middleware(['auth', 'privacy.accepted'])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/files/{type}/{id}', [FileController::class, 'show'])->name('files.show')->whereNumber('id');
    Route::get('/privacy', [PrivacyController::class, 'show'])->name('privacy.show');
    Route::post('/privacy', [PrivacyController::class, 'accept'])->name('privacy.accept');
    Route::get('/password/change', [PasswordController::class, 'showChange'])->name('password.change');
    Route::post('/password/change', [PasswordController::class, 'change'])->name('password.change.save');
    Route::get('/', [DashboardController::class, 'index'])->name('home');

    Route::get('/menu', [MenuController::class, 'index'])->name('menu');
    Route::get('/notifications', [MenuController::class, 'notifications'])->name('notifications');

    // คู่มือการใช้งาน (ทุกบทบาท เห็นเฉพาะหัวข้อของตัวเอง)
    Route::get('/manual', [ManualController::class, 'index'])->name('manual.index');
    Route::get('/manual/print', [ManualController::class, 'print'])->name('manual.print');
    Route::get('/manual/{topic}', [ManualController::class, 'show'])->name('manual.show')->where('topic', '[a-z\-]+');

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
        Route::get('/consents', [ConsentController::class, 'parentIndex'])->name('consents');
        Route::post('/consents/{form}', [ConsentController::class, 'respond'])->name('consents.respond');
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

        Route::get('/students/import', [StudentImportController::class, 'form'])->name('students.import')->middleware('permission:students.edit');
        Route::post('/students/import', [StudentImportController::class, 'store'])->name('students.import.store')->middleware('permission:students.edit');
        Route::get('/students/import/template', [StudentImportController::class, 'template'])->name('students.import.template')->middleware('permission:students.edit');
        Route::resource('students', StudentController::class)
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'permission:students.edit');
        Route::post('/students/{student}/guardians', [StudentController::class, 'addGuardian'])->name('students.guardians.store')->middleware('permission:students.edit');
        Route::delete('/students/{student}/guardians/{user}', [StudentController::class, 'removeGuardian'])->name('students.guardians.destroy')->middleware('permission:students.edit');

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

        // ทะเบียนบุคลากร (เจ้าตัวดู/แก้ของตัวเองได้ ฝ่ายบุคคลดูได้ทุกคน — ตรวจใน controller)
        Route::get('/staff', [StaffProfileController::class, 'index'])->name('staff.index')->middleware('permission:staff.manage');
        Route::get('/staff/{user}', [StaffProfileController::class, 'show'])->name('staff.show');
        Route::put('/staff/{user}', [StaffProfileController::class, 'update'])->name('staff.update');
        Route::post('/staff/{user}/trainings', [StaffProfileController::class, 'storeTraining'])->name('staff.trainings.store');
        Route::delete('/staff-trainings/{training}', [StaffProfileController::class, 'destroyTraining'])->name('staff.trainings.destroy');

        // สารบรรณ
        Route::get('/office', [OfficeDocumentController::class, 'index'])->name('office.index');
        Route::post('/office', [OfficeDocumentController::class, 'store'])->name('office.store')->middleware('permission:office.manage');
        Route::get('/office/{document}', [OfficeDocumentController::class, 'show'])->name('office.show');
        Route::post('/office/{document}/recipients', [OfficeDocumentController::class, 'addRecipients'])->name('office.recipients')->middleware('permission:office.manage');
        Route::post('/office/{document}/acknowledge', [OfficeDocumentController::class, 'acknowledge'])->name('office.acknowledge');

        // ดูแลช่วยเหลือนักเรียน (ครูประจำชั้น = ห้องตัวเอง, care.manage = ทุกห้อง — ตรวจใน controller)
        Route::get('/care', [CareController::class, 'index'])->name('care.index');
        Route::get('/care/create', [CareController::class, 'create'])->name('care.create');
        Route::post('/care', [CareController::class, 'store'])->name('care.store');
        Route::get('/care/visits', [CareController::class, 'visits'])->name('care.visits');
        Route::get('/care/visits/{student}', [CareController::class, 'visitForm'])->name('care.visits.form');
        Route::post('/care/visits/{student}', [CareController::class, 'saveVisit'])->name('care.visits.save');
        Route::get('/care/visits/{student}/print', [CareController::class, 'printVisit'])->name('care.visits.print');
        Route::get('/care/{case}', [CareController::class, 'show'])->name('care.show')->whereNumber('case');
        Route::put('/care/{case}', [CareController::class, 'update'])->name('care.update')->whereNumber('case');
        Route::post('/care/{case}/actions', [CareController::class, 'addAction'])->name('care.actions.store')->whereNumber('case');

        // หนังสือขออนุญาตผู้ปกครอง
        Route::get('/consents', [ConsentController::class, 'index'])->name('consents.index');
        Route::post('/consents', [ConsentController::class, 'store'])->name('consents.store');
        Route::get('/consents/{form}', [ConsentController::class, 'show'])->name('consents.show');
        Route::post('/consents/{form}/record', [ConsentController::class, 'record'])->name('consents.record');
        Route::post('/consents/{form}/close', [ConsentController::class, 'close'])->name('consents.close');
        Route::post('/courses/{course}/submit', [CourseApprovalController::class, 'submit'])->name('courses.submit');
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

        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index')->middleware('permission:finance.view');

        // ประตูโรงเรียน / บัตรนักเรียน
        Route::get('/gate', [GateController::class, 'index'])->name('gate')->middleware('permission:gate.use');
        Route::post('/gate/scan', [GateController::class, 'scan'])->name('gate.scan')->middleware('throttle:240,1')->middleware('permission:gate.use');
        Route::get('/student-cards', [GateController::class, 'cards'])->name('students.cards')->middleware('permission:gate.use');

        // ห้องพยาบาล
        Route::get('/health', [HealthController::class, 'index'])->name('health.index')->middleware('permission:health.manage');
        Route::post('/health', [HealthController::class, 'store'])->name('health.store')->middleware('permission:health.manage');
        Route::put('/health/{visit}', [HealthController::class, 'update'])->name('health.update')->middleware('permission:health.manage');
        Route::get('/health/measure', [HealthController::class, 'measure'])->name('health.measure')->middleware('permission:health.manage');
        Route::post('/health/measure', [HealthController::class, 'saveMeasure'])->name('health.measure.save')->middleware('permission:health.manage');

        // ห้องสมุด
        // ห้องสมุด: ระเบียนบรรณานุกรม (DDC) + ตัวเล่ม + ป้ายสัน/บาร์โค้ด
        Route::get('/library', [LibraryController::class, 'index'])->name('library.index')->middleware('permission:library.manage');
        Route::post('/library', [LibraryController::class, 'store'])->name('library.store')->middleware('permission:library.manage');
        Route::get('/library/create', [LibraryController::class, 'create'])->name('library.create')->middleware('permission:library.manage');
        Route::get('/library/{book}', [LibraryController::class, 'show'])->name('library.show')->middleware('permission:library.manage');
        Route::get('/library/{book}/edit', [LibraryController::class, 'edit'])->name('library.edit')->middleware('permission:library.manage');
        Route::put('/library/{book}', [LibraryController::class, 'update'])->name('library.update')->middleware('permission:library.manage');
        Route::delete('/library/{book}', [LibraryController::class, 'destroy'])->name('library.destroy')->middleware('permission:library.manage');
        Route::post('/library/{book}/copies', [LibraryController::class, 'storeCopies'])->name('library.copies.store')->middleware('permission:library.manage');
        Route::put('/library-copies/{copy}', [LibraryController::class, 'updateCopy'])->name('library.copies.update')->middleware('permission:library.manage');
        Route::delete('/library-copies/{copy}', [LibraryController::class, 'destroyCopy'])->name('library.copies.destroy')->middleware('permission:library.manage');
        Route::post('/library-accession', [LibraryController::class, 'assignAccession'])->name('library.accession')->middleware('permission:library.manage');
        Route::get('/library-labels', [LibraryLabelController::class, 'index'])->name('library.labels')->middleware('permission:library.manage');
        Route::post('/library-labels/printed', [LibraryLabelController::class, 'printed'])->name('library.labels.printed')->middleware('permission:library.manage');
        Route::get('/library-numbering/{kind}', [NumberingController::class, 'show'])->whereIn('kind', ['library-barcode', 'library-accession'])->name('library.numbering')->middleware('permission:library.manage');
        Route::put('/library-numbering/{kind}', [NumberingController::class, 'save'])->whereIn('kind', ['library-barcode', 'library-accession'])->name('library.numbering.update')->middleware('permission:library.manage');
        Route::get('/library-loans', [LibraryController::class, 'circulation'])->name('library.loans')->middleware('permission:library.manage');
        Route::post('/library-loans/borrow', [LibraryController::class, 'borrow'])->name('library.borrow')->middleware('permission:library.manage');
        Route::post('/library-loans/return', [LibraryController::class, 'returnByCode'])->name('library.return.code')->middleware('permission:library.manage');
        Route::post('/library-loans/{loan}/return', [LibraryController::class, 'return'])->name('library.return')->middleware('permission:library.manage');
        Route::post('/library-loans/remind', [LibraryController::class, 'remindOverdue'])->name('library.remind')->middleware('permission:library.manage');

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
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index')->middleware('permission:reports.view');
        Route::get('/report-cards', [ReportCardController::class, 'classroom'])->name('report-cards.classroom');
        Route::get('/reports/dmc', [ReportController::class, 'dmc'])->name('reports.dmc')->middleware('permission:reports.view');
    });

    /* ---------------- งานที่มอบหมายตามตำแหน่ง (ผู้ดูแลระบบได้ทุกสิทธิ์) ---------------- */
    Route::middleware('role:admin,teacher')->group(function () {
        Route::resource('users', UserController::class)->except('show')->middleware('permission:users.manage');
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password')->middleware('permission:users.manage');

        Route::get('/classrooms', [ClassroomController::class, 'index'])->name('classrooms.index')->middleware('permission:academics.manage');
        Route::post('/classrooms', [ClassroomController::class, 'store'])->name('classrooms.store')->middleware('permission:academics.manage');
        Route::put('/classrooms/{classroom}', [ClassroomController::class, 'update'])->name('classrooms.update')->middleware('permission:academics.manage');
        Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy'])->name('classrooms.destroy')->middleware('permission:academics.manage');
        Route::get('/classrooms/promote', [ClassroomController::class, 'promoteForm'])->name('classrooms.promote.form')->middleware('permission:academics.manage');
        Route::post('/classrooms/promote', [ClassroomController::class, 'promote'])->name('classrooms.promote')->middleware('permission:academics.manage');
        Route::post('/classrooms/promote/undo', [ClassroomController::class, 'undoPromote'])->name('classrooms.promote.undo')->middleware('permission:academics.manage');

        Route::resource('subjects', SubjectController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('permission:academics.manage');

        Route::get('/audit', [AuditController::class, 'index'])->name('audit.index')->middleware('permission:audit.view');
        Route::get('/backups', [BackupController::class, 'index'])->name('backups.index')->middleware('permission:settings.manage');
        Route::post('/backups', [BackupController::class, 'run'])->name('backups.run')->middleware('permission:settings.manage');
        Route::get('/backups/{name}', [BackupController::class, 'download'])->name('backups.download')->where('name', '[A-Za-z0-9._-]+')->middleware('permission:settings.manage');

        // ปพ.7 ใบรับรองผลการศึกษา + ทะเบียนคุม
        Route::get('/certificates', [CertificateController::class, 'index'])->name('certificates.index')->middleware('permission:academics.manage');
        Route::get('/certificates/{issue}', [CertificateController::class, 'show'])->name('certificates.show')->middleware('permission:academics.manage');
        Route::get('/students/{student}/certificate', [CertificateController::class, 'create'])->name('certificates.create')->middleware('permission:academics.manage');
        Route::post('/students/{student}/certificate', [CertificateController::class, 'store'])->name('certificates.store')->middleware('permission:academics.manage');
        Route::post('/students/{student}/transcript-issue', [TranscriptController::class, 'issue'])->name('transcript.issue')->middleware('permission:academics.manage');

        // ปพ.3 รายงานผู้สำเร็จการศึกษา
        Route::get('/graduates', [GraduateController::class, 'index'])->name('graduates.index')->middleware('permission:academics.manage');
        Route::post('/graduates/approve', [GraduateController::class, 'approve'])->name('graduates.approve')->middleware('permission:academics.manage');

        Route::post('/courses', [CourseController::class, 'store'])->name('courses.store')->middleware('permission:academics.manage');
        Route::post('/courses/bulk', [CourseController::class, 'bulk'])->name('courses.bulk')->middleware('permission:academics.manage');
        Route::put('/courses/{course}', [CourseController::class, 'update'])->name('courses.update')->middleware('permission:academics.manage');
        Route::delete('/courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy')->middleware('permission:academics.manage');
        Route::middleware('permission:academics.manage')->group(function () {
            Route::get('/courses/{course}/members', [CourseMemberController::class, 'edit'])->name('courses.members');
            Route::put('/courses/{course}/members', [CourseMemberController::class, 'update'])->name('courses.members.update');
            Route::get('/course-approvals', [CourseApprovalController::class, 'index'])->name('courses.approvals');
            Route::post('/courses/{course}/approve', [CourseApprovalController::class, 'approve'])->name('courses.approve');
            Route::post('/courses/{course}/return', [CourseApprovalController::class, 'return'])->name('courses.return');
        });

        Route::post('/timetable', [TimetableController::class, 'save'])->name('timetable.save')->middleware('permission:academics.manage');
        Route::get('/substitutions', [SubstitutionController::class, 'index'])->name('substitutions.index')->middleware('permission:academics.manage');
        Route::post('/substitutions', [SubstitutionController::class, 'store'])->name('substitutions.store')->middleware('permission:academics.manage');

        Route::get('/terms', [TermController::class, 'index'])->name('terms.index')->middleware('permission:academics.manage');
        Route::post('/terms', [TermController::class, 'store'])->name('terms.store')->middleware('permission:academics.manage');
        Route::put('/terms/{term}', [TermController::class, 'update'])->name('terms.update')->middleware('permission:academics.manage');
        Route::post('/terms/{term}/current', [TermController::class, 'makeCurrent'])->name('terms.current')->middleware('permission:academics.manage');

        Route::get('/settings', [SettingController::class, 'edit'])->name('settings')->middleware('permission:settings.manage');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update')->middleware('permission:settings.manage');
        Route::post('/settings/rules', [SettingController::class, 'storeRule'])->name('settings.rules.store')->middleware('permission:settings.manage');
        Route::put('/settings/rules/{rule}', [SettingController::class, 'updateRule'])->name('settings.rules.update')->middleware('permission:settings.manage');
        Route::delete('/settings/rules/{rule}', [SettingController::class, 'destroyRule'])->name('settings.rules.destroy')->middleware('permission:settings.manage');

        Route::get('/staff-attendance', [StaffAttendanceController::class, 'report'])->name('staff-attendance.report')->middleware('permission:staff.manage');

        Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create')->middleware('permission:finance.manage');
        Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store')->middleware('permission:finance.manage');
        Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'pay'])->name('invoices.pay')->middleware('permission:finance.manage');
        Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void')->middleware('permission:finance.manage');
        Route::post('/invoices/{invoice}/discount', [InvoiceController::class, 'discount'])->name('invoices.discount')->middleware('permission:finance.manage');
        Route::post('/payments/{payment}/void', [InvoiceController::class, 'voidPayment'])->name('payments.void')->middleware('permission:finance.manage');
        Route::get('/finance/reports', [FinanceController::class, 'reports'])->name('finance.reports')->middleware('permission:finance.view');
        Route::middleware('permission:finance.manage')->group(function () {
            Route::post('/invoices/{invoice}/installments', [FinanceController::class, 'installments'])->name('invoices.installments');

            Route::get('/fees', [FeePlanController::class, 'index'])->name('fees.index');
            Route::post('/fees/items', [FeePlanController::class, 'storeItem'])->name('fees.items.store');
            Route::put('/fees/items/{item}', [FeePlanController::class, 'updateItem'])->name('fees.items.update');
            Route::post('/fees/plans', [FeePlanController::class, 'storePlan'])->name('fees.plans.store');
            Route::delete('/fees/plans/{plan}', [FeePlanController::class, 'destroyPlan'])->name('fees.plans.destroy');
            Route::post('/fees/plans/{plan}/issue', [FeePlanController::class, 'issue'])->name('fees.plans.issue');
            Route::post('/fees/discounts', [FeePlanController::class, 'storeDiscount'])->name('fees.discounts.store');
            Route::delete('/fees/discounts/{discount}', [FeePlanController::class, 'destroyDiscount'])->name('fees.discounts.destroy');

            Route::get('/finance/closing', [FinanceController::class, 'closing'])->name('finance.closing');
            Route::post('/finance/closing', [FinanceController::class, 'close'])->name('finance.close');
            Route::delete('/finance/closing/{closing}', [FinanceController::class, 'reopen'])->name('finance.reopen');
        });

        Route::get('/slips', [SlipController::class, 'index'])->name('slips.index')->middleware('permission:finance.manage');
        Route::post('/slips/{slip}/approve', [SlipController::class, 'approve'])->name('slips.approve')->middleware('permission:finance.manage');
        Route::post('/slips/{slip}/reject', [SlipController::class, 'reject'])->name('slips.reject')->middleware('permission:finance.manage');

        Route::post('/staff-leaves/{leave}/approve', [StaffLeaveController::class, 'approve'])->name('staff-leaves.approve')->middleware('permission:staff.manage');
        Route::post('/staff-leaves/{leave}/reject', [StaffLeaveController::class, 'reject'])->name('staff-leaves.reject')->middleware('permission:staff.manage');

        // สอบคัดเลือก: ห้องสอบ/เลขประจำตัวสอบ · วิชาสอบ (ตรวจด้วยระบบตรวจข้อสอบ) · จัดอันดับ · ประกาศผล
        Route::get('/admission-exams', [AdmissionExamController::class, 'index'])->name('admission-exams.index')->middleware('permission:admissions.manage');
        Route::post('/admission-exams', [AdmissionExamController::class, 'store'])->name('admission-exams.store')->middleware('permission:admissions.manage');
        Route::get('/admission-exams/{round}', [AdmissionExamController::class, 'show'])->name('admission-exams.show')->middleware('permission:admissions.manage');
        Route::put('/admission-exams/{round}', [AdmissionExamController::class, 'update'])->name('admission-exams.update')->middleware('permission:admissions.manage');
        Route::post('/admission-exams/{round}/seats', [AdmissionExamController::class, 'seats'])->name('admission-exams.seats')->middleware('permission:admissions.manage');
        Route::post('/admission-exams/{round}/subjects', [AdmissionExamController::class, 'addSubject'])->name('admission-exams.subjects')->middleware('permission:admissions.manage');
        Route::post('/admission-exams/{round}/publish', [AdmissionExamController::class, 'publish'])->name('admission-exams.publish')->middleware('permission:admissions.manage');
        Route::delete('/admission-exams/{round}/publish', [AdmissionExamController::class, 'unpublish'])->name('admission-exams.unpublish')->middleware('permission:admissions.manage');
        Route::get('/admission-exams/{round}/print/{doc}', [AdmissionExamController::class, 'print'])->name('admission-exams.print')->middleware('permission:admissions.manage');
        Route::get('/admission-exams/{round}/export', [AdmissionExamController::class, 'export'])->name('admission-exams.export')->middleware('permission:admissions.manage');

        Route::get('/admissions', [AdmissionController::class, 'index'])->name('admissions.index')->middleware('permission:admissions.manage');
        Route::get('/admissions/form', [AdmissionFormController::class, 'edit'])->name('admissions.form')->middleware('permission:admissions.manage');
        Route::put('/admissions/form', [AdmissionFormController::class, 'update'])->name('admissions.form.update')->middleware('permission:admissions.manage');
        Route::get('/admissions/export', [AdmissionController::class, 'export'])->name('admissions.export')->middleware('permission:admissions.manage');
        Route::get('/admissions/{admission}', [AdmissionController::class, 'show'])->name('admissions.show')->middleware('permission:admissions.manage');
        Route::get('/admissions/{admission}/document', [AdmissionController::class, 'document'])->name('admissions.document')->middleware('permission:admissions.manage');
        Route::get('/admissions/{admission}/files/{question}', [AdmissionController::class, 'answerFile'])->name('admissions.file')->middleware('permission:admissions.manage');
        Route::put('/admissions/{admission}', [AdmissionController::class, 'update'])->name('admissions.update')->middleware('permission:admissions.manage');
        Route::put('/admissions/{admission}/exam', [AdmissionController::class, 'exam'])->name('admissions.exam')->middleware('permission:admissions.manage');
        Route::post('/admissions/{admission}/fee', [AdmissionController::class, 'fee'])->name('admissions.fee')->middleware('permission:admissions.manage');
        Route::get('/admissions/{admission}/print/{doc}', [AdmissionController::class, 'print'])->name('admissions.print')->middleware('permission:admissions.manage');
        Route::post('/admissions/{admission}/enroll', [AdmissionController::class, 'enroll'])->name('admissions.enroll')->middleware('permission:admissions.manage');

        Route::post('/calendar', [CalendarController::class, 'store'])->name('calendar.store')->middleware('permission:academics.manage');
        Route::put('/calendar/{event}', [CalendarController::class, 'update'])->name('calendar.update')->middleware('permission:academics.manage');
        Route::delete('/calendar/{event}', [CalendarController::class, 'destroy'])->name('calendar.destroy')->middleware('permission:academics.manage');

        Route::get('/surveys/create', [SurveyController::class, 'create'])->name('surveys.create')->middleware('permission:academics.manage');
        Route::post('/surveys', [SurveyController::class, 'store'])->name('surveys.store')->middleware('permission:academics.manage');
        Route::get('/surveys/{survey}/edit', [SurveyController::class, 'edit'])->name('surveys.edit')->middleware('permission:academics.manage');
        Route::put('/surveys/{survey}', [SurveyController::class, 'update'])->name('surveys.update')->middleware('permission:academics.manage');

        Route::get('/settings/messages', [LineController::class, 'logs'])->name('settings.messages')->middleware('permission:settings.manage');
        Route::post('/settings/line-test', [LineController::class, 'test'])->name('settings.line-test')->middleware('permission:settings.manage');

        Route::get('/executive', [ExecutiveController::class, 'index'])->name('executive.index')->middleware('permission:executive.view');
        Route::get('/students/{student}/data-export', [PrivacyController::class, 'export'])->name('students.data-export')->middleware('permission:users.manage');

        Route::middleware('permission:users.manage')->group(function () {
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
            Route::put('/roles', [RoleController::class, 'update'])->name('roles.update');
            Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        });
    });
});
