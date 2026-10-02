<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** รายละเอียดเพิ่มเติม: รูป รหัส ราคา ที่เก็บ (วัสดุ) · รูป สถานที่ สิ่งอำนวยความสะดวก ทะเบียนรถ ผู้ดูแล (ห้อง/รถ) */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplies', function (Blueprint $t) {
            $t->string('code', 30)->nullable()->unique();
            $t->string('photo')->nullable();
            $t->decimal('unit_price', 10, 2)->default(0);
            $t->string('storage_location', 100)->nullable();
            $t->text('description')->nullable();
        });
        Schema::table('bookable_resources', function (Blueprint $t) {
            $t->string('photo')->nullable();
            $t->string('location')->nullable();               // อาคาร / ชั้น
            $t->string('amenities', 500)->nullable();         // สิ่งอำนวยความสะดวก คั่นด้วย ,
            $t->string('plate_no', 30)->nullable();           // ทะเบียนรถ
            $t->string('contact')->nullable();                // ผู้ดูแล / คนขับ + เบอร์
            $t->text('rules')->nullable();                    // ข้อปฏิบัติในการใช้
        });
        Schema::table('bookings', function (Blueprint $t) {
            $t->string('contact_phone', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn('contact_phone'));
        Schema::table('bookable_resources', fn (Blueprint $t) => $t->dropColumn(['photo', 'location', 'amenities', 'plate_no', 'contact', 'rules']));
        Schema::table('supplies', function (Blueprint $t) {
            $t->dropUnique(['code']);
            $t->dropColumn(['code', 'photo', 'unit_price', 'storage_location', 'description']);
        });
    }
};
