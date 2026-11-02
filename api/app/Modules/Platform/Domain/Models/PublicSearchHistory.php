<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * COULOIR 16 — M3 (#9105) · Historique de recherche d'un client public.
 *
 * Table PLATEFORME (`public_search_history`, schéma `public`).
 *
 * ## Local d'abord
 *
 * L'historique vit d'abord sur l'APPAREIL : il alimente « reprendre où vous
 * étiez » et le cache hors ligne, sans exiger de compte. Cette table ne sert
 * qu'au cas où la personne est connectée et veut retrouver ses recherches
 * ailleurs. Un visiteur sans compte n'écrit jamais ici.
 *
 * ## Aucune position
 *
 * `city` est une ville choisie ou approximative. Aucune latitude/longitude
 * n'est stockée : mémoriser des coordonnées dans un historique de recherche
 * reconstituerait un trajet de vie sans que la personne l'ait demandé.
 *
 * EXCEPTION TENANT-SCOPE (#7999) : table PLATEFORME sans appartenance tenant.
 *
 * @property int $id
 * @property int $user_id
 * @property string $query
 * @property string|null $city
 * @property int $result_count
 * @property Carbon $searched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class PublicSearchHistory extends Model
{
    protected $table = 'public_search_history';

    protected $fillable = [
        'user_id',
        'query',
        'city',
        'result_count',
        'searched_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'result_count' => 'integer',
            'searched_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
