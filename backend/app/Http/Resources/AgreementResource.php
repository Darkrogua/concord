<?php

namespace App\Http\Resources;

use App\Enums\AgreementStatus;
use App\Models\Agreement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Agreement */
class AgreementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAuthor = $user && $this->author_id === $user->id;
        $isGuest = ! $user;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status?->value ?? $this->status,
            'status_label' => $this->status instanceof AgreementStatus ? $this->status->label() : $this->status,
            'deadline' => $this->deadline,
            'publish_date' => $this->publish_date,
            'is_approved' => $this->is_approved,
            'completed_at' => $this->completed_at,
            'public_token' => $isAuthor ? $this->public_token : null,
            'author' => $this->whenLoaded('author', fn () => [
                'id' => $this->author->id,
                'name' => $this->author->name,
            ]),
            'signature' => $this->whenLoaded('signature'),
            'project' => $this->whenLoaded('project'),
            'sections' => SectionResource::collection($this->whenLoaded('sections')),
            'is_author' => $isAuthor,
            'is_guest' => $isGuest,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
