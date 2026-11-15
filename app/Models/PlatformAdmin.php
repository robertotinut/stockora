<?php

namespace App\Models;

use App\Support\StockoraLocale;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PlatformAdmin extends Authenticatable implements HasLocalePreference
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'locale',
        'password',
        'is_active',
        'last_login_at',
    ];

    public function preferredLocale(): string
    {
        return StockoraLocale::normalize($this->locale) ?? StockoraLocale::defaultLocale();
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PlatformAuditLog::class, 'platform_admin_id');
    }
}
