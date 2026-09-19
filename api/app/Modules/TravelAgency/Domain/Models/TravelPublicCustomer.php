<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * #7739 — Compte client GRAND PUBLIC de la marketplace voyage.
 *
 * Table CROSS-TENANT du schéma `public` (pattern `users`) : le client
 * n'appartient à aucune agence, ses réservations (tables tenant) le
 * référencent par valeur via `travel_bookings.public_customer_id`.
 *
 * Auth par tokens Sanctum DÉDIÉS (guard `travel_customer_api`, provider
 * `travel_customers`) — jamais le guard employés : un token employé ne
 * résout pas sur ce guard et inversement (isolation testée).
 *
 * `password` est volontairement HORS `$fillable` (pattern #4695) :
 * assignation explicite par `forceFill` après hash.
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string|null $phone
 * @property string $password
 * @property string $preferred_language
 * @property string $status
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property int $failed_login_attempts
 * @property Carbon|null $locked_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<self>
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static static|null find(mixed $id, array<int, string> $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder<static> where(string|\Closure|array<mixed> $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 */
class TravelPublicCustomer extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'travel_public_customers';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'preferred_language',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until' => 'datetime',
        'failed_login_attempts' => 'integer',
    ];
}
