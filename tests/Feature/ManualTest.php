<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Manual;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/** คู่มือการใช้งาน: ทุกหัวข้อแสดงได้ · เห็นตามบทบาท · ค้นหา · พิมพ์ทั้งเล่ม · ปุ่ม ? ตามหน้า · ปุ่มเปิดหน้าตามสิทธิ์ */
class ManualTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    public function test_every_topic_has_content_and_valid_references(): void
    {
        $routeNames = collect(Route::getRoutes()->getRoutes())->map->getName()->filter()->all();
        foreach (array_keys(Manual::TOPICS) as $key) {
            $file = resource_path("views/manual/topics/{$key}.blade.php");
            $this->assertFileExists($file, "หัวข้อ {$key} ไม่มีไฟล์เนื้อหา");
            $src = file_get_contents($file);
            preg_match_all('/x-manual\.go route="([^"]+)"/', $src, $go);
            foreach ($go[1] as $name) {
                $this->assertTrue(Route::has($name), "{$key}: ไม่มี route {$name}");
            }
            preg_match_all('/x-manual\.link to="([^"]+)"/', $src, $links);
            foreach ($links[1] as $to) {
                $this->assertArrayHasKey($to, Manual::TOPICS, "{$key}: ลิงก์ไปหัวข้อ {$to} ที่ไม่มีอยู่");
            }
            // รูปแบบ route ที่ผูกปุ่ม ? ต้องตรงกับ route จริงอย่างน้อยหนึ่งตัว
            foreach (Manual::TOPICS[$key][5] as $pattern) {
                $this->assertNotEmpty(array_filter($routeNames, fn ($n) => Str::is($pattern, $n)), "{$key}: รูปแบบ {$pattern} ไม่ตรงกับ route ใด");
            }
            $this->assertContains(Manual::TOPICS[$key][2], Manual::GROUPS);
        }
    }

    public function test_admin_can_open_every_topic_and_print_the_whole_book(): void
    {
        $admin = $this->user('admin');
        foreach (Manual::TOPICS as $key => $t) {
            $this->actingAs($admin)->get(route('manual.show', $key))->assertOk()->assertSee($t[0], false);
        }
        $print = $this->actingAs($admin)->get(route('manual.print'))->assertOk();
        foreach (Manual::TOPICS as $t) {
            $print->assertSee($t[0], false);
        }
        // ปุ่มเปิดหน้าในระบบไม่ติดไปในฉบับพิมพ์
        $this->assertStringNotContainsString('manual-go', $print->getContent());
        $this->actingAs($admin)->get(route('manual.print', ['topic' => 'library']))->assertOk()->assertSee('ระบบทศนิยมของดิวอี้', false);
    }

    public function test_topics_are_filtered_by_role(): void
    {
        $cases = [
            'teacher' => [['attendance', 'gradebook', 'library', 'finance', 'parent', 'faq'], ['settings', 'users', 'admissions', 'it']],
            '0812345678' => [['parent', 'homework', 'line', 'faq'], ['gradebook', 'student', 'settings', 'library']],
            '69001' => [['student', 'homework', 'calendar', 'faq'], ['parent', 'gradebook', 'finance', 'settings']],
        ];
        foreach ($cases as $username => [$allowed, $hidden]) {
            $user = $this->user($username);
            $index = $this->actingAs($user)->get(route('manual.index'))->assertOk();
            foreach ($allowed as $key) {
                $this->actingAs($user)->get(route('manual.show', $key))->assertOk();
                $index->assertSee(route('manual.show', $key), false);
            }
            foreach ($hidden as $key) {
                $this->actingAs($user)->get(route('manual.show', $key))->assertNotFound();
                $this->actingAs($user)->get(route('manual.print', ['topic' => $key]))->assertNotFound();
                $index->assertDontSee(route('manual.show', $key), false);
            }
        }
        // หัวข้อที่ไม่มีอยู่จริง
        $this->actingAs($this->user('admin'))->get('/manual/no-such-topic')->assertNotFound();
    }

    public function test_every_visible_topic_renders_for_each_role(): void
    {
        foreach (['teacher', '0812345678', '69001'] as $username) {
            $user = $this->user($username);
            foreach (array_keys(Manual::visible($user)) as $key) {
                $this->actingAs($user)->get(route('manual.show', $key))->assertOk();
            }
            $this->actingAs($user)->get(route('manual.print'))->assertOk();
        }
    }

    public function test_search_finds_words_inside_topic_content(): void
    {
        $teacher = $this->user('teacher');
        $res = $this->actingAs($teacher)->get(route('manual.index', ['q' => 'ป้ายสัน']))->assertOk();
        $res->assertSee(route('manual.show', 'library'), false);
        $res->assertSee('<mark', false);

        // คำที่เจอหลายจุดหรือใกล้ท้ายเนื้อหา ต้องไม่ทำให้ค้นหาพัง
        foreach (['มส', 'ห้อง', 'LINE', 'ป.', 'zzzz'] as $q) {
            $this->actingAs($this->user('admin'))->get(route('manual.index', ['q' => $q]))->assertOk();
        }

        // ผู้ปกครองค้นหาไม่เจอหัวข้อที่ตัวเองมองไม่เห็น
        $parent = $this->user('0812345678');
        $this->actingAs($parent)->get(route('manual.index', ['q' => 'ป้ายสัน']))->assertOk()
            ->assertDontSee(route('manual.show', 'library'), false);
        $this->actingAs($parent)->get(route('manual.index', ['q' => 'ใบลา']))->assertOk()
            ->assertSee(route('manual.show', 'parent'), false);
    }

    public function test_help_button_opens_the_topic_of_the_current_page(): void
    {
        $teacher = $this->user('teacher');
        $this->assertSame('library', Manual::forRoute('library.loans', $teacher));
        $this->assertSame('exams', Manual::forRoute('exams.index', $teacher));
        $this->assertSame('supplies', Manual::forRoute('requisitions.create', $teacher));
        $this->assertNull(Manual::forRoute('settings', $teacher)); // ครูไม่เห็นหัวข้อตั้งค่า
        $this->assertSame('settings', Manual::forRoute('settings', $this->user('admin')));

        $this->actingAs($teacher)->get(route('library.loans'))->assertOk()
            ->assertSee(route('manual.show', 'library'), false);
        // หน้าที่ไม่มีหัวข้อเฉพาะ → ไปสารบัญคู่มือ
        $this->actingAs($this->user('0812345678'))->get(route('parent.home'))->assertOk()
            ->assertSee(route('manual.show', 'parent'), false);
    }

    public function test_open_page_buttons_follow_permissions(): void
    {
        $teacher = $this->user('teacher');
        $manager = $this->user('t8'); // ครูงานพัสดุในข้อมูลตัวอย่าง
        $parent = $this->user('0812345678');

        // หน้าของผู้ปกครองเปิดได้เฉพาะผู้ปกครอง
        $this->actingAs($teacher)->get(route('manual.show', 'parent'))->assertOk()->assertDontSee(route('parent.leave'), false);
        $this->actingAs($parent)->get(route('manual.show', 'parent'))->assertOk()->assertSee(route('parent.leave'), false);

        // คลังวัสดุเปิดได้เฉพาะงานพัสดุ · เขียนใบเบิกได้ทุกคน
        $this->actingAs($teacher)->get(route('manual.show', 'supplies'))->assertOk()
            ->assertSee(route('requisitions.create'), false)->assertDontSee(route('supplies.index').'"', false);
        $this->actingAs($manager)->get(route('manual.show', 'supplies'))->assertOk()
            ->assertSee(route('supplies.index').'"', false);

        // หน้าเฉพาะผู้ดูแลระบบ
        $this->actingAs($teacher)->get(route('manual.show', 'documents'))->assertOk()->assertDontSee(route('graduates.index'), false);
        $this->actingAs($this->user('admin'))->get(route('manual.show', 'documents'))->assertOk()->assertSee(route('graduates.index'), false);
    }

    public function test_manual_appears_in_every_role_menu(): void
    {
        foreach (['admin', 'teacher', '0812345678', '69001'] as $username) {
            $this->actingAs($this->user($username))->get(route('menu'))->assertOk()
                ->assertSee(route('manual.index'), false)->assertSee('คู่มือการใช้งาน', false);
        }
    }
}
