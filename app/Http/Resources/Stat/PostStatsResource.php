<?php

namespace App\Http\Resources\Stat;

use App\Models\ContentEngagementUser;
use App\Models\Country;
use App\Models\Group;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

class PostStatsResource extends JsonResource
{
    public function __construct(public $resource, public $countries = null, public $show_countries_stats = true) {

    }
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */

    public function toArray($request)
    {
        $reaction_stats = $this->reactionStats();

        return [
            "id" => $this->id,
            "comments" => $reaction_stats["comments"],
            "likes" => $reaction_stats["likes"],
            "dislikes" => $reaction_stats["dislikes"],
            "shares" => $this->shares,
            "impressions" => $this->impressions,
            "engagements" => divideNumber($this->impressions, $reaction_stats["likes"]),
            "engagement_rates" => ($reaction_stats["comments"] + $reaction_stats["likes"] + $reaction_stats["dislikes"] + $this->shares) / 100,
            "followers" => $this->followers,
            "profile_visits" => $this->profile_visits,
            "clicks" => $this->clicks,
            "min_time_spent" => $this->min_time_spent,
            "max_time_spent" => $this->max_time_spent,
            "countries" => $this->show_countries_stats ? $this->countriesStats() : null,
            "created_at" => formatDate($this->created_at),
        ];
    }

    public function model(Model $model)
    {
        $reaction_stats = $this->reactionStats();
        return [
            "id" => $model->id,
            "comments" => $reaction_stats["comments"],
            "likes" => $reaction_stats["likes"],
            "dislikes" => $reaction_stats["dislikes"],
            "shares" => $model->shares,
            "impressions" => $model->impressions,
            "engagements" => divideNumber($model->impressions, $reaction_stats["likes"]),
            "engagement_rates" => ($reaction_stats["comments"] + $reaction_stats["likes"] + $reaction_stats["dislikes"] + $model->shares) / 100,
            "followers" => $model->followers,
            "profile_visits" => $model->profile_visits,
            "clicks" => $model->clicks,
            "min_time_spent" => $model->min_time_spent,
            "max_time_spent" => $model->max_time_spent,
            "created_at" => formatDate($model->created_at),
        ];
    }

    public function countriesStats()
    {
        $country_stats = [];

        $selected_country_id = !empty($this->countries) ? $this->countries->pluck("id")->toArray() : [];

        if ($post_id = $this->post_id) {
            $country_stats = $this->getCountryStats($post_id, Post::class, $selected_country_id);
        } elseif ($group_id = $this->group_id) {
            $country_stats = $this->getCountryStats($group_id, Group::class, $selected_country_id);
        }

        return $country_stats;
    }

    /**
     * Helper function to calculate engagement statistics based on a model type and ID.
     */
    private function getCountryStats($model_id, $model_type, $selected_country_id = [])
    {
        $country_stats = [];

        // Retrieve user IDs engaged with the specific model (post or group)
        $engagement_user_ids = ContentEngagementUser::where([
            "model_type" => $model_type,
            "model_id" => $model_id
        ])->pluck("user_id");

        // Get country IDs for these users
        $country_ids = User::whereIn("id", $engagement_user_ids)
            ->whereNotNull("country_id")
            ->pluck("country_id")
            ->toArray();

        // If no countries were selected, dynamically get all unique country IDs from engagement data
        if (empty($selected_country_id)) {
            $selected_country_id = array_unique($country_ids);
        }

        // Filter to include only selected countries and count occurrences
        $filtered_country_ids = array_filter($country_ids, fn($id) => in_array($id, $selected_country_id));
        $country_counts = array_count_values($filtered_country_ids);

        // Calculate total and selected engagement counts
        $total_engagements = count($country_ids);
        $selected_engagements = array_sum($country_counts);
        $others_count = $total_engagements - $selected_engagements;

        // Fetch country names for selected IDs and calculate engagement percentages
        $countries = Country::whereIn("id", $selected_country_id)->get();

        foreach ($countries as $country) {
            $country_id = $country->id;
            $engagement_count = $country_counts[$country_id] ?? 0;
            $percentage = $total_engagements ? ($engagement_count / $total_engagements) * 100 : 0;

            $country_stats[] = [
                'id' => $country_id,
                'name' => $country->name,
                'percentage' => round($percentage, 2) // Round to 2 decimal places
            ];
        }

        // Add "Others" category if there are users not in the selected countries
        if ($others_count > 0) {
            $others_percentage = ($others_count / $total_engagements) * 100;
            $country_stats[] = [
                'id' => null,
                'name' => 'Others',
                'percentage' => round($others_percentage, 2) // Round to 2 decimal places
            ];
        }

        return $country_stats;
    }
}
