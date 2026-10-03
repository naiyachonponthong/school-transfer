<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // การรับทราบประกาศความเป็นส่วนตัว (PDPA): เก็บว่าใครรับทราบฉบับไหน เมื่อไร
        Schema::create('privacy_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('ip', 45)->nullable();
            $table->timestamp('accepted_at');
            $table->unique(['user_id', 'version']);
        });

        // สิทธิ์ใหม่ "แดชบอร์ดผู้บริหาร" ให้ตำแหน่งผู้บริหารที่มีอยู่แล้ว
        foreach (DB::table('roles')->where('key', 'executive')->get() as $role) {
            $permissions = array_values(array_unique([...(json_decode($role->permissions, true) ?: []), 'executive.view']));
            DB::table('roles')->where('id', $role->id)->update(['permissions' => json_encode($permissions)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_consents');
    }
};
