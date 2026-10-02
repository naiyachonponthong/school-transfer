<x-manual.section anchor="cards" title="พิมพ์บัตรนักเรียน QR">
    <x-manual.steps>
        <li>เมนู <span class="ui">บัตรนักเรียน</span> เลือกห้อง</li>
        <li>กด <span class="ui">พิมพ์บัตร</span> — บัตรขนาดบัตรเครดิต มีรูป ชื่อ ห้อง และ QR</li>
        <li>ตัด แล้วเคลือบหรือใส่ซองพลาสติก</li>
    </x-manual.steps>
    <x-manual.go route="students.cards">เปิดหน้าบัตรนักเรียน</x-manual.go>
    <x-manual.callout type="note">QR บนบัตรเป็นรหัสสุ่ม ปลอมจากรหัสนักเรียนไม่ได้ บัตรเดียวใช้ได้ทั้ง สแกนหน้าประตู ยืมหนังสือห้องสมุด และห้องพยาบาล</x-manual.callout>
</x-manual.section>

<x-manual.section anchor="setup" title="ตั้งจุดสแกนหน้าประตู">
    <x-manual.steps>
        <li>ใช้แท็บเล็ต/มือถือที่มีกล้อง (หรือคอมพิวเตอร์ต่อเครื่องอ่านบาร์โค้ด USB) เข้าระบบด้วยบัญชีครูเวร</li>
        <li>เปิดเมนู <span class="ui">สแกนหน้าประตู</span> อนุญาตให้ใช้กล้องเมื่อเบราว์เซอร์ถาม</li>
        <li>วางเครื่องให้นักเรียนยื่นบัตรให้กล้องเห็น QR ชัด ๆ (ระยะประมาณ 15–25 ซม.)</li>
    </x-manual.steps>
    <x-manual.go route="gate">เปิดจุดสแกน</x-manual.go>
    <x-manual.callout type="warn">กล้องเปิดได้เฉพาะเว็บที่เป็น <b>https</b> เท่านั้น ถ้ากล้องไม่ขึ้น ดู <x-manual.link to="faq" /></x-manual.callout>
</x-manual.section>

<x-manual.section anchor="modes" title="โหมดการสแกน">
    <ul>
        <li><b>อัตโนมัติ</b> (แนะนำ) — ก่อนเวลา "สแกนหลังเวลานี้ = กลับบ้าน" นับเป็น <b>มาโรงเรียน</b> หลังจากนั้นนับเป็น <b>กลับบ้าน</b></li>
        <li><b>เข้า / ออก</b> — บังคับโหมด ใช้เมื่อมีกรณีพิเศษ เช่น ออกก่อนเวลา</li>
    </ul>
    <p>หน้าจอแสดงรูป ชื่อ และผลเป็นสี: <span class="badge text-bg-success">มา</span> <span class="badge text-bg-warning">สาย</span> (หลังเวลามาสายที่ตั้งไว้) <span class="badge text-bg-primary">กลับบ้าน</span> <span class="badge" style="background:#7c3aed">สแกนซ้ำ</span> <span class="badge text-bg-danger">ไม่พบบัตร</span> พร้อมเสียงยืนยัน และรายการสแกนล่าสุดด้านขวา</p>
</x-manual.section>

<x-manual.section anchor="result" title="ผลที่ได้">
    <ul>
        <li>ลงเช็คชื่อประจำวันเป็น มา/สาย ให้อัตโนมัติ ครูประจำชั้นแก้เฉพาะคนที่ไม่ได้สแกนพอ</li>
        <li>ผู้ปกครองที่เชื่อม LINE ได้ข้อความเมื่อลูกมาถึงและกลับบ้าน (ผู้ดูแลเปิด/ปิดได้ในตั้งค่า)</li>
    </ul>
</x-manual.section>
