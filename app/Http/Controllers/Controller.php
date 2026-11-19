<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** กลับหน้าเดิมแล้วเลื่อนลงไปที่หัวข้อที่กำลังแก้ในหน้าตั้งค่า (#workspace) ไม่ต้องเลื่อนผ่านแบนเนอร์ใหม่ */
    protected function toWorkspace(): \Illuminate\Http\RedirectResponse
    {
        $previous = url()->previous();

        return redirect()->to(str_contains($previous, '#') ? $previous : $previous.'#workspace');
    }
}
