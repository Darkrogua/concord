<?php

namespace App\Services;

use App\Enums\AgreementStatus;
use App\Enums\BlockType;
use App\Enums\CompletionCondition;
use App\Models\Agreement;
use App\Models\InformationBlock;
use App\Models\Section;
use App\Models\SectionParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SectionService
{
    public function __construct(private VoteService $votes) {}

    public function create(Agreement $agreement, array $data): Section
    {
        $this->assertDraftOrAwaiting($agreement);

        $order = $data['order'] ?? ((int) $agreement->sections()->max('order') + 1);

        $section = $agreement->sections()->create([
            'name' => $data['name'],
            'order' => $order,
            'completion_condition' => $data['completion_condition'] ?? CompletionCondition::All,
            'show_results_before_vote' => $data['show_results_before_vote'] ?? false,
            'participants_see_each_other' => $data['participants_see_each_other'] ?? true,
        ]);

        Log::info('section.created', [
            'event' => 'section.created',
            'agreement_id' => $agreement->id,
            'section_id' => $section->id,
            'name' => $section->name,
        ]);

        return $section;
    }

    public function update(Section $section, array $data, User $actor, bool $resetVotes = true): Section
    {
        $agreement = $section->agreement;
        $this->assertDraftOrAwaiting($agreement);

        $section->fill(array_intersect_key($data, array_flip([
            'name',
            'order',
            'completion_condition',
            'show_results_before_vote',
            'participants_see_each_other',
        ])))->save();

        if ($agreement->status === AgreementStatus::Awaiting && $resetVotes) {
            $this->votes->resetSectionVotes($section, $actor);
        }

        return $section->fresh(['participants.user', 'blocks.files', 'votes']);
    }

    public function delete(Section $section): void
    {
        $this->assertDraft($section->agreement);
        $section->delete();
    }

    public function duplicate(Section $section): Section
    {
        return DB::transaction(function () use ($section) {
            $copy = $section->replicate(['is_approved', 'approved_at']);
            $copy->name = $section->name.' (копия)';
            $copy->order = (int) $section->agreement->sections()->max('order') + 1;
            $copy->is_approved = null;
            $copy->approved_at = null;
            $copy->save();

            foreach ($section->participants as $participant) {
                $copy->participants()->create([
                    'user_id' => $participant->user_id,
                    'signature_id' => $participant->signature_id,
                ]);
            }

            foreach ($section->blocks as $block) {
                $blockCopy = $block->replicate();
                $blockCopy->section_id = $copy->id;
                $blockCopy->save();
            }

            return $copy->fresh(['participants.user', 'blocks']);
        });
    }

    public function addParticipants(Section $section, array $items): void
    {
        foreach ($items as $item) {
            SectionParticipant::query()->firstOrCreate([
                'section_id' => $section->id,
                'user_id' => $item['user_id'],
                'signature_id' => $item['signature_id'],
            ]);
        }
    }

    public function removeParticipant(Section $section, int $userId): void
    {
        $this->assertDraftOrAwaiting($section->agreement);
        SectionParticipant::query()
            ->where('section_id', $section->id)
            ->where('user_id', $userId)
            ->delete();
    }

    public function createBlock(Section $section, array $data): InformationBlock
    {
        $order = $data['order'] ?? ((int) $section->blocks()->max('order') + 1);

        return $section->blocks()->create([
            'type' => $data['type'] ?? BlockType::Text,
            'title' => $data['title'] ?? null,
            'content' => $data['content'] ?? [],
            'order' => $order,
        ]);
    }

    public function updateBlock(InformationBlock $block, array $data, User $actor, bool $resetVotes = true): InformationBlock
    {
        $section = $block->section;
        $this->assertDraftOrAwaiting($section->agreement);

        $block->fill(array_intersect_key($data, array_flip(['type', 'title', 'content', 'order'])))->save();

        if ($section->agreement->status === AgreementStatus::Awaiting && $resetVotes) {
            $this->votes->resetSectionVotes($section, $actor);
        }

        return $block->fresh('files');
    }

    private function assertDraft(Agreement $agreement): void
    {
        if ($agreement->status !== AgreementStatus::Draft) {
            throw ValidationException::withMessages(['section' => 'Изменение доступно только в черновике.']);
        }
    }

    private function assertDraftOrAwaiting(Agreement $agreement): void
    {
        if (! in_array($agreement->status, [AgreementStatus::Draft, AgreementStatus::Awaiting], true)) {
            throw ValidationException::withMessages(['section' => 'Согласование нельзя менять в текущем статусе.']);
        }
    }
}
