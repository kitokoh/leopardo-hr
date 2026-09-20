<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelDistributorKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Clé API de lecture d'un distributeur (TRAVEL-DISTRIBUTION, issue #7641).
 *
 * Une agence peut donner un accès de LECTURE distinct, révocable et traçable
 * à plusieurs plateformes de distribution — contrairement au jeton boutique
 * unique par tenant (`X-Travel-Shop-Token`). Le token n'est jamais stocké en
 * clair : seul `api_key_hash` (SHA-256) est persisté, le token brut n'est
 * affiché qu'une fois (création et rotation) — pattern TravelCarrierApiKey.
 */
class TravelDistributorKey extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelDistributorKeyFactory> */
    use HasFactory;

    /**
     * Scopes de lecture autorisés (allowlist fail-closed) :
     *  - catalog.read  → catalogue des voyages publiés + tarifs ;
     *  - bookings.read → suivi d'une réservation par référence.
     *
     * @var list<string>
     */
    public const SCOPES = ['catalog.read', 'bookings.read'];

    protected $fillable = [
        'name',
        'api_key_hash',
        'scopes',
        'enabled',
        'last_used_at',
        'usage_count',
        'rotated_at',
        'revoked_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'scopes' => 'array',
        'enabled' => 'boolean',
        'last_used_at' => 'datetime',
        'usage_count' => 'integer',
        'rotated_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
