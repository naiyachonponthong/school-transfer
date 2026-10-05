<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;

class InvoiceInstallment extends Model
{
    public $timestamps = false;

    protected $fillable = ['invoice_id', 'seq', 'due_date', 'amount'];

    protected function casts(): array
    {
        return ['due_date' => DateOnly::class, 'amount' => 'decimal:2'];
    }
}
