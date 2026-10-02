<p>ครูสั่งงานพร้อมใบงาน ผู้ปกครองได้แจ้งเตือนทาง LINE นักเรียนหรือผู้ปกครองส่งงานเป็นรูป/ไฟล์ ครูตรวจให้คะแนนและส่งเข้าสมุดคะแนนได้ทันที</p>

<x-manual.section anchor="assign" title="สั่งงาน (ครู)">
    <x-manual.steps>
        <li>เมนู <span class="ui">การบ้าน</span> → <span class="ui">สั่งงานใหม่</span></li>
        <li>เลือก <b>รายวิชา / ห้อง</b> · พิมพ์ <b>ชื่องาน</b> (เช่น แบบฝึกหัดบทที่ 4 ข้อ 1-10) · <b>รายละเอียด</b></li>
        <li><b>กำหนดส่ง</b> (วันและเวลา) · <b>คะแนนเต็ม</b> (เว้นว่างได้ถ้าไม่เก็บคะแนน)</li>
        <li><b>ผูกช่องคะแนน</b> — เลือกช่องในสมุดคะแนนที่จะส่งคะแนนงานนี้เข้าไป</li>
        <li><b>ไฟล์ประกอบ</b> ใบงาน/รูป (ไม่เกิน 10 MB) · ติ๊ก <span class="ui">แจ้งผู้ปกครองทาง LINE</span></li>
        <li>กด <span class="ui">สั่งงาน</span></li>
    </x-manual.steps>
    <x-manual.go route="homework.index">เปิดหน้าการบ้าน</x-manual.go>
</x-manual.section>

<x-manual.section anchor="submit" title="ส่งงาน (นักเรียน / ผู้ปกครอง)">
    <x-manual.steps>
        <li>นักเรียน: หน้าแรก → <span class="ui">การบ้าน</span> · ผู้ปกครอง: เมนู <span class="ui">การบ้าน</span> (ส่งแทนบุตรหลานได้)</li>
        <li>กด <span class="ui">ส่งงาน</span> ใต้ชื่องาน → พิมพ์คำตอบหรือข้อความถึงครู และ/หรือ <b>ถ่ายรูปงาน</b>/แนบไฟล์ (รูป PDF Word PowerPoint Excel วิดีโอ ไม่เกิน 20 MB)</li>
        <li>กด <span class="ui">ส่ง</span> — กด <span class="ui">ส่งใหม่ / แก้ไขงาน</span> ได้จนกว่าครูจะตรวจให้คะแนน</li>
    </x-manual.steps>
    <ul>
        <li>สถานะ: <span class="badge bg-secondary">ยังไม่ส่ง</span> <span class="badge bg-primary">ส่งแล้ว</span> <span class="badge bg-warning text-dark">ส่งช้า</span> <span class="badge bg-success">ตรวจแล้ว</span> (เห็นคะแนนและความเห็นครู)</li>
        <li>เลยกำหนดแล้วยังส่งได้ แต่จะขึ้นว่า <b>ส่งช้า</b></li>
    </ul>
</x-manual.section>

<x-manual.section anchor="grade" title="ตรวจงานและให้คะแนน (ครู)">
    <x-manual.steps>
        <li>หน้าการบ้าน → คลิกชื่องาน จะเห็นรายชื่อทั้งห้องพร้อมสถานะ ตัวเลข <b>ส่งแล้ว / ตรวจแล้ว</b></li>
        <li>กด <span class="ui">เปิดไฟล์</span> เพื่อดูงานที่ส่ง · นักเรียนที่ส่งเป็นกระดาษ ติ๊ก <span class="ui">ส่งกระดาษ</span></li>
        <li>กรอก <b>คะแนน</b> และ <b>ความเห็นครู</b> (เช่น ดีมาก / แก้ข้อ 3) → <span class="ui">บันทึกการตรวจ</span></li>
        <li>กด <span class="ui">ส่งเข้าสมุดคะแนน</span> — คะแนนถูกแปลงสัดส่วนตามคะแนนเต็มของช่องที่ผูกไว้ และเขียนลงสมุดคะแนนทั้งห้อง</li>
    </x-manual.steps>
    <x-manual.callout type="note">ปุ่มส่งเข้าสมุดคะแนนใช้ได้เมื่อตอนสั่งงาน<b>ผูกช่องคะแนนและกำหนดคะแนนเต็ม</b>ไว้ · รายวิชาที่ล็อกคะแนนแล้วส่งไม่ได้ (ยกเว้นผู้ดูแล)</x-manual.callout>
    <x-manual.callout type="tip">ส่งเข้าสมุดคะแนนซ้ำได้หลายครั้ง (เช่น มีคนส่งงานเพิ่มทีหลัง) ระบบเขียนทับด้วยคะแนนล่าสุด</x-manual.callout>
</x-manual.section>
