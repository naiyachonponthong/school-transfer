<?php

namespace App\Http\Controllers;

use App\Support\Notifications;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /** หน้า "เมนูทั้งหมด" แบบตารางไอคอน */
    public function index()
    {
        return view('menu.index');
    }

    public function notifications(Request $request)
    {
        $user = $request->user();
        $items = Notifications::for($user);
        $user->forceFill(['notifications_seen_at' => now()])->save();

        return view('menu.notifications', compact('items'));
    }
}
