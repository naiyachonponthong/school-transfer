<p>สำหรับครู/เจ้าหน้าที่ไอทีที่ดูแลเครื่องเซิร์ฟเวอร์ของระบบ (ผู้ใช้ทั่วไปไม่ต้องอ่านส่วนนี้)</p>

<x-manual.section anchor="require" title="ความต้องการของระบบ">
    <ul>
        <li><b>PHP 8.3 ขึ้นไป</b> และ Composer · ส่วนขยาย: curl, fileinfo, gd, mbstring, openssl, pdo_mysql, zip</li>
        <li><b>MySQL / MariaDB</b> (utf8mb4) — ห้ามใช้ SQLite กับงานจริง</li>
        <li>เว็บเซิร์ฟเวอร์ (Nginx / Apache / IIS หรือ Laragon บน Windows) ชี้ document root ไปที่โฟลเดอร์ <code>public</code></li>
        <li><b>https</b> พร้อมใบรับรอง — จำเป็นสำหรับกล้อง GPS และ LINE webhook</li>
        <li>ไม่ต้องใช้ Node.js / npm</li>
    </ul>
</x-manual.section>

<x-manual.section anchor="install" title="ติดตั้งครั้งแรก">
<pre class="small bg-light border rounded p-2">composer install --no-dev
copy .env.example .env        (Linux: cp .env.example .env)
php artisan key:generate
# แก้ .env: DB_*  APP_ENV=production  APP_DEBUG=false  APP_URL=https://โดเมนของโรงเรียน
php artisan school:install    # สร้างตาราง + บัญชีผู้ดูแลคนแรก + storage:link (ล้างข้อมูลทั้งหมด!)
php artisan config:cache
php artisan route:cache
php artisan view:cache</pre>
    <x-manual.callout type="warn"><code>school:install</code> <b>ล้างฐานข้อมูลทั้งหมด</b> ใช้ครั้งแรกเท่านั้น · อย่าใช้ <code>migrate --seed</code> กับงานจริง (เป็นข้อมูลตัวอย่าง)</x-manual.callout>
    <ul>
        <li>โฟลเดอร์ <code>storage/</code> และ <code>bootstrap/cache/</code> ต้องให้เว็บเซิร์ฟเวอร์เขียนได้</li>
        <li>แก้ <code>.env</code> ภายหลัง ต้องรัน <code>php artisan config:clear</code> (แล้ว <code>config:cache</code> ใหม่) ค่าจึงมีผล</li>
    </ul>
</x-manual.section>

<x-manual.section anchor="schedule" title="ตั้งเวลางานอัตโนมัติ (สำคัญ)">
    <p>ระบบสำรองฐานข้อมูล 02:00 และไฟล์อัปโหลด 02:15 ทุกคืน แต่จะทำงานก็ต่อเมื่อมีการเรียก <code>php artisan schedule:run</code> <b>ทุก 1 นาที</b>:</p>
    <ul>
        <li><b>Windows:</b> Task Scheduler → Create Task → Trigger ทุก 1 นาที (Repeat task every 1 minute, indefinitely) → Action: <code>php</code> อาร์กิวเมนต์ <code>artisan schedule:run</code> · Start in = โฟลเดอร์โปรเจกต์</li>
        <li><b>Linux:</b> <code>crontab -e</code> เพิ่ม <code>* * * * * cd /path/to/project &amp;&amp; php artisan schedule:run &gt;&gt; /dev/null 2&gt;&amp;1</code></li>
    </ul>
    <p>ตรวจว่าทำงาน: เมนู <span class="ui">สำรองข้อมูล</span> ต้องมีไฟล์ใหม่ทุกวัน</p>
</x-manual.section>

<x-manual.section anchor="backup" title="สำรองและกู้คืน">
    <ul>
        <li>ไฟล์สำรองอยู่ที่ <code>storage/app/backups</code> เก็บย้อนหลัง 14 วัน — <b>คัดลอกออกนอกเครื่องเป็นระยะ</b> (ไดรฟ์อื่น / คลาวด์) เผื่อฮาร์ดดิสก์เสีย</li>
        <li>สำรองทันที: <code>php artisan backup:database</code> และ <code>php artisan backup:files</code> (หรือกด "สำรองตอนนี้" ในเมนูสำรองข้อมูล)</li>
        <li>กู้คืน: <code>php artisan backup:restore</code> แล้วเลือกไฟล์ (ระบบสำรองข้อมูลปัจจุบันไว้ก่อนกู้เสมอ) — ดู <x-manual.link to="backup" /></li>
    </ul>
</x-manual.section>

<x-manual.section anchor="update" title="อัปเดตเป็นเวอร์ชันใหม่">
    <x-manual.steps>
        <li><b>สำรองข้อมูลก่อนเสมอ:</b> <code>php artisan backup:database</code> และ <code>php artisan backup:files</code></li>
        <li>คัดลอกโค้ดเวอร์ชันใหม่ทับ (หรือ <code>git pull</code>) — <b>อย่าทับ</b> ไฟล์ <code>.env</code> และโฟลเดอร์ <code>storage/</code></li>
        <li><code>composer install --no-dev</code></li>
        <li><code>php artisan migrate --force</code> (ปรับโครงสร้างฐานข้อมูล ข้อมูลเดิมไม่หาย)</li>
        <li><code>php artisan optimize:clear</code> แล้ว <code>php artisan config:cache</code> <code>route:cache</code> <code>view:cache</code></li>
        <li>ผู้ใช้กด <kbd>Ctrl</kbd>+<kbd>F5</kbd> หนึ่งครั้งเพื่อโหลดไฟล์ CSS/JS ใหม่</li>
    </x-manual.steps>
</x-manual.section>

<x-manual.section anchor="tools" title="คำสั่งที่มีประโยชน์">
    <ul>
        <li><code>php artisan users:require-password-change --role=parent --role=student</code> — บังคับบัญชีตามบทบาทให้ตั้งรหัสผ่านใหม่ตอนเข้าครั้งถัดไป (เว้น --role = ทุกบัญชี)</li>
        <li><code>php artisan about</code> — ดูเวอร์ชัน PHP/Laravel และสถานะแคช</li>
        <li>ไฟล์บันทึกข้อผิดพลาด: <code>storage/logs/laravel.log</code> (แนบไฟล์นี้เวลาแจ้งปัญหา)</li>
        <li>ไม่ต้องรัน <code>queue:work</code> — การแจ้งเตือน LINE ส่งหลังตอบหน้าเว็บโดยไม่ใช้คิว</li>
    </ul>
</x-manual.section>
