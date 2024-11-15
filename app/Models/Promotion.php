<?php

namespace App\Models;

use App\Constants\General\StatusConstants;
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

    public function getExpiresAtAttribute()
    {
        return carbon()->parse($this->created_at)->addDays($this->duration);
    }

    public function group()
    {
        return $this->belongsTo(Group::class, "group_id");
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, "payment_id");
    }

    public function scopeStatus($query, $status = StatusConstants::ACTIVE)
    {
        return $query->where("status", $status);
    }

    public function scopeSearch($query, $key)
    {
        return $query->where(function ($q) use ($key) {
            $q->whereHas("user", function ($user) use ($key) {
                $user->search($key);
            })->orWhereHas("post", function ($post) use ($key) {
                $post->search($key);
            })->orWhereHas("group", function ($group) use ($key) {
                $group->search($key);
            })->orWhereHas("payment", function ($payment) use ($key) {
                $payment->search($key);
            });
        });
    }

    public function scopeFilterByType($query, $type)
    {
        if ($type) {
            if ($type === 'Group') {
                return $query->whereNotNull('group_id');
            } elseif ($type === 'Post') {
                return $query->whereNotNull('post_id');
            }
        }
        return $query;
    }

    public function scopeFilterByStatus($query, $status)
    {
        if ($status) {
            return $query->where('status', $status);
        }
        return $query;
    }



    public function contentWebUrl()
    {
        $web_url = config("app.web_url");

        if (!empty($id = $this->post_id)) {
            $url = $web_url . "/comment/{$id}";
        }

        if (!empty($id = $this->group_id)) {
            $url = $web_url . "/group/{$this->group->uuid}/new";
        }

        return $url ?? null;
    }

    public function type()
    {
        if ($this->group_id) {
            return 'Group';  // Promotion is related to a group
        } elseif ($this->post_id) {
            return 'Post';   // Promotion is related to a post
        }
        return 'Unknown';  // Return a default value if neither group_id nor post_id exists
    }

    public function stat()
    {
        if ($this->post?->postStat) {
            return $this->post->postStat;
        }
        if ($this->group?->groupStat) {
            return $this->group->groupStat;
        }
    }

    public function statAttribute($attribute)
    {
        if ($this->post?->postStat) {
            return $this->post->postStat->$attribute;
        }
        if ($this->group?->groupStat) {
            return $this->group->groupStat->$attribute;
        }
        return null;  // return null if no related stat is found
    }

    public function getModelTypeAttribute()
    {
        if ($this->group_id) {
            return $this->group(); 
        } elseif ($this->post_id) {
            return $this->post();  
        }
        return null;
    }
}
