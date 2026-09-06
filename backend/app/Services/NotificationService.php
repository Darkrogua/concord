<?php

namespace App\Services;

use App\Mail\GenericNotificationMail;
use App\Models\Agreement;
use App\Models\Section;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function notify(User $user, string $type, string $title, string $message, array $data = []): UserNotification
    {
        $notification = UserNotification::query()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
        ]);

        Mail::to($user->email)->queue(new GenericNotificationMail($title, $message));

        Log::info('notification.sent', [
            'event' => 'notification.sent',
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'data' => $data,
        ]);

        return $notification;
    }

    public function notifyAgreementPublished(Agreement $agreement): void
    {
        $agreement->loadMissing('sections.participants.user');

        $users = $agreement->sections
            ->flatMap(fn (Section $section) => $section->participants->pluck('user'))
            ->unique('id')
            ->filter();

        foreach ($users as $user) {
            $this->notify(
                $user,
                'agreement_published',
                'Новое согласование',
                "Вас пригласили согласовать «{$agreement->title}».",
                ['agreement_id' => $agreement->id],
            );
        }
    }

    public function notifySectionUpdated(Section $section): void
    {
        $section->loadMissing('participants.user', 'agreement');

        foreach ($section->participants as $participant) {
            if (! $participant->user) {
                continue;
            }

            $this->notify(
                $participant->user,
                'section_updated',
                'Нужно проголосовать повторно',
                "Раздел «{$section->name}» в «{$section->agreement->title}» изменён, голоса сброшены.",
                ['agreement_id' => $section->agreement_id, 'section_id' => $section->id],
            );
        }
    }

    public function notifyDeadlineApproaching(Agreement $agreement): void
    {
        $agreement->loadMissing('sections.participants.user');

        $users = $agreement->sections
            ->flatMap(fn (Section $section) => $section->participants->pluck('user'))
            ->unique('id')
            ->filter();

        foreach ($users as $user) {
            $this->notify(
                $user,
                'deadline_reminder',
                'Приближается дедлайн',
                "Срок согласования «{$agreement->title}» скоро истечёт.",
                ['agreement_id' => $agreement->id],
            );
        }
    }
}
