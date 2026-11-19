<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** ข้อความจากฟอร์มติดต่อเราบนเว็บไซต์โรงเรียน */
class WebMessage extends Model
{
    protected $fillable = ['name', 'contact', 'subject', 'message', 'ip', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
