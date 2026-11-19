<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebMessage;
use App\Models\WebPage;
use App\Models\WebPost;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** เว็บไซต์โรงเรียน (หน้าบ้าน) และหลังบ้านจัดการเว็บไซต์ */
class WebsiteTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        Settings::set(['website_enabled' => '1', 'school_name' => 'โรงเรียนทดสอบเว็บไซต์']);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makePost(array $attrs = []): WebPost
    {
        return WebPost::create($attrs + ['category' => 'news', 'title' => 'ข่าวทดสอบ', 'body' => 'เนื้อหา', 'published_at' => now()->subMinute()]);
    }

    public function test_disabled_website_keeps_old_behaviour(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get(route('web.home'))->assertNotFound();
        $this->get(route('web.news'))->assertNotFound();
        $this->post(route('web.contact.send'), ['name' => 'x'])->assertNotFound();
    }

    public function test_guests_see_website_and_logged_in_users_keep_dashboard(): void
    {
        $this->enable();
        $this->makePost(['title' => 'เปิดรับสมัครนักเรียนใหม่']);

        $this->get('/')->assertOk()->assertSee('โรงเรียนทดสอบเว็บไซต์')->assertSee('เปิดรับสมัครนักเรียนใหม่')->assertSee(route('login'));
        $this->actingAs($this->admin())->get('/')->assertOk()->assertDontSee('site-header', false);
    }

    public function test_manager_can_preview_disabled_website(): void
    {
        $this->actingAs($this->admin())->get(route('web.home'))->assertOk()->assertSee('โหมดตัวอย่าง');
        $this->actingAs(User::factory()->create(['role' => 'teacher']))->get(route('web.home'))->assertNotFound();
    }

    public function test_only_published_posts_are_public(): void
    {
        $this->enable();
        $live = $this->makePost(['title' => 'ข่าวเผยแพร่แล้ว']);
        $draft = $this->makePost(['title' => 'ข่าวฉบับร่าง', 'published_at' => null]);
        $later = $this->makePost(['title' => 'ข่าวตั้งเวลา', 'published_at' => now()->addDay()]);

        $this->get(route('web.news'))->assertSee('ข่าวเผยแพร่แล้ว')->assertDontSee('ข่าวฉบับร่าง')->assertDontSee('ข่าวตั้งเวลา');
        $this->get(route('web.post', $live))->assertOk();
        $this->get(route('web.post', $draft))->assertNotFound();
        $this->get(route('web.post', $later))->assertNotFound();
        $this->assertSame(1, $live->fresh()->views);
        $this->get(route('web.news', ['q' => 'เผยแพร่']))->assertSee('ข่าวเผยแพร่แล้ว');
        $this->get(route('web.news.category', 'nope'))->assertNotFound();
    }

    public function test_body_is_escaped_but_links_work(): void
    {
        $this->enable();
        $p = $this->makePost(['body' => "<script>alert(1)</script>\nดูเพิ่ม https://example.com/a"]);

        $this->get(route('web.post', $p))->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('<a href="https://example.com/a" target="_blank" rel="noopener">', false);
    }

    public function test_manager_creates_edits_and_deletes_post_with_files(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('website.posts.store'), [
            'category' => 'activity', 'title' => 'กีฬาสี', 'body' => 'สนุกมาก', 'status' => 'now', 'pinned' => 1,
            'images' => [UploadedFile::fake()->image('a.jpg', 2000, 1500), UploadedFile::fake()->image('b.png')],
            'files' => [UploadedFile::fake()->create('กำหนดการ.pdf', 50, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $post = WebPost::firstOrFail();
        $this->assertTrue($post->isPublished());
        $this->assertTrue($post->pinned);
        $this->assertCount(2, $post->images);
        $this->assertSame('กำหนดการ.pdf', $post->attachments[0]['name']);
        Storage::disk('public')->assertExists($post->images[0]);

        // แก้: ลบรูปแรก และเปลี่ยนเป็นฉบับร่าง
        $gone = $post->images[0];
        $this->put(route('website.posts.update', $post), ['category' => 'activity', 'title' => 'กีฬาสี 2569', 'status' => 'draft', 'remove_images' => [$gone]])
            ->assertSessionHasNoErrors();
        $post->refresh();
        $this->assertNull($post->published_at);
        $this->assertCount(1, $post->images);
        Storage::disk('public')->assertMissing($gone);

        $this->delete(route('website.posts.destroy', $post));
        $this->assertSame(0, WebPost::count());
        Storage::disk('public')->assertMissing($post->attachments[0]['path']);
    }

    public function test_scheduled_post_needs_a_time(): void
    {
        $this->actingAs($this->admin())->post(route('website.posts.store'), ['category' => 'news', 'title' => 'x', 'status' => 'schedule'])
            ->assertSessionHasErrors('publish_at');
    }

    public function test_website_permission_is_required_for_admin_pages(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($teacher)->get(route('website.admin'))->assertForbidden();
        $this->actingAs($this->admin())->get(route('website.admin'))->assertOk();
        foreach (array_keys(\App\Http\Controllers\WebsiteAdminController::TABS) as $tab) {
            $this->get(route('website.admin', ['tab' => $tab]))->assertOk();
        }
    }

    public function test_pages_and_personnel_respect_publish_flags(): void
    {
        $this->enable();
        $draft = WebPage::create(['slug' => 'secret', 'title' => 'หน้าลับ', 'is_published' => false]);
        WebPage::create(['slug' => 'history', 'title' => 'ประวัติโรงเรียน', 'body' => 'ก่อตั้งเมื่อ', 'is_published' => true, 'in_menu' => true]);
        User::factory()->create(['role' => 'teacher', 'name' => 'ครูแสดงบนเว็บ', 'public_profile' => true]);
        User::factory()->create(['role' => 'teacher', 'name' => 'ครูไม่ยินยอม', 'public_profile' => false]);

        $this->get(route('web.page', 'secret'))->assertNotFound();
        $this->get(route('web.page', 'history'))->assertOk()->assertSee('ก่อตั้งเมื่อ');
        $this->get(route('web.home'))->assertSee('ประวัติโรงเรียน'); // ขึ้นเมนู
        $this->get(route('web.personnel'))->assertSee('ครูแสดงบนเว็บ')->assertDontSee('ครูไม่ยินยอม');
        $this->assertFalse($draft->is_published);
    }

    public function test_personnel_selection_is_saved(): void
    {
        $a = User::factory()->create(['role' => 'teacher']);
        $b = User::factory()->create(['role' => 'teacher', 'public_profile' => true]);
        $this->actingAs($this->admin())->post(route('website.personnel.save'), ['public' => [$a->id]]);

        $this->assertTrue($a->fresh()->public_profile);
        $this->assertFalse($b->fresh()->public_profile);
    }

    public function test_contact_form_blocks_bots_and_saves_messages(): void
    {
        $this->enable();
        $form = ['name' => 'ผู้ปกครอง', 'contact' => '0812345678', 'subject' => 'สอบถาม', 'message' => 'รับสมัครเมื่อไร'];

        // ช่องลับมีค่า หรือกรอกเร็วเกินไป = บอท ไม่บันทึกแต่ตอบเหมือนสำเร็จ
        $this->post(route('web.contact.send'), $form + ['website' => 'spam', 'started' => encrypt(time() - 10)])->assertRedirect(route('web.contact'));
        $this->post(route('web.contact.send'), $form + ['started' => encrypt(time())])->assertRedirect(route('web.contact'));
        $this->assertSame(0, WebMessage::count());

        $this->post(route('web.contact.send'), $form + ['started' => encrypt(time() - 10)])->assertSessionHas('success');
        $this->assertSame(1, WebMessage::count());
        $this->post(route('web.contact.send'), ['started' => encrypt(time() - 10)])->assertSessionHasErrors(['name', 'contact', 'message']);
    }

    public function test_announcement_can_be_posted_to_website(): void
    {
        $this->actingAs($this->admin())->post(route('announcements.store'), [
            'title' => 'หยุดเรียนวันพรุ่งนี้', 'body' => 'เนื่องจาก...', 'audience' => 'all', 'to_website' => 1, 'send_line' => 0,
        ]);

        $this->assertSame('หยุดเรียนวันพรุ่งนี้', WebPost::firstOrFail()->title);
        $this->assertTrue(WebPost::firstOrFail()->isPublished());
    }

    public function test_settings_turn_website_on_and_create_starter_pages(): void
    {
        $this->actingAs($this->admin())->post(route('settings.update'), ['section' => 'website', 'website_enabled' => 1, 'website_greeting' => 'ยินดีต้อนรับ'])
            ->assertSessionHasNoErrors();

        $this->assertSame('1', Settings::get('website_enabled'));
        $this->assertSame(count(WebPage::STARTER), WebPage::count());
        $this->post(route('settings.update'), ['section' => 'website'])->assertSessionHasNoErrors();
        $this->assertSame('0', Settings::get('website_enabled'));
    }
}
