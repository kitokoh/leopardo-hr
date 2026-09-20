<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Compte acheteur grand public de la marketplace Leopardo Marche
 * (BC-17 RETAIL, #7814).
 *
 * Compte PLATEFORME (table centrale `marketplace_buyers`, schema public,
 * PAS de company_id) : la marketplace est cross-tenant, un acheteur
 * n'appartient a aucun vendeur. Inscription legere (email unique + mot de
 * passe hashe + nom + telephone optionnel), authentification par jeton
 * opaque hashe (MarketplaceBuyerToken) — jamais de Sanctum tenant ici.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $phone
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 */
class MarketplaceBuyer extends Model
{
    protected $table = 'marketplace_buyers';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
    ];
}
