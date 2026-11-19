<?php

return [

    /*
    | ศูนย์ License (digitalstore) ตั้งค่าใน .env ได้เพื่อทดสอบ แต่ปลอมลายเซ็นไม่ได้
    | เพราะ public key ฝังอยู่ในโค้ดที่ App\Support\License\Keys
    */
    'server' => env('SCHOOL_LICENSE_SERVER', 'https://thereal-programmers.com'),

    // ชื่อสินค้าฝั่งร้าน (ใช้ตอนขอสิทธิ์ทดลอง) และตัวขึ้นต้นของคีย์
    'product' => 'school',
    'prefix' => 'SCH1',

    // ทดลองใช้กี่วันเมื่อไม่กรอกคีย์ (ฝั่งร้านเป็นผู้กำหนดจริง ค่านี้ใช้แสดงผลเท่านั้น)
    'trial_days' => 30,

    // ติดต่อร้านไม่ได้ ใช้ต่อได้อีกกี่วันก่อนล็อก
    'grace_days' => 7,

    // เตือนล่วงหน้าก่อนหมดอายุกี่วัน
    'warn_days' => 7,

    // ตรวจกับร้านทุกกี่ชั่วโมง
    'refresh_hours' => 24,

    // วินาทีที่รอร้านตอบ
    'timeout' => 10,

    // ระบบที่อัปเกรดจากรุ่นก่อนมี License ผ่อนผันกี่วันให้ไปหาคีย์
    'upgrade_grace_days' => 30,

    /*
    | ค่าเหล่านี้ใช้ได้เฉพาะตอนรันชุดทดสอบของนักพัฒนา (ชุดติดตั้งของลูกค้าไม่มี PHPUnit)
    | test_public_key: ใช้กุญแจทดสอบแทนกุญแจจริง · test_enforce: บังคับด่าน License ในเทสต์
    */
    'test_public_key' => null,
    'test_release_public_key' => null,
    'test_enforce' => false,

    // ที่อยู่ของรายการไฟล์ ว่างไว้คือ bootstrap/integrity.json ในโปรเจกต์ (ใช้ย้ายที่ตอนทดสอบ)
    'manifest_path' => null,
];
