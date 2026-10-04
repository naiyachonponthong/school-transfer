<p>ส่งหนังสือขออนุญาตถึงผู้ปกครองทั้งห้องแทนกระดาษ เช่น ทัศนศึกษา เข้าค่าย ฉีดวัคซีน ผู้ปกครองกดอนุญาตหรือไม่อนุญาตจากมือถือ</p>

<x-manual.section anchor="send" title="ครูส่งหนังสือ">
    <x-manual.steps>
        <li>เปิดเมนู <span class="ui">ขออนุญาตผู้ปกครอง</span> กรอกเรื่อง รายละเอียด เลือกห้อง และวันครบกำหนดตอบ (ถ้ามี)</li>
        <li>กดส่ง ผู้ปกครองได้รับแจ้งเตือนทาง LINE / บนอุปกรณ์ และขึ้นที่กระดิ่ง นักเรียนก็เห็นหนังสือด้วย</li>
        <li>เปิดหนังสือเพื่อดูว่าใครอนุญาต ไม่อนุญาต หรือยังไม่ตอบ</li>
    </x-manual.steps>
    <x-manual.go route="consents.index">เปิดหน้าหนังสือขออนุญาต</x-manual.go>
    <ul>
        <li>ครูส่งได้เฉพาะห้องที่ตัวเองดูแล</li>
        <li>ผู้ปกครองที่ตอบเป็นกระดาษหรือโทรมา ครู<b>บันทึกคำตอบแทน</b>ได้</li>
        <li>กด <span class="ui">ปิดรับคำตอบ</span> เมื่อครบกำหนด หลังจากนั้นผู้ปกครองแก้คำตอบไม่ได้</li>
    </ul>
</x-manual.section>

<x-manual.section anchor="parent" title="ผู้ปกครองตอบ">
    <x-manual.steps>
        <li>เมนู <span class="ui">หนังสือขออนุญาต</span> อ่านรายละเอียด</li>
        <li>กด <span class="ui">อนุญาต</span> หรือ <span class="ui">ไม่อนุญาต</span> ที่ชื่อบุตรหลานแต่ละคน</li>
    </x-manual.steps>
    <x-manual.go route="parent.consents">เปิดหนังสือขออนุญาต</x-manual.go>
    <p>แก้คำตอบได้จนกว่าครูจะปิดรับคำตอบ</p>
</x-manual.section>

<x-manual.section anchor="student" title="นักเรียน">
    <p>นักเรียนอ่านหนังสือและเห็นว่าผู้ปกครองตอบแล้วหรือยังได้ที่เมนู <span class="ui">หนังสือขออนุญาต</span> แต่ตอบแทนผู้ปกครองไม่ได้</p>
    <x-manual.go route="student.consents">เปิดหนังสือขออนุญาต</x-manual.go>
</x-manual.section>
