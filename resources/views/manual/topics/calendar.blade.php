<p>ปฏิทินกลางของโรงเรียน ทุกคนเปิดดูได้ ผู้ดูแลระบบเป็นผู้เพิ่ม/แก้กิจกรรม ระบบแจ้งเตือน<b>ล่วงหน้า 1 วัน</b>ที่กระดิ่งของทุกคนที่มองเห็นกิจกรรมนั้น</p>

<x-manual.section anchor="view" title="ดูปฏิทิน">
    <ul>
        <li>เมนู <span class="ui">ปฏิทิน</span> แสดงทั้งเดือน เลื่อนเดือนด้วยปุ่ม <i class="bi bi-chevron-left"></i> <i class="bi bi-chevron-right"></i> กด <span class="ui">วันนี้</span> เพื่อกลับเดือนปัจจุบัน</li>
        <li>สีตามประเภท: <span class="badge bg-danger">วันหยุด</span> <span class="badge bg-warning text-dark">สอบ</span> <span class="badge bg-success">กิจกรรม</span> <span class="badge bg-info text-dark">ประชุม</span></li>
        <li>การ์ด <span class="ui">กิจกรรมที่จะถึง</span> แสดง 8 รายการถัดไป</li>
    </ul>
    <x-manual.go route="calendar">เปิดปฏิทิน</x-manual.go>
</x-manual.section>

<x-manual.section anchor="add" title="เพิ่ม / แก้ไขกิจกรรม (ผู้ดูแลระบบ)">
    <x-manual.steps>
        <li>กด <span class="ui">เพิ่มกิจกรรม</span></li>
        <li>ใส่ <b>ชื่อกิจกรรม</b> · <b>วันเริ่ม</b> · <b>วันสิ้นสุด</b> (กิจกรรมวันเดียวใส่วันเดียวกัน) · <b>ประเภท</b> · <b>รายละเอียด</b></li>
        <li><b>ใครเห็น:</b> <span class="ui">ทุกคน (รวมผู้ปกครอง)</span> หรือ <span class="ui">เฉพาะครู</span> (เช่น ประชุมครู — มีป้าย "ครู" กำกับ)</li>
        <li>กด <span class="ui">บันทึก</span></li>
        <li><b>ลบ / แก้ไข:</b> กด <i class="bi bi-x-lg"></i> ที่รายการในการ์ด <span class="ui">กิจกรรมที่จะถึง</span> เพื่อลบ ถ้าต้องการแก้ ให้ลบแล้วเพิ่มใหม่</li>
    </x-manual.steps>
    <x-manual.callout type="tip">ใส่ <b>วันหยุด</b> (เช่น วันหยุดนักขัตฤกษ์ ปิดภาคเรียน) ไว้ล่วงหน้าทั้งปี ครูจะเห็นชัดว่าวันไหนไม่ต้องเช็คชื่อ และผู้ปกครองรู้วันหยุดโดยไม่ต้องโทรถาม</x-manual.callout>
</x-manual.section>
