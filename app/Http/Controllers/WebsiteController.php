<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Department;
use App\Models\SchoolEvent;
use App\Models\Student;
use App\Models\User;
use App\Models\WebLink;
use App\Models\WebMessage;
use App\Models\WebPage;
use App\Models\WebPost;
use App\Models\WebSlide;
use App\Services\Notifier;
use App\Support\AdmissionForm;
use App\Support\Website;
use Illuminate\Http\Request;

/** เว็บไซต์โรงเรียน (หน้าบ้าน) เปิดให้ทุกคนดูได้เมื่อผู้ดูแลเปิดใช้งาน */
class WebsiteController extends Controller
{
    protected function guard(Request $request): void
    {
        abort_unless(Website::visibleTo($request->user()), 404);
    }

    public function home(Request $request)
    {
        $this->guard($request);
        $latest = fn (string $cat, int $n) => WebPost::published()->where('category', $cat)->latestFirst()->limit($n)->get();

        return view('website.home', [
            'slides' => WebSlide::where('is_active', true)->orderBy('sort')->orderBy('id')->get(),
            'news' => $latest('news', 6),
            'activities' => $latest('activity', 4),
            'procurements' => $latest('procurement', 5),
            'admissions' => $latest('admission', 3),
            'events' => SchoolEvent::where('audience', 'all')->whereDate('end_date', '>=', today())->orderBy('start_date')->limit(5)->get(),
            // หน้าเนื้อหาที่มีรูปประกอบ ขึ้นเป็นการ์ดข้างวิดีโอแนะนำ (เช่น หลักสูตร)
            'featurePages' => WebPage::where('is_published', true)->whereNotNull('cover')->orderBy('sort')->orderBy('id')->limit(2)->get(),
            'ita' => \App\Models\ItaYear::where('is_published', true)->orderByDesc('year')->first(),
            'agencies' => WebLink::where('kind', 'agency')->orderBy('sort')->orderBy('id')->get(),
            'services' => WebLink::where('kind', 'service')->orderBy('sort')->orderBy('id')->get(),
            'admissionOpen' => rescue(fn () => AdmissionForm::isOpen(), false, false),
            'stats' => [
                'students' => Student::where('status', 'active')->count(),
                'teachers' => User::where('role', 'teacher')->where('is_active', true)->count(),
                'classrooms' => Classroom::count(),
            ],
        ]);
    }

    public function news(Request $request, ?string $category = null)
    {
        $this->guard($request);
        abort_if($category !== null && ! isset(WebPost::CATEGORIES[$category]), 404);
        $q = trim((string) $request->query('q', ''));

        return view('website.news', [
            'category' => $category,
            'q' => $q,
            'posts' => WebPost::published()
                ->when($category, fn ($w) => $w->where('category', $category))
                ->when($q !== '', fn ($w) => $w->where(fn ($s) => $s->where('title', 'like', "%{$q}%")->orWhere('body', 'like', "%{$q}%")))
                ->latestFirst()->paginate(12)->withQueryString(),
        ]);
    }

    public function post(Request $request, WebPost $post)
    {
        $this->guard($request);
        abort_unless($post->isPublished() || Website::canManage($request->user()), 404);
        if ($post->isPublished()) {
            WebPost::whereKey($post->id)->increment('views');
        }

        return view('website.post', [
            'post' => $post,
            'related' => WebPost::published()->where('category', $post->category)->whereKeyNot($post->id)->latestFirst()->limit(4)->get(),
        ]);
    }

    public function page(Request $request, WebPage $page)
    {
        $this->guard($request);
        abort_unless($page->is_published || Website::canManage($request->user()), 404);

        return view('website.page', ['page' => $page]);
    }

    /** ทำเนียบบุคลากร: เฉพาะคนที่เลือกให้แสดงบนเว็บ จัดกลุ่มตามฝ่ายหลัก */
    public function personnel(Request $request)
    {
        $this->guard($request);
        $people = User::where('public_profile', true)->where('is_active', true)->whereIn('role', ['admin', 'teacher'])
            ->with(['departments' => fn ($q) => $q->wherePivot('is_primary', true)])->orderBy('name')->get(['id', 'name', 'position', 'avatar', 'role']);
        $groups = $people->groupBy(fn ($u) => $u->departments->first()?->id ?? 0);
        $departments = Department::whereIn('id', $groups->keys()->filter())->orderBy('sort')->orderBy('id')->get();

        return view('website.personnel', ['groups' => $groups, 'departments' => $departments]);
    }

    /** ITA / OIT: ผู้ชมทั่วไปเห็นเฉพาะปีที่เผยแพร่ ผู้ดูแลเว็บเห็นฉบับร่างด้วย */
    public function ita(Request $request, ?int $year = null)
    {
        $this->guard($request);
        $years = \App\Models\ItaYear::when(! Website::canManage($request->user()), fn ($q) => $q->where('is_published', true))
            ->orderByDesc('year')->get();
        $current = $year ? $years->firstWhere('year', $year) : $years->first();
        abort_if($year && ! $current, 404);

        return view('website.ita', ['years' => $years, 'year' => $current?->load('items.evidences.post')]);
    }

    public function contact(Request $request)
    {
        $this->guard($request);

        return view('website.contact', ['started' => encrypt(time())]);
    }

    public function sendContact(Request $request)
    {
        abort_unless(Website::enabled(), 404);
        // กันสแปม: ช่องลับต้องว่าง และต้องใช้เวลากรอกอย่างน้อย 3 วินาที
        $started = rescue(fn () => (int) decrypt((string) $request->input('started')), 0, false);
        if (filled($request->input('website')) || time() - $started < 3) {
            return redirect()->route('web.contact')->with('success', 'ส่งข้อความแล้ว ขอบคุณที่ติดต่อโรงเรียน');
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'contact' => ['required', 'string', 'max:150'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:3000'],
        ], [], ['name' => 'ชื่อ', 'contact' => 'อีเมลหรือเบอร์โทร', 'subject' => 'เรื่อง', 'message' => 'ข้อความ']);

        $msg = WebMessage::create($data + ['ip' => $request->ip()]);
        $managers = User::where('is_active', true)->whereIn('role', ['admin', 'teacher'])->get()->filter(fn ($u) => Website::canManage($u));
        rescue(fn () => Notifier::users($managers, '✉️ ข้อความใหม่จากเว็บไซต์: '.$msg->name.' เรื่อง '.($msg->subject ?: \Illuminate\Support\Str::limit($msg->message, 40)), route('website.admin', ['tab' => 'messages'])), null, true);

        return redirect()->route('web.contact')->with('success', 'ส่งข้อความแล้ว ขอบคุณที่ติดต่อโรงเรียน เจ้าหน้าที่จะติดต่อกลับโดยเร็ว');
    }
}
