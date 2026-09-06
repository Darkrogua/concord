<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'is_admin', 'theme'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_admin;
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(Signature::class);
    }

    public function activeSignature(): ?Signature
    {
        return $this->signatures()->where('is_active', true)->first()
            ?? $this->signatures()->first();
    }

    public function authoredAgreements(): HasMany
    {
        return $this->hasMany(Agreement::class, 'author_id');
    }

    public function userGroups(): HasMany
    {
        return $this->hasMany(UserGroup::class);
    }

    public function agreementGroups(): HasMany
    {
        return $this->hasMany(AgreementGroup::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function notificationsFeed(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }
}
