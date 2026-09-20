<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Issue #7739 — Compte client GRAND PUBLIC de la marketplace travel
 * (épic #7736).
 *
 * Table PLATEFORME (`travel_customer_accounts`, schéma public, pas de
 * company_id) : le client n'appartient à aucun tenant — il réserve chez
 * plusieurs agences. Authentification par tokens Sanctum via le guard DÉDIÉ
 * `travel_customer` (provider `travel_customers`) : jamais le guard employés
 * ni super-admin (critère d'acceptation #7739).
 *
 * Le lien avec les réservations tenant est la colonne nue
 * `travel_bookings.customer_account_id` (pas de FK cross-schéma).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $password
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class TravelCustomerAccount extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    protected $table = 'travel_customer_accounts';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'email_verified_at',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];
}
