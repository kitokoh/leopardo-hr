<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * COULOIR 16 — M3 (#9105) · Index de ROUTAGE des activités du client public.
 *
 * Table PLATEFORME (`public_activity_index`, schéma `public`) : le client
 * n'appartient à AUCUN tenant — c'est pourquoi il n'y a **ni `company_id`
 * fillable au sens tenant, ni scope `BelongsToCompany`**. `company_id` est ici
 * une simple coordonnée de routage (« dans quel schéma chercher »), jamais un
 * critère d'autorisation.
 *
 * EXCEPTION TENANT-SCOPE (#7999) : table PLATEFORME sans appartenance tenant.
 * Même régime que `PublicContactRequest` (#8760) et `MarketplaceBuyer` (#7814).
 *
 * ⚠️ **Cette table n'autorise RIEN et ne porte AUCUN contenu métier.** Chaque
 * lecture rejoue le contrôle d'accès dans le contexte tenant via le
 * `PublicActivityProvider` de la verticale, qui relit le statut réel.
 *
 * @property int $id
 * @property int $user_id
 * @property string $company_id
 * @property string $vertical
 * @property string $activity_type
 * @property string $external_ref
 * @property string $status
 * @property Carbon $occurred_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class PublicActivityIndex extends Model
{
    public const TYPE_ORDER = 'order';

    public const TYPE_BOOKING = 'booking';

    /** Types d'activité qu'un client peut voir dans l'onglet Activités. */
    public const TYPES = [self::TYPE_ORDER, self::TYPE_BOOKING];

    protected $table = 'public_activity_index';

    protected $fillable = [
        'user_id',
        'company_id',
        'vertical',
        'activity_type',
        'external_ref',
        'status',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
