<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * #8760 — Demande de contact reçue depuis la passerelle publique.
 *
 * Table PLATEFORME (`public_contact_requests`, schéma public) : le visiteur
 * n'appartient à AUCUN tenant — c'est pourquoi il n'y a **ni `company_id`
 * fillable, ni scope `BelongsToCompany`**. Le rattachement au professionnel
 * visé est porté par (`target_type`, `target_slug`) ; `resolved_company_id`
 * n'est qu'une trace d'acheminement écrite après résolution fail-closed.
 *
 * EXCEPTION TENANT-SCOPE (#7999) : table PLATEFORME (schéma public) SANS
 * colonne `company_id` — un visiteur n'appartient à aucun tenant et peut
 * contacter plusieurs professionnels dans la même session. Le rattachement au
 * professionnel visé est porté par (`target_type`, `target_slug`) ;
 * `resolved_company_id` n'est qu'une trace d'acheminement écrite après
 * résolution fail-closed, jamais un critère de lecture publique. Le trait
 * `BelongsToCompany` est donc inapplicable (même régime que
 * `MarketplaceBuyer` #7814 et `FundraiserPublicLink`).
 *
 * Séparation stricte avec Léa (assistante IA) : cette table n'a AUCUN lien avec
 * `ai_conversations` / `ai_audit_logs` — ni clé, ni service partagé, ni
 * permission commune. Un message humain entrant n'alimente jamais la mémoire
 * de l'IA, et l'IA ne lit jamais ces demandes.
 *
 * @property int $id
 * @property string $target_type
 * @property string $target_slug
 * @property string $sender_name
 * @property string $sender_email
 * @property string|null $sender_phone
 * @property string $message
 * @property Carbon $consent_at
 * @property Carbon|null $retention_until
 * @property string $idempotency_key
 * @property string|null $ip_hash
 * @property string|null $resolved_company_id
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class PublicContactRequest extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_HANDLED = 'handled';

    public const STATUS_CLOSED = 'closed';

    protected $table = 'public_contact_requests';

    protected $fillable = [
        'target_type',
        'target_slug',
        'sender_name',
        'sender_email',
        'sender_phone',
        'message',
        'consent_at',
        'retention_until',
        'idempotency_key',
        'ip_hash',
        'resolved_company_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'consent_at' => 'datetime',
            'retention_until' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
