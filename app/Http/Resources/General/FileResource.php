<?php

namespace App\Http\Resources\General;

use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "url" => $this->url(),
            "mime_type" => $this->mime_type,
            "size" => $this->size,
            "formatted_size" => $this->formatted_size,
            "created_at" => formatDate($this->created_at)
        ];
    }
}
