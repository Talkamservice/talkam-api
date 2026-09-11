<?php

namespace App\Http\Resources\Article;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Journal article — card shape (index grid, editor's pick, related). No `body`:
 * the list stays light and the article view fetches the full body by slug.
 *
 * Keys are camelCase to match the web mock contract one-to-one so the pages
 * need no field remapping.
 */
class ArticleResource extends JsonResource
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
            "readTime" => $this->read_time,
            "date" => $this->display_date,
        ];
    }
}
