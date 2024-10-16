<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, "user_id");
    }

    public function post()
    {
        return $this->belongsTo(Post::class, "post_id");
    }

    public function group()
    {
        return $this->belongsTo(Group::class, "group_id");
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, "payment_id");
    }
}
