<?php

namespace App\Services;

use App\Enums\AgreementStatus;
use App\Enums\CompletionCondition;
use App\Enums\VoteChoice;
use App\Models\Agreement;
use App\Models\Section;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class VoteService
{
    public function __construct(
        private ActivityLogService $activityLog,
        private NotificationService $notifications,
    ) {}

    public function submit(Section $section, User $user, VoteChoice $choice, ?string $comment = null): Vote
    {
        $section->loadMissing('agreement', 'participants');
        $agreement = $section->agreement;

        if ($agreement->status !== AgreementStatus::Awaiting) {
            throw ValidationException::withMessages(['vote' => 'Голосование недоступно.']);
        }

        if ($agreement->deadline->isPast()) {
            throw ValidationException::withMessages(['vote' => 'Дедлайн истёк.']);
        }

        $signature = $user->activeSignature();
        $isParticipant = $section->participants
            ->contains(fn ($p) => $p->user_id === $user->id && $p->signature_id === $signature?->id)
            || $section->participants->contains('user_id', $user->id);

        if (! $isParticipant) {
            throw ValidationException::withMessages(['vote' => 'Вы не участник этого раздела.']);
        }

        $existing = Vote::query()
            ->where('section_id', $section->id)
            ->where('user_id', $user->id)
            ->where('signature_id', $signature->id)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages(['vote' => 'Голос уже зафиксирован.']);
        }

        if ($choice === VoteChoice::No && blank($comment)) {
            throw ValidationException::withMessages(['comment' => 'Укажите причину отказа.']);
        }

        if ($comment && mb_strlen($comment) > 500) {
            throw ValidationException::withMessages(['comment' => 'Комментарий не длиннее 500 символов.']);
        }

        return DB::transaction(function () use ($section, $user, $signature, $choice, $comment) {
            $vote = Vote::query()->create([
                'section_id' => $section->id,
                'user_id' => $user->id,
                'signature_id' => $signature->id,
                'vote' => $choice,
                'comment' => $choice === VoteChoice::No ? $comment : null,
            ]);

            $this->activityLog->log($user, 'vote.submitted', $vote, [
                'section_id' => $section->id,
                'vote' => $choice->value,
            ]);

            Log::info('vote.submitted', [
                'event' => 'vote.submitted',
                'section_id' => $section->id,
                'agreement_id' => $section->agreement_id,
                'vote' => $choice->value,
                'user_id' => $user->id,
            ]);

            $this->refreshSection($section->fresh(['participants', 'votes', 'agreement.sections.participants', 'agreement.sections.votes']));

            return $vote;
        });
    }

    public function resetSectionVotes(Section $section, User $actor): void
    {
        Vote::query()->where('section_id', $section->id)->delete();
        $section->update([
            'is_approved' => null,
            'approved_at' => null,
        ]);

        $this->activityLog->log($actor, 'section.votes_reset', $section);
        $this->notifications->notifySectionUpdated($section->fresh(['participants.user', 'agreement']));
    }

    public function refreshSection(Section $section): void
    {
        $section->loadMissing('participants', 'votes', 'agreement');
        $participantCount = $section->participants->count();

        if ($participantCount === 0) {
            return;
        }

        $yesCount = $section->votes->where('vote', VoteChoice::Yes)->count();
        $allVoted = $section->votes->count() >= $participantCount;
        $unanimous = $yesCount === $participantCount;

        if ($section->completion_condition === CompletionCondition::All && $unanimous) {
            $section->update([
                'is_approved' => true,
                'approved_at' => now(),
            ]);
        } elseif ($allVoted && ! $unanimous) {
            $section->update([
                'is_approved' => false,
                'approved_at' => now(),
            ]);
        }

        $this->refreshAgreement($section->agreement->fresh(['sections']));
    }

    public function refreshAgreement(Agreement $agreement): void
    {
        if (! in_array($agreement->status, [AgreementStatus::Awaiting], true)) {
            return;
        }

        $sections = $agreement->sections;
        if ($sections->isEmpty()) {
            return;
        }

        $allApproved = $sections->every(fn (Section $section) => $section->is_approved === true);

        if ($allApproved) {
            $agreement->update([
                'status' => AgreementStatus::Completed,
                'is_approved' => true,
                'completed_at' => now(),
            ]);
            Log::info('agreement.completed', [
                'event' => 'agreement.completed',
                'agreement_id' => $agreement->id,
                'is_approved' => true,
            ]);
        }
    }

    public function expireIfNeeded(Agreement $agreement): void
    {
        if ($agreement->status !== AgreementStatus::Awaiting) {
            return;
        }

        if ($agreement->deadline->isFuture()) {
            return;
        }

        $allApproved = $agreement->sections->every(fn (Section $section) => $section->is_approved === true);

        $agreement->update([
            'status' => $allApproved ? AgreementStatus::Completed : AgreementStatus::Expired,
            'is_approved' => $allApproved,
            'completed_at' => now(),
        ]);

        Log::info($allApproved ? 'agreement.completed' : 'agreement.expired', [
            'event' => $allApproved ? 'agreement.completed' : 'agreement.expired',
            'agreement_id' => $agreement->id,
            'is_approved' => $allApproved,
        ]);
    }
}
