<?php

declare(strict_types=1);

namespace App\Core\Auth\Domain\Models;

use App\Core\Tenant\Domain\Models\CompanyRequest;
use App\Modules\HR\Domain\Models\UserEmployeeLink;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Contracts\HasApiTokens as HasApiTokensContract;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string|null $phone_e164 Numero normalise E.164 (#8736) — identifiant de premier rang, unique quand renseigne
 * @property string|null $password_hash
 * @property string|null $google_id
 * @property string|null $avatar_url
 * @property string $provider
 * @property string $preferred_language
 * @property string $status
 * @property list<string> $personal_statuses Statuts cumulables : student, employee, entrepreneur, seeking_employment
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property Carbon|null $phone_verified_at Date de verification du telephone par OTP (M2 #9104)
 * @property Carbon|null $deletion_requested_at Suppression differee demandee (M2 #9104)
 * @property Carbon|null $purge_scheduled_at Purge planifiee a 30 jours (M2 #9104)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static static|null find(mixed $id, array<int, string> $columns = ['*'])
 * @method static static findOrFail(mixed $id, array<int, string> $columns = ['*'])
 * @method static static|null first()
 * @method static static firstOrCreate(array<string, mixed> $attributes, array<string, mixed> $values = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static> where(string|\Closure|array<mixed> $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 */
class User extends Authenticatable implements HasApiTokensContract
{
    use HasApiTokens;
    use HasFactory;

    protected $table = 'users';

    /**
     * Resolve the factory for this model explicitly since it lives
     * outside the default App\Models namespace.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'phone_e164', // #8736 — forme normalisee, seule porteuse de l'unicite
        'google_id',
        'avatar_url',
        'provider',
        'preferred_language',
        'last_login_at',
        'personal_statuses', // #5540 — statuts multi-rôles
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'failed_login_attempts' => 'integer',
        'personal_statuses' => 'array', // #5540 — tableau JSON
        // COULOIR 16 — M2 (#9104) : cycle de vie du compte public.
        'phone_verified_at' => 'datetime',
        'deletion_requested_at' => 'datetime',
        'purge_scheduled_at' => 'datetime',
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

