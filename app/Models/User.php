<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Services\RbacService;
use App\Services\TenantContext;
use App\Support\StockoraLocale;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function preferredLocale(): string
    {
        return StockoraLocale::normalize($this->locale) ?? StockoraLocale::defaultLocale();
    }

    /**
     * Dispatch Stockora's localized self-service password reset notification.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_user')
            ->withPivot(['role', 'is_default', 'is_active'])
            ->withTimestamps();
    }

    public function canAccessModule(string $module, string $action = 'view', ?int $companyId = null): bool
    {
        $resolvedCompanyId = $companyId ?? app(TenantContext::class)->id();

        return app(RbacService::class)->canAccessModule($this, $resolvedCompanyId, $module, $action);
    }

    public function roleInActiveCompany(?int $companyId = null): ?string
    {
        $resolvedCompanyId = $companyId ?? app(TenantContext::class)->id();
        if (!$resolvedCompanyId) {
            return null;
        }

        return app(RbacService::class)->roleFor($this, $resolvedCompanyId);
    }
}
