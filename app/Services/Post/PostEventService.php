<?php

namespace App\Services\Post;

use App\Constants\General\StatusConstants;
use App\Constants\Post\PostConstants;
use App\Events\RefreshNotification;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\TrendingTag;
use App\Notifications\Post\SchedulePostPublishedNotification;
use Illuminate\Support\Facades\Notification;

class PostEventService
{
    public static function publishScheduledPosts()
    {
        $posts = Post::status(StatusConstants::SCHEDULED)
            ->where("publish_at", "<=", now()->format("Y-m-d H:i:s"))
            ->get();

        foreach ($posts as $key => $post) {
            $post->update([
                "created_at" => now(),
                "status" => StatusConstants::ACTIVE
            ]);

            Notification::send($post->user, new SchedulePostPublishedNotification($post));
            broadcast(new RefreshNotification($post->user_id));
        }
    }

    public static function generalTrendingTags()
    {
        $stop_words = PostConstants::STOP_WORDS;
        $word_frequency = [];
    
        // Fetch posts for the current year and process in chunks
        Post::status()->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->chunk(1000, function ($posts) use (&$word_frequency, $stop_words) {
                foreach ($posts as $post) {
                    // Decode the tags into an array
                    $post_tags = is_string($post->tags) ? json_decode($post->tags, true) : $post->tags;
    
                    if (!is_array($post_tags) || empty($post_tags)) {
                        continue;
                    }
    
                    // Normalize tags and remove stop words
                    $filtered_tags = array_filter($post_tags, function ($tag) use ($stop_words) {
                        $tag = strtolower(trim($tag)); // Convert to lowercase and trim
                        return !empty($tag) && !in_array($tag, $stop_words);
                    });
    
                    // Count each tag's frequency
                    foreach ($filtered_tags as $tag) {
                        $word_frequency[$tag] = ($word_frequency[$tag] ?? 0) + 1;
                    }
                }
            });
    
        // Sort tags by frequency in descending order
        arsort($word_frequency);
    
        // Filter and limit to top trending tags
        $top_trending_tags = array_slice($word_frequency, 0, 10, true);
    
        // Save top trending tags into the database
        TrendingTag::whereNull("category_id")->delete(); // Clear previous entries
    
        foreach ($top_trending_tags as $tag => $count) {
            TrendingTag::create([
                'tag' => ucfirst(trim($tag)), // Capitalize the tag
                'count' => $count,
            ]);
        }
    }    

    public static function categoryTrendingTags()
    {
        $categories = PostCategory::status()->get();
        $stop_words = PostConstants::STOP_WORDS;
    
        foreach ($categories as $category) {
            $word_frequency = [];
    
            // Process posts for the current week within the category
            $category->posts()->status()->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->chunk(1000, function ($posts) use (&$word_frequency, $stop_words) {
                    foreach ($posts as $post) {
                        // Decode tags into an array
                        $post_tags = is_string($post->tags) ? json_decode($post->tags, true) : $post->tags;
    
                        if (!is_array($post_tags) || empty($post_tags)) {
                            continue;
                        }
    
                        // Normalize tags and remove stop words
                        $filtered_tags = array_filter($post_tags, function ($tag) use ($stop_words) {
                            $tag = strtolower(trim($tag)); // Convert to lowercase and trim
                            return !empty($tag) && !in_array($tag, $stop_words);
                        });
    
                        // Count each tag's frequency
                        foreach ($filtered_tags as $tag) {
                            $word_frequency[$tag] = ($word_frequency[$tag] ?? 0) + 1;
                        }
                    }
                });
    
            // Sort tags by frequency in descending order
            arsort($word_frequency);
    
            // Filter and limit to top trending tags
            $top_trending_tags = array_slice($word_frequency, 0, 10, true);
    
            // Save trending tags for the category
            $category->trendingTags()->delete(); // Clear previous trending tags
    
            foreach ($top_trending_tags as $tag => $count) {
                TrendingTag::create([
                    "category_id" => $category->id,
                    'tag' => ucfirst(trim($tag)), // Capitalize the tag
                    "count" => $count,
                ]);
            }
        }
    }
    
}
