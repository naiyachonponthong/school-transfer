<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentHomeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_outstanding_fees_banner_lists_every_invoice_when_more_than_one(): void
    {
        $parent = User::where('phone', '0812345678')->first();
        $children = $parent->children()->get();
        Invoice::whereIn('student_id', $children->pluck('id'))->update(['status' => 'paid']);
        $make = fn ($child, string $title, float $total, $due) => Invoice::create([
            'invoice_no' => 'T'.fake()->unique()->numerify('########'), 'student_id' => $child->id, 'term_id' => Term::current()->id,
            'title' => $title, 'due_date' => $due, 'total' => $total, 'discount' => 0, 'paid' => 0, 'status' => 'unpaid',
        ]);

        // ค้างใบเดียว: กดแล้วเข้าใบนั้นเลย
        $first = $make($children[0], 'ค่าเอกสาร/แบบฝึกหัด', 50, today()->addDays(5));
        $this->actingAs($parent)->get(route('parent.home'))->assertOk()
            ->assertSee('ค่าธรรมเนียมค้างชำระ 50.00 บาท')->assertSee(route('invoices.show', $first), false)->assertDontSee('แตะรายการเพื่อดูช่องทางชำระ');

        // ค้างหลายใบ: ยอดรวมต้องมาพร้อมรายการทุกใบ ไม่พาไปใบแรกใบเดียว
        $second = $make($children[1] ?? $children[0], 'ค่าธรรมเนียมการศึกษา ภาคเรียนที่ 1', 2500, today()->subDays(3));
        $this->get(route('parent.home'))->assertOk()
            ->assertSee('ค่าธรรมเนียมค้างชำระ 2,550.00 บาท')->assertSee('2 รายการ')
            ->assertSee(route('invoices.show', $first), false)->assertSee(route('invoices.show', $second), false)
            ->assertSee('ค่าธรรมเนียมการศึกษา ภาคเรียนที่ 1')->assertSee('เลยกำหนด');
    }
}
