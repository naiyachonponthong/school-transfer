<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/** หน้าดูประวัติการแก้ไข (ผู้ดูแลระบบ) — อ่านอย่างเดียว */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::query()
            ->when($request->query('group'), fn ($q, $g) => $q->where('action', 'like', $g.'.%'))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('description', 'like', "%{$t}%")->orWhere('user_name', 'like', "%{$t}%")))
            ->when($request->query('from'), fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($request->query('to'), fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->when($request->query('subject'), function ($q, $s) {
                [$type, $id] = array_pad(explode(':', $s, 2), 2, null);
                $q->where('subject_type', $type)->where('subject_id', (int) $id);
            })
            ->latest('id')->paginate(50)->withQueryString();

        return view('audit.index', compact('logs'));
    }
}
