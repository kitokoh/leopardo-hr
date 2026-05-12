<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $password_hash
 * @property string|null $google_id
 * @property string|null $avatar_url
 * @property string $provider
 * @property string $preferred_language
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property int $failed_login_attempts
 * @property \Illuminate\Support\Carbon|null $locked_until
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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

    /** @return HasMany<CompanyRequest, $this> */
    public function companyRequests(): HasMany
    {
        return $this->hasMany(CompanyRequest::class, 'user_id');
    }

    /** @return HasMany<UserEmployeeLink, $this> */
    public function employeeLinks(): HasMany
    {
        return $this->hasMany(UserEmployeeLink::class, 'user_id');
    }
}
