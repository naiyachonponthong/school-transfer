<?php

namespace Tests\Feature;

use App\Models\ItaEvidence;
use App\Models\ItaYear;
use App\Models\User;
use App\Models\WebPost;
use App\Support\ItaTemplate;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** ITA / OIT บนเว็บไซต์โรงเรียน */
class ItaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_create_year_from_template_then_copy_without_evidence(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post(route('website.ita.years.store'), ['year' => 2569, 'from' => 'template'])
            ->assertRedirect(route('website.admin', ['tab' => 'ita', 'year' => 2569]));
        $y = ItaYear::where('year', 2569)->firstOrFail();
        $this->assertSame(count(ItaTemplate::ITEMS), $y->items()->count());

        $item = $y->items()->where('code', 'O1')->first();
        $this->post(route('website.ita.evidence.store', $item), ['kind' => 'link', 'url' => 'https://example.com/o1'])->assertRedirect();
        $this->assertTrue($item->fresh()->done);

        $y->items()->where('code', 'O33')->delete();
        $this->post(route('website.ita.years.store'), ['year' => 2570, 'from' => 'copy'])->assertRedirect();
        $n = ItaYear::where('year', 2570)->firstOrFail();
        $this->assertSame(count(ItaTemplate::ITEMS) - 1, $n->items()->count());
        $this->assertSame(0, ItaEvidence::whereIn('ita_item_id', $n->items()->pluck('id'))->count());
        $this->assertSame(0, $n->items()->where('done', true)->count());

        $this->post(route('website.ita.years.store'), ['year' => 2570, 'from' => 'template'])->assertSessionHasErrors('year');
        $this->get(route('website.admin', ['tab' => 'ita', 'year' => 2569]))->assertOk()->assertSee('O32')->assertDontSee('O33')->assertSee('example.com/o1');
    }

    public function test_evidence_kinds_and_item_crud(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $y = ItaTemplate::createYear(2569, null);
        $item = $y->items()->first();
        $post = WebPost::create(['category' => 'procurement', 'title' => 'ประกาศแผนจัดซื้อ', 'body' => 'x', 'published_at' => now()->subMinute()]);

        $this->post(route('website.ita.evidence.store', $item), ['kind' => 'file', 'file' => UploadedFile::fake()->create('แผน.pdf', 100, 'application/pdf')])->assertSessionHasNoErrors();
        $this->post(route('website.ita.evidence.store', $item), ['kind' => 'post', 'web_post_id' => $post->id])->assertSessionHasNoErrors();
        $this->post(route('website.ita.evidence.store', $item), ['kind' => 'link', 'url' => 'not a url'])->assertSessionHasErrors('url');
        $this->assertSame(['file', 'post'], $item->evidences()->pluck('kind')->all());
        $file = $item->evidences()->where('kind', 'file')->first();
        Storage::disk('public')->assertExists($file->path);
        $this->assertSame('ประกาศแผนจัดซื้อ', $item->evidences()->where('kind', 'post')->value('title'));

        $this->delete(route('website.ita.evidence.destroy', $file))->assertRedirect();
        Storage::disk('public')->assertMissing($file->path);

        $this->post(route('website.ita.items.store', $y->id), ['code' => 'O1', 'title' => 'ซ้ำ'])->assertSessionHasErrors('code');
        $this->post(route('website.ita.items.store', $y->id), ['code' => 'O99', 'group' => 'เพิ่มเติม', 'title' => 'หัวข้อใหม่'])->assertSessionHasNoErrors();
        $o99 = $y->items()->where('code', 'O99')->first();
        $this->put(route('website.ita.items.update', [$y->id, $o99->id]), ['code' => 'O99', 'title' => 'แก้ชื่อแล้ว'])->assertSessionHasNoErrors();
        $this->assertSame('แก้ชื่อแล้ว', $o99->fresh()->title);
        $this->post(route('website.ita.items.toggle', $o99))->assertRedirect();
        $this->assertTrue($o99->fresh()->done);
        $this->delete(route('website.ita.items.destroy', $o99))->assertRedirect();
        $this->assertNull($o99->fresh());
    }

    public function test_public_page_shows_only_published_years(): void
    {
        Settings::set(['website_enabled' => '1']);
        $y = ItaTemplate::createYear(2569, null);
        $item = $y->items()->where('code', 'O12')->first();
        ItaEvidence::create(['ita_item_id' => $item->id, 'kind' => 'link', 'title' => 'หลักฐาน O12', 'url' => 'https://example.com/o12']);

        $this->get(route('web.ita'))->assertOk()->assertSee('ยังไม่มีข้อมูล ITA');
        $this->get(route('web.ita', 2569))->assertNotFound();
        $this->get(route('web.home'))->assertDontSee(route('web.ita'));

        $y->update(['is_published' => true]);
        $this->get(route('web.ita', 2569))->assertOk()->assertSee('id="O12"', false)->assertSee('หลักฐาน O12')->assertSee('https://example.com/o12');
        $this->get(route('web.home'))->assertSee(route('web.ita'))->assertSee('ปีงบประมาณ 2569');

        // ผู้ดูแลเห็นฉบับร่าง
        ItaTemplate::createYear(2570, null);
        $this->actingAs($this->admin())->get(route('web.ita', 2570))->assertOk()->assertSee('ฉบับร่าง');
    }

    public function test_only_website_managers_can_edit_ita(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $this->actingAs($teacher)->post(route('website.ita.years.store'), ['year' => 2569, 'from' => 'template'])->assertForbidden();
        $this->assertSame(0, ItaYear::count());
    }
}
