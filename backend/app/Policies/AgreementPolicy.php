<?php

namespace App\Policies;

use App\Models\Agreement;
use App\Models\User;

class AgreementPolicy
{
    public function view(User $user, Agreement $agreement): bool
    {
        if ($agreement->author_id === $user->id) {
            return true;
        }

        return $agreement->sections()->whereHas('participants', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->exists();
    }

    public function update(User $user, Agreement $agreement): bool
    {
        return $agreement->author_id === $user->id;
    }

    public function delete(User $user, Agreement $agreement): bool
    {
        return $agreement->author_id === $user->id;
    }

    public function publish(User $user, Agreement $agreement): bool
    {
        return $agreement->author_id === $user->id;
    }
}
