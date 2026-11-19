<?php

return [
    // เวอร์ชันของโค้ด: เพิ่มเลขทุกครั้งที่ส่งชุดใหม่ที่มี migration ระบบจะพาผู้ดูแลระบบไปหน้า /update เอง
    'version' => '1.1.0',

    // preboot ตั้งเป็น true ตอนสร้าง .env ให้ครั้งแรก ตัวติดตั้งตั้งกลับเป็น false ตอนติดตั้งเสร็จ
    'install_pending' => (bool) env('SCHOOL_INSTALL_PENDING', false),

    // shared hosting ที่ไม่มี cron: ให้งานตั้งเวลา (สำรองข้อมูล แจ้งเตือน คิว LINE) ทำงานตอนมีคนเปิดเว็บแทน
    // ถ้าตั้ง cron ของโฮสต์แล้ว ระบบจะไม่ใช้ทางนี้เอง
    'web_cron' => (bool) env('SCHOOL_WEB_CRON', true),

    // APP_ENV=production บังคับ https ทุกลิงก์และคุกกี้ secure ปิดได้ชั่วคราวเมื่อโฮสต์ยังไม่มี SSL
    'force_https' => (bool) env('FORCE_HTTPS', true),

];
