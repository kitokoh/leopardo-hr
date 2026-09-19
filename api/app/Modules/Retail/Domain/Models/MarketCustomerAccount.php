<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Issue #7814 — Compte acheteur GRAND PUBLIC de Leopardo Marché
 * (BC-17 RETAIL, backlog post-v1 spec MARKETPLACE_RETAIL_PUBLIC.md §6).
 *
 * Table PLATEFORME (`market_customer_accounts`, schéma public, pas de
 * company_id) : l'acheteur n'appartient à aucun tenant — il commande chez
 * plusieurs boutiques. Authentification par tokens Sanctum via le guard
 * DÉDIÉ `market_customer` (provider `market_customers`) : jamais le guard
 * employés ni super-admin (même pattern que TravelCustomerAccount #7739).
 *
 * Le lien avec les commandes tenant est la colonne nue
 * `retail_orders.customer_account_id` (pas de FK cross-schéma).
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
class MarketCustomerAccount extends Authenticatable
{
    use HasApiTokens;
    use Notifiable;

    protected $table = 'market_customer_accounts';

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
