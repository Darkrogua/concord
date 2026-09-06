<?php

namespace App\Services;

use App\Models\AgreementGroup;
use App\Models\Signature;
use App\Models\User;

class UserOnboardingService
{
    public function setup(User $user): void
    {
        Signature::query()->create([
            'user_id' => $user->id,
            'name' => 'Основная',
            'is_active' => true,
        ]);

        $defaults = [
            ['name' => 'Входящие', 'slug' => 'incoming', 'filters' => ['group' => 'incoming']],
            ['name' => 'Исходящие', 'slug' => 'outgoing', 'filters' => ['group' => 'outgoing']],
            ['name' => 'Запущенные', 'slug' => 'awaiting', 'filters' => ['status' => 'awaiting']],
            ['name' => 'Все', 'slug' => 'all', 'filters' => ['group' => 'all']],
            ['name' => 'Избранное', 'slug' => 'favorites', 'filters' => ['group' => 'favorites']],
            ['name' => 'Корзина', 'slug' => 'trash', 'filters' => ['group' => 'trash']],
            ['name' => 'Завершенные', 'slug' => 'completed', 'filters' => ['status' => 'completed']],
        ];

        foreach ($defaults as $group) {
            AgreementGroup::query()->create([
                'user_id' => $user->id,
                'name' => $group['name'],
                'slug' => $group['slug'],
                'filters' => $group['filters'],
                'is_default' => true,
            ]);
        }
    }
}
