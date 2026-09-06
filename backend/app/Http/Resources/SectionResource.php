<?php

namespace App\Http\Resources;

use App\Models\Section;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Section */
class SectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAuthor = $user && $this->agreement && $this->agreement->author_id === $user->id;
        $ownVote = $user
            ? $this->votes->firstWhere('user_id', $user->id)
            : null;
        $canVote = $user && $this->participants->contains('user_id', $user->id) && ! $ownVote;
        $showOthers = $isAuthor
            || ($this->participants_see_each_other && ($this->show_results_before_vote || $ownVote));

        $votes = $this->whenLoaded('votes', function () use ($isAuthor, $showOthers, $user) {
            return $this->votes->map(function (Vote $vote) use ($isAuthor, $showOthers, $user) {
                if (! $showOthers && $vote->user_id !== $user?->id) {
                    return null;
                }

                return [
                    'id' => $vote->id,
                    'user' => $vote->relationLoaded('user') ? [
                        'id' => $vote->user->id,
                        'name' => $vote->user->name,
                    ] : ['id' => $vote->user_id],
                    'vote' => $vote->vote?->value ?? $vote->vote,
                    'comment' => $isAuthor ? $vote->comment : null,
                    'is_own' => $user && $vote->user_id === $user->id,
                    'created_at' => $vote->created_at,
                ];
            })->filter()->values();
        });

        return [
            'id' => $this->id,
            'name' => $this->name,
            'order' => $this->order,
            'completion_condition' => $this->completion_condition,
            'show_results_before_vote' => $this->show_results_before_vote,
            'participants_see_each_other' => $this->participants_see_each_other,
            'is_approved' => $this->is_approved,
            'progress' => [
                'yes' => $this->votes->where('vote', 'yes')->count() + $this->votes->where('vote', \App\Enums\VoteChoice::Yes)->count() > 0
                    ? $this->votes->filter(fn ($v) => ($v->vote?->value ?? $v->vote) === 'yes')->count()
                    : 0,
                'total' => $this->participants->count(),
            ],
            'can_vote' => (bool) $canVote,
            'own_vote' => $ownVote ? ($ownVote->vote?->value ?? $ownVote->vote) : null,
            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(fn ($p) => [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'signature_id' => $p->signature_id,
                'user' => $p->relationLoaded('user') ? [
                    'id' => $p->user->id,
                    'name' => $p->user->name,
                    'email' => $p->user->email,
                ] : null,
            ])),
            'blocks' => $this->whenLoaded('blocks', fn () => $this->blocks->map(fn ($block) => [
                'id' => $block->id,
                'type' => $block->type?->value ?? $block->type,
                'title' => $block->title,
                'content' => $block->content,
                'order' => $block->order,
                'files' => $block->relationLoaded('files') ? $block->files : [],
            ])),
            'votes' => $votes,
        ];
    }
}
