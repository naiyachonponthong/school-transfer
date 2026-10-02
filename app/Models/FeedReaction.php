<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeedReaction extends Model
{
    protected $fillable = ['feed_post_id', 'user_id', 'emoji'];
}
