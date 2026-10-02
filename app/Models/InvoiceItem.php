<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['invoice_id', 'description', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'float'];
    }
}
