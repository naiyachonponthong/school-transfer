<p>ครูทุกคนแจ้งซ่อมได้จากมือถือพร้อมรูปถ่าย ผู้รับเรื่องคือ<b>ผู้ดูแลระบบ</b>และ<b>ครูงานพัสดุ/อาคารสถานที่</b>ที่ตั้งไว้ใน <span class="ui">ตั้งค่า → งานพัสดุ / อาคารสถานที่</span> ทุกการเปลี่ยนสถานะแจ้งผู้แจ้งทาง LINE</p>

<x-manual.section anchor="report" title="แจ้งซ่อม (ครูทุกคน)">
    <x-manual.steps>
        <li>เมนู <span class="ui">แจ้งซ่อม</span> → <span class="ui">แจ้งซ่อม</span></li>
        <li>ใส่ <b>อาการ / สิ่งที่เสีย</b> (เช่น แอร์ไม่เย็น มีน้ำหยด) · <b>สถานที่</b> (เช่น อาคาร 2 ห้อง 204) · รายละเอียดเพิ่มเติม</li>
        <li>เลือก <b>ความเร่งด่วน</b>: ปกติ / ด่วน · แนบ <b>รูปถ่ายจุดที่เสีย</b> (ถ่ายจากกล้องมือถือได้ทันที)</li>
        <li>กด <span class="ui">ส่งแจ้งซ่อม</span> ได้เลขที่งานซ่อม — ผู้รับเรื่องได้ LINE ทันที</li>
    </x-manual.steps>
    <x-manual.go route="repairs.create">แจ้งซ่อม</x-manual.go>
    <x-manual.callout type="tip"><b>แจ้งซ่อมครุภัณฑ์เร็วที่สุด:</b> สแกน QR บนสติกเกอร์ครุภัณฑ์ด้วยกล้องมือถือ → หน้าครุภัณฑ์ → <span class="ui">แจ้งซ่อม</span> ระบบกรอกชื่อครุภัณฑ์และสถานที่ให้ และเก็บประวัติซ่อมไว้ในทะเบียนครุภัณฑ์</x-manual.callout>
</x-manual.section>

<x-manual.section anchor="track" title="ติดตามสถานะ">
    <ul>
        <li>หน้าแจ้งซ่อมแสดงรายการที่คุณแจ้ง สถานะ: <span class="badge bg-secondary">รอรับเรื่อง</span> <span class="badge bg-warning text-dark">กำลังซ่อม</span> <span class="badge bg-info text-dark">ส่งซ่อมภายนอก</span> <span class="badge bg-success">ซ่อมเสร็จ</span> <span class="badge bg-danger">ซ่อมไม่ได้</span></li>
        <li>เปิดรายการเพื่อดู <span class="ui">ความคืบหน้า</span> (ใคร ทำอะไร เมื่อไร)</li>
        <li>แจ้งผิด/ซ่อมเองได้แล้ว: กด <span class="ui">ยกเลิกการแจ้งซ่อม</span> (ได้เฉพาะตอนที่ยัง "รอรับเรื่อง")</li>
    </ul>
</x-manual.section>

<x-manual.section anchor="manage" title="รับเรื่องและปิดงาน (งานพัสดุ / ผู้ดูแล)">
    <x-manual.steps>
        <li>หน้าแจ้งซ่อมแสดง<b>งานที่ยังไม่เสร็จ</b>ของทุกคน (งาน "ด่วน" มีป้ายแดง) ค้นหาด้วยเลขที่ อาการ สถานที่</li>
        <li>เปิดงาน → การ์ด <span class="ui">จัดการงานซ่อม</span> เปลี่ยน <b>สถานะ</b> เลือก <b>ผู้รับผิดชอบ / ช่าง</b> ใส่บันทึกความคืบหน้า</li>
        <li>เมื่อเสร็จ: สถานะ <b>ซ่อมเสร็จ</b> หรือ <b>ซ่อมไม่ได้</b> ใส่ <b>ค่าใช้จ่าย (บาท)</b> และ <b>ผลการซ่อม</b> → <span class="ui">บันทึก</span></li>
    </x-manual.steps>
    <ul>
        <li>งานที่ผูกกับครุภัณฑ์ — สถานะครุภัณฑ์เปลี่ยนตาม: กำลังซ่อม/ส่งซ่อมภายนอก → <b>ซ่อม</b> · ซ่อมเสร็จ → <b>ปกติ</b> · ซ่อมไม่ได้ → <b>ชำรุด</b></li>
        <li>การแก้ค่าใช้จ่ายถูกบันทึกใน <x-manual.link to="backup">ประวัติการแก้ไข</x-manual.link></li>
    </ul>
</x-manual.section>

<x-manual.section anchor="report-month" title="สรุปงานซ่อมรายเดือน">
    <p>หน้าแจ้งซ่อม → <span class="ui">สรุปรายเดือน</span> แสดงจำนวนแจ้งทั้งหมดแยกสถานะ ค่าใช้จ่ายรวม <b>เวลาเฉลี่ยจนซ่อมเสร็จ</b> และ<b>สถานที่ที่แจ้งบ่อย</b> กด <span class="ui">พิมพ์</span> แนบรายงานผู้บริหาร</p>
    <x-manual.go route="repairs.report" manager>เปิดสรุปรายเดือน</x-manual.go>
</x-manual.section>
