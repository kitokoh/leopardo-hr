<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'users';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password_hash',
        'google_id',
        'avatar_url',
        'provider',
        'preferred_language',
        'status',
        'email_verified_at',
        'last_login_at',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'failed_login_attempts' => 'integer',
    ];

    public function getAuthPassword(): string
    {
        return $this->password_hash ?? '';
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function companyRequests(): HasMany
    {
        return $this->hasMany(CompanyRequest::class, 'user_id');
    }

    public function employeeLinks(): HasMany
    {
        return $this->hasMany(UserEmployeeLink::class, 'user_id');
    }
}
