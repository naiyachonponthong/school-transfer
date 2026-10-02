<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SurveyItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['survey_id', 'sort', 'text', 'subscale', 'reverse'];

    protected function casts(): array
    {
        return ['reverse' => 'boolean'];
    }
}
