<?php

namespace App\Support;

use App\Models\Admission;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Attendance;
use App\Models\BehaviorRecord;
use App\Models\ConsentForm;
use App\Models\ConsentResponse;
use App\Models\HealthVisit;
use App\Models\Invoice;
use App\Models\LeaveRequest;
use App\Models\PaymentSlip;
use App\Models\PeriodAttendance;
use App\Models\SchoolEvent;
use App\Models\StaffLeave;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * รายการแจ้งเตือนคำนวณจากข้อมูลจริง (ไม่ต้องมีตารางแยก)
 * "ยังไม่อ่าน" = เกิดหลังเวลาที่ผู้ใช้เปิดหน้าแจ้งเตือนครั้งล่าสุด
 */
class Notifications
{
    /** @return Collection<int, array{icon:string,color:string,title:string,sub:string,url:string,at:Carbon,unread:bool}> */
    public static function for(User $user): Collection
    {
        $items = match (true) {
            $user->isParent() => self::parent($user),
            $user->isStudent() => self::student($user),
            default => self::staff($user),
        };
        $seen = $user->notifications_seen_at;

        return $items
            ->map(fn ($i) => $i + ['unread' => ! $seen || $i['at']->gt($seen)])
            ->sortByDesc('at')->take(40)->values();
    }

    public static function unreadCount(User $user): int
    {
        return self::for($user)->where('unread', true)->count();
    }

    private static function staff(User $user): Collection
    {
        $items = collect();

        $leaves = LeaveRequest::with('student.classroom')->where('status', 'pending');
        if (! $user->isAdmin()) {
            $leaves->whereHas('student', fn ($q) => $q->whereIn('classroom_id', $user->myClassrooms()->pluck('id')));
        }
        foreach ($leaves->latest()->limit(20)->get() as $l) {
            $items->push([
                'icon' => 'bi-envelope-paper', 'color' => 'warning',
                'title' => "{$l->student->fullName()} ส่งใบ{$l->typeLabel()}",
                'sub' => ($l->student->classroom?->name() ?? '').' · '.thai_date($l->start_date).($l->days() > 1 ? " ({$l->days()} วัน)" : '').' · รออนุมัติ',
                'url' => route('leaves.index'), 'at' => $l->created_at,
            ]);
        }

        $items = $items->concat(self::announcements($user));

        // แต่ละเรื่องแจ้งเฉพาะผู้ที่มีสิทธิ์จัดการเรื่องนั้น (ผู้ดูแลระบบมีทุกสิทธิ์)
        if ($user->hasPermission('finance.manage')) {
            foreach (PaymentSlip::with('invoice.student')->where('status', 'pending')->latest()->limit(10)->get() as $s) {
                $items->push(['icon' => 'bi-receipt-cutoff', 'color' => 'teal', 'title' => 'สลิปรอตรวจ '.baht($s->amount).' บาท',
                    'sub' => $s->invoice->student->fullName().' · '.$s->invoice->title, 'url' => route('slips.index'), 'at' => $s->created_at]);
            }
        }
        if ($user->hasPermission('staff.manage')) {
            foreach (StaffLeave::with('user')->where('status', 'pending')->latest()->limit(10)->get() as $l) {
                $items->push(['icon' => 'bi-briefcase', 'color' => 'warning', 'title' => "{$l->user->name} ยื่น{$l->typeLabel()} {$l->days()} วัน",
                    'sub' => thai_date($l->start_date).' · รออนุมัติ', 'url' => route('staff-leaves.index'), 'at' => $l->created_at]);
            }
        }
        if ($user->hasPermission('admissions.manage')) {
            $newApps = Admission::where('status', 'submitted')->count();
            if ($newApps) {
                $items->push(['icon' => 'bi-person-plus', 'color' => 'info', 'title' => "ใบสมัครใหม่ {$newApps} ใบ", 'sub' => 'รับสมัครนักเรียน',
                    'url' => route('admissions.index', ['status' => 'submitted']), 'at' => Admission::where('status', 'submitted')->max('created_at') ? Carbon::parse(Admission::where('status', 'submitted')->max('created_at')) : now()]);
            }
        }
        foreach (StaffLeave::where('user_id', $user->id)->whereIn('status', ['approved', 'rejected'])->where('reviewed_at', '>=', now()->subDays(14))->get() as $l) {
            $items->push(['icon' => $l->status === 'approved' ? 'bi-check-circle' : 'bi-x-circle', 'color' => $l->status === 'approved' ? 'success' : 'danger',
                'title' => "{$l->typeLabel()} ของคุณ{$l->statusLabel()}", 'sub' => thai_date($l->start_date), 'url' => route('staff-leaves.index'), 'at' => $l->reviewed_at]);
        }
        $items = $items->concat(self::eventsTomorrow($user));

        if (today()->isWeekday() && now()->hour >= 8) {
            foreach ($user->myClassrooms() as $c) {
                $checked = Attendance::where('classroom_id', $c->id)->where('date', today()->toDateString())->exists();
                if (! $checked && $c->students()->exists()) {
                    $items->push([
                        'icon' => 'bi-alarm', 'color' => 'danger',
                        'title' => "ยังไม่ได้เช็คชื่อห้อง {$c->name()}",
                        'sub' => 'ผู้ปกครองจะเห็นสถานะหลังครูเช็คชื่อ',
                        'url' => route('attendance.index', ['classroom' => $c->id]), 'at' => today()->setTime(8, 0),
                    ]);
                }
            }
        }

        return $items;
    }

    private static function parent(User $user): Collection
    {
        $children = $user->children()->get();
        $ids = $children->pluck('id');
        $nick = fn ($s) => 'น้อง'.($s->nickname ?: $s->first_name);
        $items = self::announcements($user);

        foreach (Attendance::with('student')->whereIn('student_id', $ids)->whereIn('status', ['absent', 'late'])
            ->where('date', '>=', today()->subDays(7)->toDateString())->get() as $a) {
            $items->push([
                'icon' => $a->status === 'absent' ? 'bi-person-x' : 'bi-clock-history',
                'color' => $a->status === 'absent' ? 'danger' : 'warning',
                'title' => $nick($a->student).' '.($a->status === 'absent' ? 'ขาดเรียน' : 'มาสาย'),
                'sub' => Thai::fullDate($a->date).($a->checked_at ? ' เวลา '.substr($a->checked_at, 0, 5).' น.' : ''),
                'url' => route('parent.child', $a->student), 'at' => $a->date->copy()->setTimeFromTimeString($a->checked_at ?: '08:00:00'),
            ]);
        }

        // ขาดเรียนรายวิชา (เช็คชื่อรายคาบ) 7 วันล่าสุด — เฉพาะวันที่มาโรงเรียนแต่ไม่เข้าคาบ
        // (ขาด/ลาทั้งวันแจ้งไปแล้วด้านบน ไม่ต้องแจ้งซ้ำทุกคาบ)
        $wholeDayOff = Attendance::whereIn('student_id', $ids)->whereIn('status', ['absent', 'leave', 'sick'])
            ->where('date', '>=', today()->subDays(7)->toDateString())->get()
            ->mapWithKeys(fn ($a) => [$a->student_id.'-'.$a->date->toDateString() => true]);
        foreach (PeriodAttendance::with(['student', 'course.subject'])->whereIn('student_id', $ids)->where('status', 'absent')
            ->where('date', '>=', today()->subDays(7)->toDateString())->get()
            ->reject(fn ($p) => isset($wholeDayOff[$p->student_id.'-'.$p->date->toDateString()])) as $p) {
            $items->push(['icon' => 'bi-door-open', 'color' => 'danger',
                'title' => $nick($p->student).' ขาดเรียนวิชา'.$p->course->subject->name.' คาบที่ '.$p->period,
                'sub' => Thai::fullDate($p->date), 'url' => route('parent.child', ['student' => $p->student, 'tab' => 'grades']),
                'at' => $p->created_at]);
        }

        foreach (LeaveRequest::with('student')->whereIn('student_id', $ids)->whereIn('status', ['approved', 'rejected'])
            ->where('reviewed_at', '>=', now()->subDays(14))->get() as $l) {
            $items->push([
                'icon' => $l->status === 'approved' ? 'bi-check-circle' : 'bi-x-circle',
                'color' => $l->status === 'approved' ? 'success' : 'danger',
                'title' => "ใบลาของ{$nick($l->student)} {$l->statusLabel()}",
                'sub' => $l->typeLabel().' '.thai_date($l->start_date).($l->review_note ? ' · '.$l->review_note : ''),
                'url' => route('parent.home'), 'at' => $l->reviewed_at,
            ]);
        }

        foreach (BehaviorRecord::with('student')->whereIn('student_id', $ids)->where('created_at', '>=', now()->subDays(14))->get() as $b) {
            $items->push([
                'icon' => $b->points > 0 ? 'bi-star' : 'bi-exclamation-diamond',
                'color' => $b->points > 0 ? 'success' : 'danger',
                'title' => $nick($b->student).($b->points > 0 ? ' ได้รับคำชม' : ' ถูกหักคะแนนความประพฤติ').' ('.($b->points > 0 ? '+' : '').$b->points.')',
                'sub' => $b->title,
                'url' => route('parent.child', ['student' => $b->student, 'tab' => 'behavior']), 'at' => $b->created_at,
            ]);
        }

        // การบ้านที่ยังไม่ส่ง (สั่งใน 7 วัน)
        foreach ($children as $child) {
            $pending = Assignment::with('course.subject')
                ->whereHas('course', fn ($q) => $q->forStudent($child, $child->classroom_id))
                ->where('created_at', '>=', now()->subDays(7))
                ->whereDoesntHave('submissions', fn ($q) => $q->where('student_id', $child->id)->whereNotNull('submitted_at'))->get();
            foreach ($pending as $a) {
                $items->push(['icon' => 'bi-journal-text', 'color' => $a->isClosed() ? 'danger' : 'primary',
                    'title' => 'การบ้าน'.$a->course->subject->name.': '.$a->title, 'sub' => $nick($child).($a->due_at ? ' · ส่งภายใน '.thai_datetime($a->due_at) : ''),
                    'url' => route('parent.homework'), 'at' => $a->created_at]);
            }
        }

        foreach (HealthVisit::with('student')->whereIn('student_id', $ids)->where('visited_at', '>=', now()->subDays(7))->get() as $v) {
            $items->push(['icon' => 'bi-heart-pulse', 'color' => in_array($v->action, ['sent_home', 'hospital'], true) ? 'danger' : 'info',
                'title' => $nick($v->student).' มาห้องพยาบาล: '.$v->symptom, 'sub' => $v->actionLabel().($v->treatment ? ' · '.$v->treatment : ''),
                'url' => route('parent.child', ['student' => $v->student, 'tab' => 'health']), 'at' => $v->visited_at]);
        }

        foreach (PaymentSlip::with('invoice.student')->whereHas('invoice', fn ($q) => $q->whereIn('student_id', $ids))
            ->whereIn('status', ['approved', 'rejected'])->where('reviewed_at', '>=', now()->subDays(14))->get() as $s) {
            $items->push(['icon' => $s->status === 'approved' ? 'bi-check-circle' : 'bi-x-circle', 'color' => $s->status === 'approved' ? 'success' : 'danger',
                'title' => 'สลิป '.baht($s->amount).' บาท '.$s->statusLabel(), 'sub' => $s->invoice->title.($s->review_note ? ' · '.$s->review_note : ''),
                'url' => route('invoices.show', $s->invoice), 'at' => $s->reviewed_at]);
        }
        $items = $items->concat(self::eventsTomorrow($user))->concat(self::consents($children, 'parent.consents', true));

        foreach (Invoice::with('student')->whereIn('student_id', $ids)->whereIn('status', ['unpaid', 'partial'])->get() as $inv) {
            $items->push([
                'icon' => 'bi-receipt', 'color' => $inv->isOverdue() ? 'danger' : 'primary',
                'title' => ($inv->isOverdue() ? 'เลยกำหนดชำระ: ' : 'ใบแจ้งหนี้: ').$inv->title,
                'sub' => $nick($inv->student).' · ค้าง '.baht($inv->balance()).' บาท'.($inv->due_date ? ' · กำหนด '.thai_date($inv->due_date) : ''),
                'url' => route('invoices.show', $inv), 'at' => $inv->created_at,
            ]);
        }

        return $items;
    }

    /** นักเรียน: ประกาศ การบ้านค้าง งานที่ครูตรวจแล้ว ความประพฤติ กิจกรรมพรุ่งนี้ */
    private static function student(User $user): Collection
    {
        $me = $user->studentProfile;
        $items = self::announcements($user)->concat(self::eventsTomorrow($user));
        if (! $me) {
            return $items;
        }

        $pending = Assignment::with('course.subject')
            ->whereHas('course', fn ($q) => $q->forStudent($me, $me->classroom_id))
            ->where('created_at', '>=', now()->subDays(14))
            ->whereDoesntHave('submissions', fn ($q) => $q->where('student_id', $me->id)->whereNotNull('submitted_at'))->get();
        foreach ($pending as $a) {
            $items->push(['icon' => 'bi-journal-text', 'color' => $a->isClosed() ? 'danger' : 'primary',
                'title' => 'การบ้าน'.$a->course->subject->name.': '.$a->title,
                'sub' => $a->due_at ? ($a->isClosed() ? 'เลยกำหนดแล้ว ' : 'ส่งภายใน ').thai_datetime($a->due_at) : 'ยังไม่ได้ส่ง',
                'url' => route('student.homework'), 'at' => $a->created_at]);
        }

        foreach (Submission::with('assignment')->where('student_id', $me->id)->whereNotNull('graded_at')->where('graded_at', '>=', now()->subDays(14))->get() as $s) {
            $items->push(['icon' => 'bi-check2-circle', 'color' => 'success',
                'title' => 'ครูตรวจงาน "'.$s->assignment->title.'" แล้ว', 'sub' => 'ได้ '.rtrim(rtrim(number_format($s->score, 2), '0'), '.').($s->assignment->max_score ? '/'.rtrim(rtrim(number_format($s->assignment->max_score, 2), '0'), '.') : '').($s->feedback ? ' · '.$s->feedback : ''),
                'url' => route('student.homework'), 'at' => $s->graded_at]);
        }

        $items = $items->concat(self::consents(collect([$me]), 'student.consents', false));

        foreach (BehaviorRecord::where('student_id', $me->id)->where('created_at', '>=', now()->subDays(14))->get() as $b) {
            $items->push(['icon' => $b->points > 0 ? 'bi-star' : 'bi-exclamation-diamond', 'color' => $b->points > 0 ? 'success' : 'danger',
                'title' => ($b->points > 0 ? 'ได้รับคำชม ' : 'ถูกหักคะแนนความประพฤติ ').'('.($b->points > 0 ? '+' : '').$b->points.')', 'sub' => $b->title,
                'url' => route('student.info', ['tab' => 'behavior']), 'at' => $b->created_at]);
        }

        return $items;
    }

    /** หนังสือขออนุญาตที่ยังเปิดรับคำตอบและยังไม่มีคำตอบของนักเรียนคนนั้น */
    private static function consents(Collection $students, string $route, bool $named): Collection
    {
        $forms = ConsentForm::latest()->limit(30)->get()->filter(fn ($f) => $f->acceptsResponses());
        $answered = ConsentResponse::whereIn('consent_form_id', $forms->pluck('id'))->whereIn('student_id', $students->pluck('id'))->get()
            ->mapWithKeys(fn ($r) => [$r->consent_form_id.'-'.$r->student_id => true]);
        $items = collect();
        foreach ($forms as $f) {
            foreach ($students->filter(fn ($s) => $f->includes($s) && ! isset($answered[$f->id.'-'.$s->id])) as $s) {
                $items->push(['icon' => 'bi-envelope-check', 'color' => 'warning', 'title' => 'หนังสือขออนุญาต: '.$f->title,
                    'sub' => ($named ? 'น้อง'.($s->nickname ?: $s->first_name).' · ' : '').($named ? 'ยังไม่ได้ตอบ' : 'รอผู้ปกครองตอบ').($f->due_date ? ' · ภายใน '.thai_date($f->due_date) : ''),
                    'url' => route($route), 'at' => $f->created_at]);
            }
        }

        return $items;
    }

    /** กิจกรรมในปฏิทินที่เริ่มพรุ่งนี้ (แจ้งล่วงหน้า 1 วัน) */
    private static function eventsTomorrow(User $user): Collection
    {
        $tomorrow = today()->addDay();

        return SchoolEvent::visibleTo($user)->where('start_date', $tomorrow->toDateString())->get()
            ->map(fn ($e) => ['icon' => $e->typeIcon(), 'color' => $e->typeColor(), 'title' => 'พรุ่งนี้: '.$e->title,
                'sub' => $e->typeLabel().($e->description ? ' · '.$e->description : ''), 'url' => route('calendar'), 'at' => today()->startOfDay()]);
    }

    private static function announcements(User $user): Collection
    {
        $read = $user->belongsToMany(Announcement::class, 'announcement_reads')->pluck('announcements.id')->all();

        return Announcement::visibleTo($user)->where('created_at', '>=', now()->subDays(30))->latest()->limit(15)->get()
            ->reject(fn ($a) => in_array($a->id, $read))
            ->map(fn ($a) => [
                'icon' => 'bi-megaphone', 'color' => 'primary',
                'title' => $a->title, 'sub' => 'ประกาศ · '.$a->audienceLabel(),
                'url' => route('announcements.show', $a), 'at' => $a->created_at,
            ])->values();
    }
}
