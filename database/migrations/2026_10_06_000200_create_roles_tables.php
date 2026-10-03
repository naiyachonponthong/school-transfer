<?php

use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('key', 40)->unique();
            $table->string('name', 100);
            $table->json('permissions');
            $table->boolean('is_system')->default(false); // ตำแหน่งตั้งต้น ลบไม่ได้ (แก้สิทธิ์ได้)
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'user_id']);
        });

        $now = now();
        foreach (Permissions::DEFAULT_ROLES as $key => [$name, $permissions]) {
            DB::table('roles')->insert([
                'key' => $key, 'name' => $name, 'permissions' => json_encode($permissions), 'is_system' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // ครูที่ตั้งเป็น "งานพัสดุ/อาคารสถานที่" ไว้ในหน้าตั้งค่าเดิม ยังทำงานได้ตามเดิม (User::canManageFacilities ตรวจทั้งสองทาง)
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
