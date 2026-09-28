<?php

namespace App\Http\Resources\Article;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Journal article — full article view: card fields plus the author bio and the
 * ordered `body` block array. CamelCase to match the web mock contract.
 */
class ArticleDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            "slug" => $this->slug,
            "category" => $this->category,
            "tone" => $this->tone,
            "cover" => $this->cover,
            "title" => $this->title,
            "excerpt" => $this->excerpt,
            "author" => $this->author,
            "authorInitials" => $this->author_initials,
            "authorRole" => $this->author_role,
            "authorBio" => $this->author_bio,
            "readTime" => $this->read_time,
            "date" => $this->display_date,
            "body" => $this->body ?? [],
        ];
    }
}
