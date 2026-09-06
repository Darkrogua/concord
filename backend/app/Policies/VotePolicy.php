<?php

namespace App\Policies;

use App\Models\Section;
use App\Models\User;

class VotePolicy
{
    public function create(User $user, Section $section): bool
    {
        return $user->can('vote', $section);
    }
}
