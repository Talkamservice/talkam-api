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

        Post::status()->whereBetween('created_at', [now()->subDays(20)->toDateTimeString(), now()->toDateTimeString()])
            ->chunk(1000, function ($posts) use (&$word_frequency, $stop_words) {
                foreach ($posts as $post) {

                    $content = implode(" ", filterUniqueWords($post->tags));

                    // $content = $post->title . ' ' . $post->body . " " . implode(" ", $post->tags);

                    // Tokenize the content into words
                    $words = preg_split('/[\s,]+/', $content);

                    // Remove stop words and punctuation, and convert to lowercase
                    $filtered_words = array_filter($words, function ($word) use ($stop_words) {
                        $word = strtolower($word);
                        $word = preg_replace('/[^\w\s]/', '', $word);
                        return !in_array($word, $stop_words) && !empty($word);
                    });

                    // Generate unigrams, bigrams, and trigrams
                    $phrases = [];
                    $count = count($filtered_words);
                    for ($i = 0; $i < $count; $i++) {
                        $phrases[] = $filtered_words[$i] ?? null; // unigram
                        if ($i + 1 < $count) {
                            $phrases[] = ($filtered_words[$i] ?? null) . ' ' . ($filtered_words[$i + 1] ?? null); // bigram
                        }
                        if ($i + 2 < $count) {
                            $phrases[] = ($filtered_words[$i] ?? null) . ' ' . ($filtered_words[$i + 1] ?? null) . ' ' . ($filtered_words[$i + 2] ?? null); // trigram
                        }
                    }

                    // Count the frequency of each phrase
                    foreach ($phrases as $phrase) {
                        if (isset($word_frequency[$phrase])) {
                            $word_frequency[$phrase]++;
                        } else {
                            $word_frequency[$phrase] = 1;
                        }
                    }
                }
            });

        // Sort the array by frequency
        arsort($word_frequency);

        // Get the top trending words (e.g., top 10)
        $top_trending_words = array_slice($word_frequency, 0, 10, true);

        $filtered_array = array_filter($top_trending_words, function ($key) {
            $trimmedKey = trim($key);
            return !empty($trimmedKey) && strlen($trimmedKey) > 3;
        }, ARRAY_FILTER_USE_KEY);

        TrendingTag::whereNull("category_id")->delete();
        foreach (ensureUniqueKeys($filtered_array) as $word => $count) {
            TrendingTag::create([
                'tag' => trim(ucwords($word)),
                'count' => $count,
            ]);
        }
    }

    public static function categoryTrendingTags()
    {
        $categories = PostCategory::status()->get();
        $stop_words = PostConstants::STOP_WORDS;
        $word_frequency = [];

        foreach ($categories as $key => $category) {
            $category->posts()->status()->whereBetween("created_at", [now()->subDays(20)->toDateTimeString(), now()->toDateTimeString()])
                ->chunk(1000, function ($posts) use (&$word_frequency, $stop_words, $category) {
                    foreach ($posts as $post) {

                        $content = implode(" ", filterUniqueWords($post->tags));
                        // $content = $post->title . ' ' . $post->body . " " . implode(" ", $post->tags);

                        // Tokenize the content into words
                        $words = preg_split('/[\s,]+/', $content);

                        // Remove stop words and punctuation, and convert to lowercase
                        $filtered_words = array_filter($words, function ($word) use ($stop_words) {
                            $word = strtolower($word);
                            $word = preg_replace('/[^\w\s]/', '', $word);
                            return !in_array($word, $stop_words) && !empty($word);
                        });

                        // Generate unigrams, bigrams, and trigrams
                        $phrases = [];
                        $count = count($filtered_words);
                        for ($i = 0; $i < $count; $i++) {
                            $phrases[] = $filtered_words[$i] ?? null; // unigram
                            if ($i + 1 < $count) {
                                $phrases[] = ($filtered_words[$i] ?? null) . ' ' . ($filtered_words[$i + 1] ?? null); // bigram
                            }
                            if ($i + 2 < $count) {
                                $phrases[] = ($filtered_words[$i] ?? null) . ' ' . ($filtered_words[$i + 1] ?? null) . ' ' . ($filtered_words[$i + 2] ?? null); // trigram
                            }
                        }

                        // Count the frequency of each phrase
                        foreach ($phrases as $phrase) {
                            if (isset($word_frequency[$phrase])) {
                                $word_frequency[$phrase]++;
                            } else {
                                $word_frequency[$phrase] = 1;
                            }
                        }
                    }

                    // Sort the array by frequency
                    arsort($word_frequency);

                    // Get the top trending words (e.g., top 10)
                    $top_trending_words = array_slice($word_frequency, 0, 10, true);


                    $filtered_array = array_filter($top_trending_words, function ($key) {
                        $trimmedKey = trim($key);
                        return !empty($trimmedKey) && strlen($trimmedKey) > 3;
                    }, ARRAY_FILTER_USE_KEY);

                    $category->trendingTags()->delete();
                    foreach (ensureUniqueKeys($filtered_array) as $word => $count) {
                        TrendingTag::create([
                            "category_id" => $category->id,
                            'tag' => trim(ucwords($word)),
                            "count" => $count,
                        ]);
                    }
                });
        }
    }
}
