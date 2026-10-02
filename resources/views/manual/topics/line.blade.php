<x-manual.section anchor="why" title="ทำไมต้องเชื่อม LINE">
    <p>ระบบส่งแจ้งเตือนผ่าน <b>LINE Official Account ของโรงเรียน</b> เมื่อเชื่อมแล้วจะได้ข้อความเด้งบนมือถือทันที โดยไม่ต้องเปิดเว็บ และใช้ <x-manual.link to="start">ขอรหัสเมื่อลืมรหัสผ่าน</x-manual.link> ได้</p>
    <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>ผู้รับ</th><th>เรื่องที่แจ้ง</th></tr></thead>
        <tbody>
            <tr><td>ผู้ปกครอง</td><td>ลูกสแกนเข้า/ออกโรงเรียน · ขาด/สาย · มาโรงเรียนแต่ไม่เข้าคาบ · ผลใบลา · ไปห้องพยาบาล · คะแนนความประพฤติ · ใบแจ้งหนี้และผลตรวจสลิป · ประกาศ · ข้อความจากครู · หนังสือห้องสมุดเกินกำหนด</td></tr>
            <tr><td>ครู</td><td>ใบลาใหม่ · ข้อความจากผู้ปกครอง · ผลใบลาของตัวเอง · ผลการจอง/ใบเบิก/งานซ่อมที่แจ้งไว้ · กิจกรรมในปฏิทินล่วงหน้า 1 วัน</td></tr>
            <tr><td>งานพัสดุ / ผู้บริหาร</td><td>แจ้งซ่อมใหม่ · ขอจองที่ต้องอนุมัติ · ใบเบิกวัสดุใหม่ · ใบลาครู</td></tr>
        </tbody>
    </table>
</x-manual.section>

<x-manual.section anchor="link" title="วิธีเชื่อม LINE (ทุกคนทำเองได้)">
    <x-manual.steps>
        <li>เมนูรูปโปรไฟล์มุมขวาบน → <span class="ui">ข้อมูลส่วนตัว / รหัสผ่าน</span></li>
        <li>ที่การ์ด <span class="ui">รับแจ้งเตือนทาง LINE</span> กด <span class="ui">เชื่อม LINE</span> ระบบจะแสดง <b>รหัส 6 หลัก</b></li>
        <li>กด <span class="ui">เปิด LINE เพิ่มเพื่อน</span> เพื่อเพิ่มเพื่อน LINE ของโรงเรียน (หรือค้นหา ID ที่แสดงไว้)</li>
        <li>พิมพ์รหัส 6 หลักนั้นส่งในแชทของโรงเรียน</li>
        <li>LINE ตอบกลับว่าเชื่อมสำเร็จ หน้าโปรไฟล์จะขึ้น "เชื่อม LINE แล้ว"</li>
    </x-manual.steps>
    <x-manual.go route="profile">เปิดหน้าข้อมูลส่วนตัว</x-manual.go>
    <x-manual.callout type="tip">ยกเลิกรับแจ้งเตือนได้ทุกเมื่อ ที่การ์ดเดิม กด <span class="ui">ยกเลิก</span> · เปลี่ยนมือถือ/เปลี่ยนบัญชี LINE ให้ยกเลิกแล้วเชื่อมใหม่</x-manual.callout>
</x-manual.section>

<x-manual.section anchor="admin" title="สำหรับผู้ดูแลระบบ: ตั้งค่า LINE Official Account">
    <x-manual.steps>
        <li>สร้าง LINE Official Account ของโรงเรียน แล้วเปิดใช้ <b>Messaging API</b> ที่ LINE Developers Console</li>
        <li>คัดลอก <b>Channel access token</b> และ <b>Channel secret</b> มาใส่ที่ <span class="ui">ตั้งค่าโรงเรียน → LINE Official Account</span> พร้อม LINE ID ของ OA (เช่น @school)</li>
        <li>นำ <b>Webhook URL</b> ที่แสดงในหน้าตั้งค่า ไปใส่ใน LINE Developers แล้วเปิด <b>Use webhook</b> (ต้องเป็น https ที่เปิดจากอินเทอร์เน็ตได้)</li>
        <li>เลือกเรื่องที่จะแจ้งผู้ปกครอง (สแกนเข้า-ออก · ขาด/สาย) แล้วบันทึก</li>
        <li>ไปที่เมนู <span class="ui">LINE แจ้งเตือน</span> กด <span class="ui">ส่งทดสอบหาตัวเอง</span> (ต้องเชื่อม LINE ของตัวเองก่อน)</li>
    </x-manual.steps>
    <x-manual.go route="settings.messages" admin>เปิดหน้า LINE แจ้งเตือน</x-manual.go>
    <x-manual.callout type="note">หน้า <span class="ui">LINE แจ้งเตือน</span> บอกจำนวนผู้ปกครองที่เชื่อมแล้ว และประวัติการส่งทุกข้อความ ใช้ตรวจเมื่อมีคนบอกว่าไม่ได้รับ · บริการ LINE Notify ปิดไปแล้ว ระบบนี้ใช้ Messaging API แทน</x-manual.callout>
</x-manual.section>
