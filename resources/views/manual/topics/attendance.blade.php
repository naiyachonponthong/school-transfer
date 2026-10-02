<x-manual.section anchor="daily" title="เช็คชื่อประจำวัน (ครูประจำชั้น)">
    <x-manual.steps>
        <li>เมนู <span class="ui">เช็คชื่อ</span> (หรือปุ่ม <span class="ui">เช็คชื่อวันนี้</span> ที่หน้าแรก) เลือก <b>ห้องเรียน</b> และ <b>วันที่</b> (ค่าเริ่มต้นวันนี้ · ปุ่มลูกศรเลื่อนไปวันทำการก่อน/ถัดไป)</li>
        <li>เลือกสถานะของนักเรียนที่ <b>ไม่ได้มาปกติ</b> ก่อน: <span class="ui">สาย</span> <span class="ui">ขาด</span> <span class="ui">ลากิจ</span> <span class="ui">ลาป่วย</span></li>
        <li>กด <span class="ui">คนที่เหลือ = มา</span> ระบบติ๊ก "มา" ให้ทุกคนที่ยังไม่ได้เลือก (หรือ <span class="ui">ทุกคนมา</span> ถ้ามาครบทั้งห้อง)</li>
        <li>กด <span class="ui">บันทึกการเช็คชื่อ</span></li>
    </x-manual.steps>
    <x-manual.go route="attendance.index">เปิดหน้าเช็คชื่อ</x-manual.go>
    <h3>คีย์ลัด (คอมพิวเตอร์)</h3>
    <table class="table table-sm table-bordered" style="max-width:420px">
        <tbody>
            <tr><td><kbd>1</kbd></td><td>มา</td><td><kbd>4</kbd></td><td>ลากิจ</td></tr>
            <tr><td><kbd>2</kbd></td><td>สาย</td><td><kbd>5</kbd></td><td>ลาป่วย</td></tr>
            <tr><td><kbd>3</kbd></td><td>ขาด</td><td><kbd>↑</kbd> <kbd>↓</kbd></td><td>เลื่อนคน</td></tr>
        </tbody>
    </table>
    <p>กดตัวเลขแล้วระบบเลื่อนไปคนถัดไปให้เอง เช็คทั้งห้องได้โดยไม่ต้องใช้เมาส์</p>
</x-manual.section>

<x-manual.section anchor="auto" title="สิ่งที่ระบบทำให้อัตโนมัติ">
    <ul>
        <li><b>ใบลาที่อนุมัติแล้ว</b> ถูกลงเป็น "ลากิจ/ลาป่วย" ให้เอง ไม่ต้องเลือกซ้ำ · ถ้ามี <b>ใบลารออนุมัติ</b> ของวันนั้น จะมีป้ายเตือนข้างชื่อ ให้ไป <x-manual.link to="leaves">พิจารณาใบลา</x-manual.link></li>
        <li><b>สแกนหน้าประตู</b> แล้ว ระบบลง "มา/สาย" ให้ตามเวลาที่สแกน — <x-manual.link to="gate" /></li>
        <li><b>แจ้งผู้ปกครองทาง LINE</b> เมื่อบันทึกว่าขาดหรือสาย (เฉพาะวันนี้ และเมื่อผู้ดูแลเปิดการแจ้งเตือนไว้)</li>
        <li>เช็คชื่อหน้าเสาธงถูกใช้ต่อใน <x-manual.link to="period">เช็คชื่อรายคาบ</x-manual.link> (คนขาด/ลาทั้งวันถูกดึงไปให้)</li>
    </ul>
    <x-manual.callout type="tip">แก้การเช็คชื่อย้อนหลังได้ เลือกวันที่เดิมแล้วบันทึกใหม่ · บนมือถือ ปุ่มสถานะใหญ่กดง่าย ใช้เช็คชื่อหน้าเสาธงได้เลย</x-manual.callout>
</x-manual.section>

<x-manual.section anchor="today" title="สรุปการมาเรียนทั้งโรงเรียน (ผู้บริหาร)">
    <p>เมนู <span class="ui">สรุปวันนี้</span> แสดงจำนวน มา/สาย/ขาด/ลา <b>รายห้อง</b> และรายชื่อนักเรียนที่ไม่ได้มาปกติ ห้องที่ยังไม่เช็คชื่อจะเห็นชัด ครูประจำชั้นห้องนั้นจะเห็นการแจ้งเตือนที่กระดิ่งด้วย</p>
    <x-manual.go route="attendance.today">เปิดสรุปวันนี้</x-manual.go>
</x-manual.section>

<x-manual.section anchor="report" title="รายงานเวลาเรียนรายเดือน">
    <x-manual.steps>
        <li>เมนู <span class="ui">รายงาน / ปพ.</span> → <span class="ui">รายงานเวลาเรียนรายเดือน</span></li>
        <li>เลือกห้องและเดือน จะได้ตาราง มา/ขาด/ลา/สาย รายวัน พร้อมรวมแต่ละคน</li>
        <li>กด <span class="ui">พิมพ์</span> หรือ <span class="ui">ส่งออก Excel</span></li>
    </x-manual.steps>
    <x-manual.go route="attendance.report">เปิดรายงานรายเดือน</x-manual.go>
</x-manual.section>
