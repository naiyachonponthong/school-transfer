<x-manual.section anchor="visit" title="บันทึกนักเรียนมาห้องพยาบาล">
    <x-manual.steps>
        <li>เมนู <span class="ui">ห้องพยาบาล</span> → ช่องนักเรียน พิมพ์ชื่อ/รหัส หรือ<b>สแกนบัตรนักเรียน</b></li>
        <li>กดปุ่มอาการที่พบบ่อย (ปวดหัว ปวดท้อง มีไข้ บาดแผล/หกล้ม ฯลฯ) หรือพิมพ์เอง ใส่อุณหภูมิ ยาที่ให้ และการดูแล</li>
        <li>เลือกผล: <b>พักที่ห้องพยาบาล · กลับเข้าเรียน · ผู้ปกครองรับกลับบ้าน · ส่งโรงพยาบาล</b></li>
        <li>กด <span class="ui">บันทึกและแจ้งผู้ปกครอง</span> — ผู้ปกครองได้ LINE ทันที</li>
    </x-manual.steps>
    <x-manual.go route="health.index">เปิดหน้าห้องพยาบาล</x-manual.go>
    <x-manual.callout type="warn">ก่อนให้ยา ตรวจ <b>โรคประจำตัว/ประวัติแพ้ยา</b> ในหน้าข้อมูลนักเรียน (การ์ดสุขภาพ) ทุกครั้ง</x-manual.callout>
</x-manual.section>

<x-manual.section anchor="measure" title="ชั่งน้ำหนัก / วัดส่วนสูงทั้งห้อง">
    <x-manual.steps>
        <li>หน้าห้องพยาบาล → <span class="ui">ชั่งน้ำหนัก / วัดส่วนสูง</span> เลือกห้องและวันที่วัด</li>
        <li>กรอกน้ำหนัก (กก.) และส่วนสูง (ซม.) ทีละคน กด <kbd>Enter</kbd> เลื่อนลงคนถัดไป</li>
        <li>กด <span class="ui">บันทึก</span> — ระบบคำนวณ BMI ให้ และแสดงในสมุดพกกับหน้าผู้ปกครอง</li>
    </x-manual.steps>
    <x-manual.go route="health.measure">เปิดหน้าชั่ง-วัด</x-manual.go>
</x-manual.section>
