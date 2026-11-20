<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WebLink;
use App\Models\WebMessage;
use App\Models\WebPage;
use App\Models\WebPost;
use App\Models\WebSlide;
use App\Support\Audit;
use App\Support\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** จัดการเว็บไซต์โรงเรียน: ข่าว สไลด์ หน้าเนื้อหา ลิงก์ ทำเนียบบุคลากร ข้อความติดต่อ (เพิ่ม/แก้ผ่าน modal) */
class WebsiteAdminController extends Controller
{
    public const TABS = [
        'posts' => ['ข่าว', 'newspaper'],
        'slides' => ['สไลด์หน้าแรก', 'images'],
        'pages' => ['หน้าเนื้อหา', 'file-earmark-richtext'],
        'links' => ['ลิงก์หน่วยงาน/บริการ', 'link-45deg'],
        'ita' => ['ITA / OIT', 'patch-check'],
        'personnel' => ['ทำเนียบบุคลากร', 'people'],
        'messages' => ['ข้อความติดต่อ', 'envelope'],
    ];

    private const IMAGE = ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192'];

    public function index(Request $request)
    {
        $tab = isset(self::TABS[$request->query('tab')]) ? $request->query('tab') : 'posts';
        $data = ['tab' => $tab, 'unread' => WebMessage::whereNull('read_at')->count()];

        $data += match ($tab) {
            'posts' => ['posts' => WebPost::with('author:id,name')
                ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
                ->when(trim((string) $request->query('q')), fn ($q, $s) => $q->where('title', 'like', "%{$s}%"))
                ->orderByDesc('pinned')->orderByRaw('published_at is null desc')->orderByDesc('published_at')->orderByDesc('id')
                ->paginate(20)->withQueryString()],
            'slides' => ['slides' => WebSlide::orderBy('sort')->orderBy('id')->get()],
            'pages' => ['pages' => WebPage::orderBy('sort')->orderBy('id')->get()],
            'links' => ['links' => WebLink::orderBy('kind')->orderBy('sort')->orderBy('id')->get()],
            'personnel' => ['staff' => User::whereIn('role', ['admin', 'teacher'])->where('is_active', true)->orderBy('name')->get(['id', 'name', 'position', 'avatar', 'public_profile'])],
            'messages' => ['messages' => WebMessage::latest()->paginate(20)],
            'ita' => $this->itaData($request),
        };

        return view('website.admin.index', $data);
    }

    // ------------------------------------------------------------------ ข่าว

    public function savePost(Request $request, ?WebPost $post = null)
    {
        // route เพิ่มใหม่ไม่มี {post} Laravel จะส่ง model เปล่ามาให้ (exists = false)
        if (! $post?->exists) {
            $post = new WebPost(['author_id' => $request->user()->id]);
        }
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(WebPost::CATEGORIES))],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:50000'],
            'status' => ['required', Rule::in(['draft', 'now', 'schedule'])],
            'publish_at' => ['exclude_unless:status,schedule', 'required', 'date'],
            'pinned' => ['nullable', 'boolean'],
            'cover' => ['nullable', ...self::IMAGE],
            'images' => ['nullable', 'array', 'max:20'],
            'images.*' => self::IMAGE,
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip', 'max:20480'],
            'remove_images' => ['nullable', 'array'],
            'remove_files' => ['nullable', 'array'],
        ], ['images.*.max' => 'รูปแต่ละรูปต้องไม่เกิน 8 MB', 'files.*.max' => 'ไฟล์แนบแต่ละไฟล์ต้องไม่เกิน 20 MB'],
            ['category' => 'หมวด', 'title' => 'หัวข้อ', 'publish_at' => 'วันเวลาเผยแพร่', 'cover' => 'รูปปก']);

        $post->fill([
            'category' => $data['category'], 'title' => $data['title'], 'body' => $data['body'] ?? '', 'pinned' => $request->boolean('pinned'),
            'published_at' => match ($data['status']) {
                'draft' => null,
                'now' => $post->published_at && $post->published_at->isPast() ? $post->published_at : now(),
                'schedule' => \Illuminate\Support\Carbon::parse($data['publish_at']),
            },
        ]);

        if ($request->hasFile('cover')) {
            Website::deleteFile($post->cover);
            $post->cover = Website::storeImage($request->file('cover'), 'posts');
        }
        $images = array_values(array_diff($post->images ?? [], $data['remove_images'] ?? []));
        foreach (array_diff($post->images ?? [], $images) as $gone) {
            Website::deleteFile($gone);
        }
        foreach ($request->file('images', []) as $img) {
            $images[] = Website::storeImage($img, 'posts');
        }
        $post->images = $images;

        $files = array_values(array_filter($post->attachments ?? [], fn ($f) => ! in_array($f['path'], $data['remove_files'] ?? [], true)));
        foreach (array_diff(array_column($post->attachments ?? [], 'path'), array_column($files, 'path')) as $gone) {
            Website::deleteFile($gone);
        }
        foreach ($request->file('files', []) as $f) {
            $files[] = Website::storeFile($f);
        }
        $post->attachments = $files;

        $isNew = ! $post->exists;
        $post->save();
        Audit::log($isNew ? 'website.post_create' : 'website.post_update', $post, ($isNew ? 'เพิ่มข่าว ' : 'แก้ข่าว ').$post->title);

        return $this->back('posts')->with('success', $post->published_at === null ? 'บันทึกเป็นฉบับร่างแล้ว' : ($post->isPublished() ? 'เผยแพร่ข่าวแล้ว' : 'ตั้งเวลาเผยแพร่แล้ว'));
    }

    public function deletePost(WebPost $post)
    {
        Website::deleteFile($post->cover);
        foreach ($post->images ?? [] as $p) {
            Website::deleteFile($p);
        }
        foreach ($post->attachments ?? [] as $f) {
            Website::deleteFile($f['path'] ?? null);
        }
        Audit::log('website.post_delete', null, 'ลบข่าว '.$post->title);
        $post->delete();

        return $this->back('posts')->with('success', 'ลบข่าวแล้ว');
    }

    // ----------------------------------------------------------------- สไลด์

    public function saveSlide(Request $request, ?WebSlide $slide = null)
    {
        $slide = $slide?->exists ? $slide : new WebSlide;
        $data = $request->validate([
            'image' => [$slide->exists ? 'nullable' : 'required', ...self::IMAGE],
            'title' => ['nullable', 'string', 'max:150'],
            'caption' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'string', 'max:500'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ], ['image.required' => 'เลือกรูปสไลด์'], ['image' => 'รูป']);

        $slide->fill(['title' => $data['title'] ?? null, 'caption' => $data['caption'] ?? null, 'url' => $data['url'] ?? null,
            'sort' => (int) ($data['sort'] ?? 0), 'is_active' => $request->boolean('is_active')]);
        if ($request->hasFile('image')) {
            Website::deleteFile($slide->image);
            $slide->image = Website::storeImage($request->file('image'), 'slides');
        }
        $slide->save();

        return $this->back('slides')->with('success', 'บันทึกสไลด์แล้ว');
    }

    public function deleteSlide(WebSlide $slide)
    {
        Website::deleteFile($slide->image);
        $slide->delete();

        return $this->back('slides')->with('success', 'ลบสไลด์แล้ว');
    }

    // ------------------------------------------------------------- หน้าเนื้อหา

    public function savePage(Request $request, ?WebPage $page = null)
    {
        $page = $page?->exists ? $page : new WebPage;
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('web_pages', 'slug')->ignore($page->id)],
            'body' => ['nullable', 'string', 'max:50000'],
            'cover' => ['nullable', ...self::IMAGE],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
            'in_menu' => ['nullable', 'boolean'],
            'is_published' => ['nullable', 'boolean'],
        ], ['slug.regex' => 'ชื่อในลิงก์ใช้ได้เฉพาะ a-z 0-9 และ -'], ['title' => 'ชื่อหน้า', 'slug' => 'ชื่อในลิงก์']);

        $slug = $data['slug'] ?: ($page->slug ?: Str::slug($data['title']) ?: 'page-'.Str::lower(Str::random(6)));
        if (WebPage::where('slug', $slug)->whereKeyNot($page->id)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }
        $page->fill(['title' => $data['title'], 'slug' => $slug, 'body' => $data['body'] ?? '', 'sort' => (int) ($data['sort'] ?? 0),
            'in_menu' => $request->boolean('in_menu'), 'is_published' => $request->boolean('is_published')]);
        if ($request->hasFile('cover')) {
            Website::deleteFile($page->cover);
            $page->cover = Website::storeImage($request->file('cover'), 'pages');
        }
        $page->save();
        Audit::log('website.page', $page, 'บันทึกหน้า '.$page->title);

        return $this->back('pages')->with('success', 'บันทึกหน้าเนื้อหาแล้ว');
    }

    public function deletePage(WebPage $page)
    {
        Website::deleteFile($page->cover);
        $page->delete();

        return $this->back('pages')->with('success', 'ลบหน้าแล้ว');
    }

    // ------------------------------------------------------------------- ลิงก์

    public function saveLink(Request $request, ?WebLink $link = null)
    {
        $link = $link?->exists ? $link : new WebLink;
        $data = $request->validate([
            'kind' => ['required', Rule::in(array_keys(WebLink::KINDS))],
            'title' => ['required', 'string', 'max:150'],
            'url' => ['required', 'string', 'max:500'],
            'logo' => ['nullable', ...self::IMAGE],
            'icon' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['title' => 'ชื่อ', 'url' => 'ลิงก์']);

        $link->fill(['kind' => $data['kind'], 'title' => $data['title'], 'url' => $data['url'], 'icon' => $data['icon'] ?? null, 'sort' => (int) ($data['sort'] ?? 0)]);
        if ($request->hasFile('logo')) {
            Website::deleteFile($link->logo);
            $link->logo = Website::storeImage($request->file('logo'), 'links');
        }
        $link->save();

        return $this->back('links')->with('success', 'บันทึกลิงก์แล้ว');
    }

    public function deleteLink(WebLink $link)
    {
        Website::deleteFile($link->logo);
        $link->delete();

        return $this->back('links')->with('success', 'ลบลิงก์แล้ว');
    }

    // ---------------------------------------------------------- ทำเนียบบุคลากร

    public function savePersonnel(Request $request)
    {
        $ids = array_map('intval', (array) $request->input('public', []));
        $staff = User::whereIn('role', ['admin', 'teacher']);
        (clone $staff)->whereIn('id', $ids)->update(['public_profile' => true]);
        (clone $staff)->whereNotIn('id', $ids)->update(['public_profile' => false]);
        Audit::log('website.personnel', null, 'ตั้งรายชื่อบุคลากรบนเว็บไซต์ '.count($ids).' คน');

        return $this->back('personnel')->with('success', 'บันทึกทำเนียบบุคลากรแล้ว');
    }

    // ---------------------------------------------------------- ข้อความติดต่อ

    public function readMessage(WebMessage $message)
    {
        $message->update(['read_at' => $message->read_at ? null : now()]);

        return $this->back('messages');
    }

    public function deleteMessage(WebMessage $message)
    {
        $message->delete();

        return $this->back('messages')->with('success', 'ลบข้อความแล้ว');
    }

    private function itaData(Request $request): array
    {
        $years = \App\Models\ItaYear::orderByDesc('year')->get();
        $year = $years->firstWhere('year', (int) $request->query('year')) ?? $years->first();

        return [
            'years' => $years,
            'year' => $year?->load('items.evidences.post'),
            'posts' => \App\Models\WebPost::whereNotNull('published_at')->latest('published_at')->limit(200)->get(['id', 'title', 'category', 'published_at']),
            'suggestYear' => max(\App\Models\ItaYear::fiscalYearNow(), ($years->max('year') ?? 0) + 1),
        ];
    }

    private function back(string $tab)
    {
        $prev = url()->previous();

        return redirect()->to(str_contains($prev, 'website-admin') ? $prev : route('website.admin', ['tab' => $tab]));
    }
}
