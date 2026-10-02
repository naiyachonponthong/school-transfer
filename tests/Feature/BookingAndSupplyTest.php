<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BookableResource;
use App\Models\Booking;
use App\Models\Supply;
use App\Models\SupplyRequisition;
use App\Models\SupplyTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** จองห้อง/รถ/อุปกรณ์ · คลังวัสดุ + ใบเบิก */
class BookingAndSupplyTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function teacher(): User
    {
        return User::where('username', 'teacher')->first();
    }

    private function facility(): User
    {
        return User::find(User::facilityManagerIds()[0]);
    }

    private function otherTeacher(): User
    {
        return User::where('role', 'teacher')->get()->first(fn ($u) => ! $u->canManageFacilities() && $u->id !== $this->teacher()->id);
    }

    private function book(User $user, BookableResource $r, string $from, string $to, array $extra = [])
    {
        return $this->actingAs($user)->post('/bookings', [
            'resource_id' => $r->id, 'title' => 'ประชุม', 'date' => today()->addDay()->toDateString(), 'start_time' => $from, 'end_time' => $to,
        ] + $extra);
    }

    public function test_booking_prevents_overlaps(): void
    {
        $room = BookableResource::where('name', 'ห้องประชุม 1')->first();
        $this->book($this->teacher(), $room, '09:00', '11:00')->assertSessionHasNoErrors();
        $this->assertSame('approved', Booking::latest('id')->value('status'));

        $this->book($this->otherTeacher(), $room, '10:30', '12:00')->assertSessionHasErrors('start_time');
        $this->assertStringContainsString('ถูกจองแล้ว 09:00–11:00', session('errors')->first('start_time'));
        $this->book($this->otherTeacher(), $room, '11:00', '12:00')->assertSessionHasNoErrors(); // ต่อเวลากันพอดีได้
        $this->book($this->otherTeacher(), $room, '14:00', '13:00')->assertSessionHasErrors('end_time');
        $this->actingAs($this->teacher())->post('/bookings', ['resource_id' => $room->id, 'title' => 'x', 'date' => today()->subDay()->toDateString(), 'start_time' => '09:00', 'end_time' => '10:00'])
            ->assertSessionHasErrors('start_time');

        $this->actingAs($this->teacher())->get('/bookings?date='.today()->addDay()->toDateString())->assertOk()->assertSee('09:00–11:00');
    }

    public function test_approval_flow_for_vehicles(): void
    {
        $van = BookableResource::where('type', 'vehicle')->first();
        $this->book($this->teacher(), $van, '08:00', '16:00', ['destination' => 'อำเภอเมือง', 'end_date' => today()->addDays(2)->toDateString()])->assertSessionHasNoErrors();
        $b = Booking::latest('id')->first();
        $this->assertSame('pending', $b->status);
        $this->assertSame(2, (int) $b->starts_at->diffInDays($b->ends_at, true) + 1); // ค้างคืน 2 วัน

        // รออนุมัติก็กันเวลาไว้แล้ว
        $this->book($this->otherTeacher(), $van, '10:00', '11:00')->assertSessionHasErrors('start_time');
        $this->actingAs($this->teacher())->post("/bookings/{$b->id}/review", ['decision' => 'approved'])->assertForbidden();

        $this->actingAs($this->facility())->get('/bookings')->assertSee('รออนุมัติ')->assertSee('อำเภอเมือง');
        $this->actingAs($this->facility())->post("/bookings/{$b->id}/review", ['decision' => 'approved', 'review_note' => 'ใช้คนขับประจำ'])->assertRedirect();
        $this->assertSame('approved', $b->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'booking.review')->exists());

        // งานอาคารสถานที่จองเองไม่ต้องรออนุมัติ
        $this->actingAs($this->facility())->post('/bookings', ['resource_id' => $van->id, 'title' => 'ไปเขต', 'date' => today()->addDays(10)->toDateString(), 'start_time' => '09:00', 'end_time' => '12:00']);
        $this->assertSame('approved', Booking::latest('id')->value('status'));
    }

    public function test_cancel_permissions_and_resource_management(): void
    {
        $room = BookableResource::where('name', 'ห้องประชุม 1')->first();
        $this->book($this->teacher(), $room, '09:00', '10:00');
        $b = Booking::latest('id')->first();
        $this->actingAs($this->otherTeacher())->post("/bookings/{$b->id}/cancel")->assertForbidden();
        $this->actingAs($this->teacher())->post("/bookings/{$b->id}/cancel")->assertRedirect();
        $this->assertSame('cancelled', $b->fresh()->status);
        $this->book($this->otherTeacher(), $room, '09:00', '10:00')->assertSessionHasNoErrors(); // เวลาที่ยกเลิกแล้วว่าง

        $this->actingAs($this->teacher())->get('/booking-resources')->assertForbidden();
        $this->actingAs($this->facility())->post('/booking-resources', ['name' => 'ห้องโสตฯ', 'type' => 'room', 'capacity' => 60, 'requires_approval' => 1, 'is_active' => 1])->assertRedirect();
        $this->assertTrue(BookableResource::where('name', 'ห้องโสตฯ')->first()->requires_approval);
        $r = BookableResource::where('name', 'ห้องโสตฯ')->first();
        $this->actingAs($this->facility())->put("/booking-resources/{$r->id}", ['name' => 'ห้องโสตฯ', 'type' => 'room', 'is_active' => 0])->assertRedirect();
        $this->assertFalse($r->fresh()->is_active);
        $this->book($this->teacher(), $r, '09:00', '10:00')->assertSessionHasErrors('resource_id'); // ปิดให้จองแล้ว
    }

    public function test_stock_movements_keep_a_stock_card(): void
    {
        $m = $this->facility();
        $this->actingAs($m)->post('/supplies', ['name' => 'ลวดเย็บกระดาษ', 'unit' => 'กล่อง', 'min_stock' => 5, 'initial_stock' => 10])->assertRedirect();
        $s = Supply::where('name', 'ลวดเย็บกระดาษ')->first();
        $this->assertSame(10, $s->stock);
        $this->actingAs($m)->post("/supplies/{$s->id}/move", ['type' => 'in', 'quantity' => 5, 'note' => 'ซื้อเพิ่ม'])->assertSessionHasNoErrors();
        $this->actingAs($m)->post("/supplies/{$s->id}/move", ['type' => 'in', 'quantity' => -3])->assertSessionHasErrors('quantity');
        $this->actingAs($m)->post("/supplies/{$s->id}/move", ['type' => 'adjust', 'quantity' => -100])->assertSessionHasErrors('quantity');
        $this->actingAs($m)->post("/supplies/{$s->id}/move", ['type' => 'adjust', 'quantity' => -11, 'note' => 'ตรวจนับ'])->assertSessionHasNoErrors();
        $this->assertSame(4, $s->fresh()->stock);
        $this->assertTrue($s->fresh()->isLow());
        $this->assertSame([10, 15, 4], $s->transactions()->reorder('id')->pluck('balance')->all());
        $this->actingAs($m)->get("/supplies/{$s->id}")->assertOk()->assertSee('บัญชีวัสดุ')->assertSee('ตรวจนับ');
        $this->actingAs($m)->get('/supplies?low=1')->assertSee('ลวดเย็บกระดาษ');
        $this->actingAs($this->teacher())->get('/supplies')->assertForbidden();
    }

    public function test_requisition_issue_flow(): void
    {
        $paper = Supply::where('name', 'like', 'กระดาษ A4%')->first();
        $toner = Supply::where('name', 'like', 'ผงหมึก%')->first(); // คงคลัง 2
        $this->actingAs($this->teacher())->post('/requisitions', ['department' => 'คณิตศาสตร์', 'items' => [['supply_id' => '', 'quantity' => '']]])->assertSessionHasErrors('items');
        $this->actingAs($this->teacher())->post('/requisitions', ['department' => 'คณิตศาสตร์', 'purpose' => 'ทำใบงาน', 'items' => [
            ['supply_id' => $paper->id, 'quantity' => 3], ['supply_id' => $paper->id, 'quantity' => 2], ['supply_id' => $toner->id, 'quantity' => 5],
        ]])->assertRedirect();
        $req = SupplyRequisition::latest('id')->first();
        $this->assertSame('S'.(today()->year + 543).'-0003', $req->req_no);
        $this->assertSame([5, 5], $req->items()->orderBy('supply_id')->pluck('quantity')->all()); // วัสดุซ้ำรวมเป็นรายการเดียว

        $items = $req->items->keyBy('supply_id');
        $this->actingAs($this->teacher())->post("/requisitions/{$req->id}/issue", ['issued' => []])->assertForbidden();
        $this->actingAs($this->facility())->post("/requisitions/{$req->id}/issue", ['issued' => [$items[$paper->id]->id => 5, $items[$toner->id]->id => 5]])
            ->assertSessionHasErrors('issued'); // ผงหมึกมีแค่ 2
        $this->assertSame('pending', $req->fresh()->status);
        $this->assertSame(110, $paper->fresh()->stock); // ล้มทั้งใบ ไม่ตัดสต็อกบางส่วน

        $this->actingAs($this->facility())->post("/requisitions/{$req->id}/issue", ['issued' => [$items[$paper->id]->id => 5, $items[$toner->id]->id => 2], 'review_note' => 'ผงหมึกได้ 2'])->assertRedirect();
        $this->assertSame(['issued', 105, 0], [$req->fresh()->status, $paper->fresh()->stock, $toner->fresh()->stock]);
        $this->assertSame(2, SupplyTransaction::where('requisition_id', $req->id)->count());
        $this->actingAs($this->teacher())->get("/requisitions/{$req->id}")->assertOk()->assertSee('ใบเบิกวัสดุ')->assertSee('ผงหมึกได้ 2');
        $this->actingAs($this->otherTeacher())->get("/requisitions/{$req->id}")->assertForbidden();
        $this->actingAs($this->facility())->get('/supplies/report?month='.now()->format('Y-m'))->assertOk()->assertSee('คณิตศาสตร์')->assertSee('กระดาษ A4');
    }

    public function test_requisition_reject_and_cancel(): void
    {
        $pending = SupplyRequisition::where('status', 'pending')->first();
        $this->actingAs($this->facility())->post("/requisitions/{$pending->id}/reject", ['review_note' => 'ยังมีของในกลุ่มสาระ'])->assertRedirect();
        $this->assertSame('rejected', $pending->fresh()->status);

        $marker = Supply::where('name', 'ปากกาไวท์บอร์ด')->first();
        $this->actingAs($this->teacher())->post('/requisitions', ['items' => [['supply_id' => $marker->id, 'quantity' => 2]]]);
        $req = SupplyRequisition::latest('id')->first();
        $this->actingAs($this->otherTeacher())->post("/requisitions/{$req->id}/cancel")->assertForbidden();
        $this->actingAs($this->teacher())->post("/requisitions/{$req->id}/cancel")->assertRedirect();
        $this->assertSame('cancelled', $req->fresh()->status);
        $this->assertSame(100, $marker->fresh()->stock);
    }

    public function test_supply_details_photo_and_value(): void
    {
        Storage::fake('public');
        $m = $this->facility();
        $this->actingAs($m)->get('/supplies/create')->assertOk()->assertSee('รูปวัสดุ')->assertSee('ราคาต่อหน่วย');
        $this->actingAs($m)->post('/supplies', [
            'code' => 'ST-9', 'name' => 'เทปกาวสองหน้า', 'unit' => 'ม้วน', 'category' => 'วัสดุสำนักงาน', 'unit_price' => 35.5,
            'storage_location' => 'ตู้ 3', 'description' => 'กว้าง 1 นิ้ว', 'initial_stock' => 10,
            'photo' => UploadedFile::fake()->image('tape.jpg'),
        ])->assertRedirect(route('supplies.index'));
        $s = Supply::where('code', 'ST-9')->first();
        $this->assertSame([10, 355.0, 'ตู้ 3'], [$s->stock, $s->value(), $s->storage_location]);
        Storage::disk('public')->assertExists($s->photo);
        $this->actingAs($m)->post('/supplies', ['code' => 'ST-9', 'name' => 'ซ้ำ', 'unit' => 'ชิ้น'])->assertSessionHasErrors('code');

        // รับเข้าพร้อมราคาใหม่
        $this->actingAs($m)->post("/supplies/{$s->id}/move", ['type' => 'in', 'quantity' => 5, 'unit_price' => 40])->assertSessionHasNoErrors();
        $this->assertSame([15, 40.0], [$s->fresh()->stock, $s->fresh()->unit_price]);
        $this->actingAs($m)->get('/supplies')->assertSee('มูลค่าคงคลัง')->assertSee('600.00');
        $this->actingAs($m)->get("/supplies/{$s->id}")->assertSee('กว้าง 1 นิ้ว')->assertSee('ตู้ 3');
        $this->actingAs($m)->put("/supplies/{$s->id}", ['code' => 'ST-9', 'name' => 'เทปกาวสองหน้า 1 นิ้ว', 'unit' => 'ม้วน', 'is_active' => 1])->assertRedirect(route('supplies.show', $s));
        $this->assertSame(15, $s->fresh()->stock); // แก้ข้อมูลไม่กระทบยอดคงเหลือ

        // หน้าเขียนใบเบิกเป็นแคตตาล็อกพร้อมรูปและยอดคงเหลือ
        $this->actingAs($this->teacher())->get('/requisitions/create')->assertOk()->assertSee('เพิ่มในใบเบิก')->assertSee('เทปกาวสองหน้า 1 นิ้ว')->assertSee($s->photoUrl(), false);
    }

    public function test_resource_details_and_day_slots(): void
    {
        Storage::fake('public');
        $m = $this->facility();
        $this->actingAs($m)->get('/booking-resources/create?type=vehicle')->assertOk()->assertSee('ทะเบียนรถ')->assertSee('สิ่งอำนวยความสะดวก');
        $this->actingAs($m)->post('/booking-resources', [
            'name' => 'ห้องสมุดชั้น 2', 'type' => 'room', 'capacity' => 40, 'location' => 'อาคาร 3', 'amenities' => ['โปรเจกเตอร์', 'Wi-Fi'],
            'amenities_other' => 'โซฟา, Wi-Fi', 'rules' => 'ห้ามนำอาหารเข้า', 'is_active' => 1, 'photo' => UploadedFile::fake()->image('lib.jpg'),
        ])->assertRedirect(route('bookings.resources'));
        $r = BookableResource::where('name', 'ห้องสมุดชั้น 2')->first();
        $this->assertSame(['โปรเจกเตอร์', 'Wi-Fi', 'โซฟา'], $r->amenityList()); // รวมที่ติ๊ก + พิมพ์เพิ่ม ไม่ซ้ำ
        $this->assertNotNull($r->photo);
        $this->actingAs($m)->get('/booking-resources')->assertSee('ห้องสมุดชั้น 2')->assertSee('โซฟา')->assertSee('อาคาร 3');

        $this->book($this->teacher(), $r, '10:00', '11:30');
        $this->assertSame($this->teacher()->phone, Booking::latest('id')->value('contact_phone')); // เบอร์ติดต่อตั้งต้นจากบัญชี
        $this->actingAs($this->otherTeacher())->getJson('/bookings/day?resource='.$r->id.'&date='.today()->addDay()->toDateString())
            ->assertOk()->assertJsonPath('0.from', '10:00')->assertJsonPath('0.to', '11:30')->assertJsonPath('0.mine', false);
        $this->actingAs($this->teacher())->get('/bookings/create?resource='.$r->id)->assertOk()->assertSee('ช่วงเวลาที่ถูกจองแล้ว')
            ->assertSee(trim(json_encode('ห้ามนำอาหารเข้า'), '"'), false); // ข้อปฏิบัติอยู่ในข้อมูล JSON ของหน้า (แสดงเมื่อเลือก)
    }
}
