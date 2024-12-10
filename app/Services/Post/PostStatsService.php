<?php

namespace App\Services\Post;

use App\Constants\General\AppConstants;
use App\Jobs\PostStatsJob;
use App\Models\ContentEngagementUser;
use App\Models\Group;
use App\Models\Post;
use App\Models\PostStat;
use App\Models\User;
use App\Models\WebUser;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PostStatsService
{
    public function validate(array $data)
    {
        $validator = Validator::make($data, [
            "post_id" => "nullable|exists:posts,id",
            "group_id" => "nullable|exists:groups,id",
            "comments" => "nullable",
            "likes" => "nullable",
            "dislikes" => "nullable",
            "shares" => "nullable",
            "impressions" => "nullable",
            "engagements" => "nullable",
            "followers" => "nullable",
            "profile_visits" => "nullable",
            "clicks" => "nullable",
            "time_spent" => "nullable",
            "web_user_id" => "nullable",
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    public function dispatch(array $data, $remove = false)
    {
        try {
            $data = $this->validate($data);
            dispatch(new PostStatsJob($data, $remove))
                ->onQueue(AppConstants::STATS_QUEUE);
        } catch (\Throwable $th) {
            logger("Post stats job not running", [
                "error" => $th->getMessage(),
                "trace" => $th->getTrace()
            ]);
            // throw $th;
        }
    }

    public function create(array $data)
    {
        try {
            $data = $this->validate($data);

            $fields_to_update = ["comments", "likes", "dislikes", "shares", "impressions", "engagements", "followers", "profile_visits", "clicks"];

            $query = array_intersect_key($data, array_flip(["post_id", "group_id"]));

            $post_stat = PostStat::firstOrCreate($query);

            foreach ($fields_to_update as $field) {
                if (isset($data[$field]) && $data[$field] == true) {
                    $data[$field] = $post_stat->$field + 1;
                }
            }

            if (isset($data["time_spent"]) && $data["time_spent"] > $post_stat->max_time_spent) {
                $data["max_time_spent"] = $data["time_spent"];
            }

            if (isset($data["time_spent"]) && $data["time_spent"] < $post_stat->min_time_spent) {
                $data["min_time_spent"] = $data["time_spent"];
            }

            unset($data["time_spent"]);
            $post_stat->update($data);

            $user = auth("sanctum")->check() ? auth("sanctum")->user() : null;

            if ($user) {
                $this->createContentEngagementUser($post_stat, $user->id, User::class);
            } elseif (!empty($data["web_user_id"] ?? null)) {
                $this->createContentEngagementUser($post_stat, $data["web_user_id"], WebUser::class);
            }

            return $post_stat->refresh();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function remove(array $data)
    {
        try {
            $data = $this->validate($data);

            $fields_to_update = ["comments", "likes", "dislikes", "shares", "impressions", "engagements", "followers", "profile_visits", "clicks"];

            $query = array_intersect_key($data, array_flip(["post_id", "group_id"]));
            $query["user_id"] = auth()->check() ? auth()->id() : null;

            $post_stat = PostStat::firstOrCreate($query);

            foreach ($fields_to_update as $field) {
                if (isset($data[$field]) && $data[$field] == true) {
                    $data[$field] = $post_stat->$field - 1;
                }
            }

            if (isset($data["time_spent"]) && $data["time_spent"] > $post_stat->max_time_spent) {
                $data["max_time_spent"] = $data["time_spent"];
            }

            if (isset($data["time_spent"]) && $data["time_spent"] < $post_stat->min_time_spent) {
                $data["min_time_spent"] = $data["time_spent"];
            }

            unset($data["time_spent"]);
            $post_stat->update($data);
            return $post_stat->refresh();
        } catch (\Throwable $th) {
            throw $th;
        }
    }

    public function fetchStats(array $data)
    {
        $builder = PostStat::query();

        if (!empty($key = $data["post_id"] ?? null)) {
            $builder = $builder->where("post_id", $key);
        }

        if (!empty($key = $data["group_id"] ?? null)) {
            $builder = $builder->where("group_id", $key);
        }

        $stats = $builder->latest()->first();
        return $stats;
    }

    public function savePostImpressions(array $data, array $extras = [])
    {
        foreach ($data as $key => $post_id) {
            $data = $this->validate([
                "post_id" => $post_id,
                ...$extras
            ]);

            $this->dispatch($data);
        }
    }

    public function saveGroupImpressions(array $data, array $extras = [])
    {
        foreach ($data as $key => $group_id) {
            $data = $this->validate([
                "group_id" => $group_id,
                ...$extras
            ]);
            $this->dispatch($data);
        }
    }

    function createContentEngagementUser($post_stat, $user_id, $user_type)
    {
        if (!empty($post_id = $post_stat->post_id)) {
            ContentEngagementUser::firstOrCreate([
                "model_type" => Post::class,
                "model_id" => $post_id,
                "user_type" => $user_type,
                "user_id" => $user_id,
            ]);
        }

        if (!empty($group_id = $post_stat->group_id)) {
            ContentEngagementUser::firstOrCreate([
                "model_type" => Group::class,
                "model_id" => $group_id,
                "user_type" => $user_type,
                "user_id" => $user_id,
            ]);
        }
    }
}
