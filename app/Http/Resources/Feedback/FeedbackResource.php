<?php

namespace App\Http\Resources\Feedback;

use App\Constants\General\AppConstants;
use Illuminate\Http\Resources\Json\JsonResource;

class FeedbackResource extends JsonResource
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
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'platform' => $this->platform,
            'content' => $this->content,
            'feedback_type' => $this->feedback_type,
            "attachments" => FeedbackAttachmentResource::collection($this->attachments),
            "created_at" => formatDate($this->created_at),
            "updated_at" => formatDate($this->updated_at)
        ];
    }
}
