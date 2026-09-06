<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\UserOnboardingService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $onboarding = app(UserOnboardingService::class);

        $admin = User::query()->create([
            'name' => 'Администратор',
            'email' => 'admin@concord.local',
            'password' => 'password',
            'is_admin' => true,
        ]);
        $onboarding->setup($admin);

        $author = User::query()->create([
            'name' => 'Автор',
            'email' => 'author@concord.local',
            'password' => 'password',
        ]);
        $onboarding->setup($author);

        $approver = User::query()->create([
            'name' => 'Согласователь',
            'email' => 'approver@concord.local',
            'password' => 'password',
        ]);
        $onboarding->setup($approver);
    }
}
