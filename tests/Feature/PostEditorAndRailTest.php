<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WebPost;
use App\Support\HtmlSanitizer;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** หน้าเขียนข่าวแบบเต็มหน้า (HTML + แท็ก + ข่าวด่วน) และแถบซ้ายแบบหมวดที่เด้งเมนูย่อย */
class PostEditorAndRailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_sanitizer_keeps_formatting_and_strips_dangerous_html(): void
    {
        $out = HtmlSanitizer::clean('<h2 onclick="x()">หัวข้อ</h2><p style="color:#dc2626;position:fixed">แดง<script>alert(1)</script></p>'
            .'<a href="javascript:alert(1)">ลิงก์อันตราย</a><a href="https://ex.com">ปกติ</a><img src="x" onerror="a()"><img src="/storage/web/a.png">'
            .'<iframe src="https://evil.example/embed"></iframe><iframe src="https://www.youtube.com/watch?v=dQw4w9WgXcQ"></iframe><font color="blue">ฟ้า</font><style>*{}</style>');

        $this->assertStringContainsString('<h2>หัวข้อ</h2>', $out);
        $this->assertStringContainsString('<p style="color: #dc2626">แดง</p>', $out);
        $this->assertStringContainsString('<a href="https://ex.com" target="_blank" rel="noopener">ปกติ</a>', $out);
        $this->assertStringContainsString('src="/storage/web/a.png"', $out);
        $this->assertStringContainsString('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', $out);
        $this->assertStringContainsString('<span style="color: blue">ฟ้า</span>', $out);
        foreach (['script', 'onclick', 'onerror', 'javascript:', 'evil.example', 'position', '<style', 'src="x"'] as $bad) {
            $this->assertStringNotContainsString($bad, $out);
        }
        $this->assertSame('', HtmlSanitizer::clean('   '));
    }

    public function test_full_page_editor_saves_html_tags_urgent_and_excerpt(): void
    {
        Settings::set(['website_enabled' => '1']);
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('website.posts.create', ['category' => 'activity']))->assertOk()
            ->assertSee('data-rich-editor', false)->assertSee('value="activity" selected', false);

        $this->post(route('website.posts.store'), [
            'category' => 'activity', 'title' => 'กีฬาสีประจำปี', 'status' => 'now', 'urgent' => '1',
            'excerpt' => 'สรุปกีฬาสี', 'tags' => ['#กีฬาสี', 'กิจกรรม', 'กีฬาสี', ' '],
            'body' => '<h2>ผลการแข่งขัน</h2><p>สีแดง<b>ชนะ</b></p><script>alert(1)</script>',
        ])->assertSessionHasNoErrors()->assertRedirectContains('tab=posts')->assertSessionHas('success');

        $post = WebPost::where('title', 'กีฬาสีประจำปี')->firstOrFail();
        $this->assertTrue($post->is_html);
        $this->assertTrue($post->urgent);
        $this->assertSame(['กีฬาสี', 'กิจกรรม'], $post->tags);
        $this->assertStringNotContainsString('script', $post->body);
        $this->assertSame('สรุปกีฬาสี', $post->excerpt());

        $this->get(route('web.post', $post))->assertOk()->assertSee('<h2>ผลการแข่งขัน</h2>', false)->assertSee('ข่าวด่วน')
            ->assertSee(route('web.news', ['tag' => 'กีฬาสี']), false);
        $this->get(route('web.news', ['tag' => 'กีฬาสี']))->assertOk()->assertSee('กีฬาสีประจำปี')->assertSee('#กีฬาสี');
        $this->get(route('web.news', ['tag' => 'ไม่มีแท็กนี้']))->assertOk()->assertDontSee('กีฬาสีประจำปี');
        $this->get(route('website.posts.edit', $post))->assertOk()->assertSee('#กิจกรรม')->assertSee('สรุปกีฬาสี');
        $this->get(route('website.admin', ['tab' => 'posts']))->assertOk()->assertSee(route('website.posts.edit', $post), false);
    }

    public function test_old_plain_text_posts_still_render_and_open_in_editor(): void
    {
        Settings::set(['website_enabled' => '1']);
        $post = WebPost::create(['category' => 'news', 'title' => 'ข่าวเก่า', 'body' => "บรรทัดแรก\nดู https://ex.com <b>ไม่ใช่แท็ก</b>", 'published_at' => now()->subDay()]);

        $this->get(route('web.post', $post))->assertOk()->assertSee('บรรทัดแรก<br', false)->assertSee('&lt;b&gt;ไม่ใช่แท็ก', false);
        $this->assertSame('บรรทัดแรก ดู https://ex.com <b>ไม่ใช่แท็ก</b>', $post->plainText());
        $this->actingAs($this->admin())->get(route('website.posts.edit', $post))->assertOk()->assertSee('บรรทัดแรก&lt;br', false);
    }

    public function test_inline_image_upload_needs_permission_and_an_image(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'teacher']))
            ->postJson(route('website.posts.upload'), ['image' => UploadedFile::fake()->image('a.png')])->assertForbidden();

        $res = $this->actingAs($this->admin())->postJson(route('website.posts.upload'), ['image' => UploadedFile::fake()->image('a.png', 300, 200)])->assertOk();
        $this->assertStringContainsString('/storage/web/posts/inline/', $res->json('url'));
        $this->postJson(route('website.posts.upload'), ['image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertUnprocessable();
    }

    public function test_staff_rail_groups_menus_into_flyouts(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('home'))->assertOk()
            ->assertSee('data-rail-group', false)->assertSee('aria-controls="fly-', false)
            ->assertSee('งานประจำวัน')->assertSee(route('website.admin'), false);

        // ผู้ปกครองยังเป็นแถบไอคอนตรงแบบเดิม
        $parent = User::factory()->create(['role' => 'parent']);
        $this->actingAs($parent)->get(route('parent.home'))->assertOk()->assertDontSee('data-rail-group', false);
    }
}
