<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

class SectionPolicy
{
    public function view(User $user, Section $section): bool
    {
        return $user->can('view', $section->agreement);
    }

    public function update(User $user, Section $section): bool
    {
        return $section->agreement->author_id === $user->id;
    }

    public function vote(User $user, Section $section): bool
    {
        return $section->participants()->where('user_id', $user->id)->exists();
    }
}
