<p>ตารางเรียนรายห้องใช้ร่วมกันทั้งระบบ: ครูเห็นตารางสอนของตัวเอง <x-manual.link to="period">เช็คชื่อรายคาบ</x-manual.link> รู้ว่าคาบนี้สอนห้องไหน ผู้ปกครองและนักเรียนเห็นตารางเรียนในหน้าของตัวเอง</p>

<x-manual.section anchor="before" title="ก่อนจัดตาราง">
    <ul>
        <li>ตั้ง <b>เวลาเริ่ม-จบของแต่ละคาบ</b> ที่ <span class="ui">ตั้งค่า → เวลาเรียน</span> (ดู <x-manual.link to="settings" />)</li>
        <li><b>เปิดรายวิชา</b> ของห้องและกำหนดครูผู้สอนให้ครบ (ดู <x-manual.link to="gradebook" />) — ตารางเลือกได้เฉพาะรายวิชาที่เปิดแล้ว</li>
    </ul>
</x-manual.section>

<x-manual.section anchor="edit" title="จัดตารางรายห้อง (ผู้ดูแล / ฝ่ายวิชาการ)">
    <x-manual.steps>
        <li>เมนู <span class="ui">ตารางเรียน</span> เลือกห้อง → กด <span class="ui">จัดตาราง</span></li>
        <li>ในแต่ละช่อง (วัน × คาบ) <b>พิมพ์รหัสหรือชื่อวิชา</b> แล้วเลือกจากรายการ</li>
        <li>คาบที่ไม่มีรายวิชา เช่น ลูกเสือ ชุมนุม โฮมรูม พิมพ์เป็น<b>ข้อความอิสระ</b>ได้</li>
        <li>กด <span class="ui">บันทึกตาราง</span></li>
    </x-manual.steps>
    <x-manual.go route="timetable.index">เปิดหน้าตารางเรียน</x-manual.go>
    <x-manual.callout type="warn"><b>ครูสอนชนกัน</b> — ถ้าครูคนเดียวกันถูกจัดสอนสองห้องในวันและคาบเดียวกัน ระบบบันทึกให้แต่ขึ้นแถบเตือนสีเหลือง เช่น "ครูสอนชนกัน: จันทร์ คาบ 3 (ครูสอนห้อง ม.2/1 อยู่แล้ว)" ให้แก้ห้องใดห้องหนึ่งแล้วบันทึกใหม่</x-manual.callout>
</x-manual.section>

<x-manual.section anchor="mine" title="ตารางสอนของฉัน (ครู)">
    <p>หน้าตารางเรียน → <span class="ui">ตารางสอนของฉัน</span> รวมทุกคาบที่สอนจากทุกห้องในตารางเดียว กด <span class="ui">พิมพ์</span> ได้แผ่น A4 ติดโต๊ะทำงาน</p>
    <x-manual.go route="timetable.mine">เปิดตารางสอนของฉัน</x-manual.go>
</x-manual.section>

<x-manual.section anchor="print" title="พิมพ์ตารางเรียนติดห้อง">
    <p>เลือกห้อง แล้วกดปุ่ม <i class="bi bi-printer"></i> มุมขวาบน ได้ตารางเรียนของห้องพร้อมเวลาแต่ละคาบ ชื่อวิชาและครูผู้สอน</p>
</x-manual.section>
