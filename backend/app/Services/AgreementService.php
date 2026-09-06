<?php

namespace App\Services;

use App\Enums\AgreementStatus;
use App\Models\Agreement;
use App\Models\Favorite;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AgreementService
{
    public function __construct(
        private ActivityLogService $activityLog,
        private NotificationService $notifications,
        private VoteService $votes,
    ) {}

    public function create(User $user, array $data): Agreement
    {
        $signature = $user->activeSignature();
        if (! $signature) {
            throw ValidationException::withMessages(['signature' => 'Нет активной подписи.']);
        }

        $agreement = Agreement::query()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'author_id' => $user->id,
            'signature_id' => $signature->id,
            'project_id' => $data['project_id'] ?? null,
            'deadline' => $data['deadline'],
            'publish_date' => $data['publish_date'] ?? null,
            'status' => AgreementStatus::Draft,
        ]);

        $this->activityLog->log($user, 'agreement.created', $agreement);

        return $agreement->fresh(['sections', 'author', 'signature', 'project']);
    }

    public function update(Agreement $agreement, array $data, User $user): Agreement
    {
        if ($agreement->status !== AgreementStatus::Draft) {
            throw ValidationException::withMessages(['agreement' => 'Редактировать целиком можно только черновик.']);
        }

        $agreement->fill(array_intersect_key($data, array_flip([
            'title',
            'description',
            'project_id',
            'deadline',
            'publish_date',
        ])))->save();

        $this->activityLog->log($user, 'agreement.updated', $agreement, $data);

        return $agreement->fresh(['sections.participants.user', 'sections.blocks', 'author', 'signature']);
    }

    public function publish(Agreement $agreement, User $user): Agreement
    {
        if ($agreement->status !== AgreementStatus::Draft) {
            throw ValidationException::withMessages(['agreement' => 'Запустить можно только черновик.']);
        }

        if ($agreement->sections()->count() < 1) {
            throw ValidationException::withMessages(['sections' => 'Нужен хотя бы один раздел.']);
        }

        $agreement->update([
            'status' => AgreementStatus::Awaiting,
            'publish_date' => $agreement->publish_date && $agreement->publish_date->isFuture()
                ? $agreement->publish_date
                : now(),
        ]);

        if ($agreement->publish_date->isFuture()) {
            $agreement->update(['status' => AgreementStatus::Draft]);
        } else {
            $this->notifications->notifyAgreementPublished($agreement);
        }

        $this->activityLog->log($user, 'agreement.published', $agreement);

        return $agreement->fresh();
    }

    public function publishScheduled(): int
    {
        $count = 0;

        Agreement::query()
            ->where('status', AgreementStatus::Draft)
            ->whereNotNull('publish_date')
            ->where('publish_date', '<=', now())
            ->each(function (Agreement $agreement) use (&$count): void {
                $agreement->update(['status' => AgreementStatus::Awaiting]);
                $this->notifications->notifyAgreementPublished($agreement);
                Log::info('agreement.auto_published', [
                    'event' => 'agreement.auto_published',
                    'agreement_id' => $agreement->id,
                ]);
                $count++;
            });

        if ($count > 0) {
            Log::info('job.publish_scheduled', [
                'event' => 'job.publish_scheduled',
                'count' => $count,
            ]);
        }

        return $count;
    }

    public function archiveCompleted(): int
    {
        $count = Agreement::query()
            ->whereIn('status', [AgreementStatus::Completed, AgreementStatus::Expired])
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', now()->subWeeks(2))
            ->update(['status' => AgreementStatus::Archived]);

        if ($count > 0) {
            Log::info('job.archive_completed', [
                'event' => 'job.archive_completed',
                'count' => $count,
            ]);
        }

        return $count;
    }

    public function archive(Agreement $agreement, User $user): Agreement
    {
        $agreement->update(['status' => AgreementStatus::Archived]);
        $this->activityLog->log($user, 'agreement.archived', $agreement);

        return $agreement;
    }

    public function softDelete(Agreement $agreement, User $user): void
    {
        $agreement->update(['status' => AgreementStatus::Deleted]);
        $agreement->delete();
        $this->activityLog->log($user, 'agreement.deleted', $agreement);
    }

    public function restore(Agreement $agreement, User $user): Agreement
    {
        $agreement->restore();
        $agreement->update(['status' => AgreementStatus::Draft]);
        $this->activityLog->log($user, 'agreement.restored', $agreement);

        return $agreement;
    }

    public function restart(Agreement $agreement, User $user): Agreement
    {
        if (! in_array($agreement->status, [AgreementStatus::Completed, AgreementStatus::Expired, AgreementStatus::Archived], true)) {
            throw ValidationException::withMessages(['agreement' => 'Перезапуск доступен после завершения.']);
        }

        return DB::transaction(function () use ($agreement, $user) {
            foreach ($agreement->sections as $section) {
                $this->votes->resetSectionVotes($section, $user);
            }

            $agreement->update([
                'status' => AgreementStatus::Draft,
                'is_approved' => null,
                'completed_at' => null,
            ]);

            $this->activityLog->log($user, 'agreement.restarted', $agreement);

            return $agreement->fresh(['sections']);
        });
    }

    public function duplicate(Agreement $agreement, User $user): Agreement
    {
        return DB::transaction(function () use ($agreement, $user) {
            $copy = $agreement->replicate(['status', 'is_approved', 'completed_at', 'public_token', 'publish_date']);
            $copy->title = $agreement->title.' (копия)';
            $copy->author_id = $user->id;
            $copy->signature_id = $user->activeSignature()->id;
            $copy->status = AgreementStatus::Draft;
            $copy->is_approved = null;
            $copy->completed_at = null;
            $copy->publish_date = null;
            $copy->public_token = null;
            $copy->save();

            foreach ($agreement->sections as $section) {
                $sectionCopy = $section->replicate(['is_approved', 'approved_at']);
                $sectionCopy->agreement_id = $copy->id;
                $sectionCopy->is_approved = null;
                $sectionCopy->approved_at = null;
                $sectionCopy->save();

                foreach ($section->participants as $participant) {
                    $sectionCopy->participants()->create([
                        'user_id' => $participant->user_id,
                        'signature_id' => $participant->signature_id,
                    ]);
                }

                foreach ($section->blocks as $block) {
                    $blockCopy = $block->replicate();
                    $blockCopy->section_id = $sectionCopy->id;
                    $blockCopy->save();
                }
            }

            $this->activityLog->log($user, 'agreement.duplicated', $copy, ['from' => $agreement->id]);

            return $copy->fresh(['sections.blocks', 'sections.participants']);
        });
    }

    public function toggleFavorite(Agreement $agreement, User $user): bool
    {
        $existing = Favorite::query()
            ->where('user_id', $user->id)
            ->where('agreement_id', $agreement->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        Favorite::query()->create([
            'user_id' => $user->id,
            'agreement_id' => $agreement->id,
        ]);

        return true;
    }

    public function queryForUser(User $user, array $filters): LengthAwarePaginator
    {
        $query = Agreement::query()
            ->with(['author', 'signature', 'sections.participants', 'sections.votes'])
            ->withCount(['sections']);

        $group = $filters['group'] ?? 'incoming';

        match ($group) {
            'outgoing' => $query->where('author_id', $user->id)->where('status', '!=', AgreementStatus::Deleted),
            'favorites' => $query->whereHas('favorites', fn (Builder $q) => $q->where('user_id', $user->id)),
            'trash' => $query->onlyTrashed()->where('author_id', $user->id),
            'all' => $query->where(function (Builder $q) use ($user) {
                $q->where('author_id', $user->id)
                    ->orWhereHas('sections.participants', fn (Builder $p) => $p->where('user_id', $user->id));
            })->where('status', '!=', AgreementStatus::Deleted),
            'archive' => $query->where('status', AgreementStatus::Archived)
                ->where(function (Builder $q) use ($user) {
                    $q->where('author_id', $user->id)
                        ->orWhereHas('sections.participants', fn (Builder $p) => $p->where('user_id', $user->id));
                }),
            default => $query->whereHas('sections.participants', fn (Builder $p) => $p->where('user_id', $user->id))
                ->where('status', AgreementStatus::Awaiting),
        };

        if (! empty($filters['status']) && $group !== 'trash') {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['q'])) {
            $q = $filters['q'];
            $query->where(function (Builder $builder) use ($q) {
                $builder->where('title', 'ilike', "%{$q}%")
                    ->orWhere('description', 'ilike', "%{$q}%")
                    ->orWhereHas('author', fn (Builder $a) => $a->where('name', 'ilike', "%{$q}%"))
                    ->orWhereHas('sections', fn (Builder $s) => $s->where('name', 'ilike', "%{$q}%"));
            });
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        $sort = $filters['sort'] ?? 'newest';
        match ($sort) {
            'deadline' => $query->orderBy('deadline'),
            'activity' => $query->orderByDesc('updated_at'),
            default => $query->orderByDesc('created_at'),
        };

        return $query->paginate((int) ($filters['per_page'] ?? 20));
    }
}
