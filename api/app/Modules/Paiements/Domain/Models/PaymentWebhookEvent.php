<?php

declare(strict_types=1);

namespace App\Modules\Paiements\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * BC-38 PAIEMENTS (#8742) — evenement de webhook entrant, journalise.
 *
 * Deux roles, et un seul enregistrement par evenement fournisseur :
 *
 * 1. **Idempotence** — l'unicite `(provider_instance_id, event_id)` est portee
 *    par la base. Un rejeu est detecte par violation de contrainte, pas par un
 *    `if` applicatif qui perdrait la course en concurrence.
 * 2. **Tracabilite du hors-sequence** — un evenement qui contredirait un etat
 *    terminal est conserve avec `outcome = out_of_sequence` : il n'est pas
 *    silencieusement jete, donc un webhook perdu reste diagnosticable.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $provider_instance_id
 * @property int|null $webhook_endpoint_id
 * @property string $event_id
 * @property string|null $external_reference
 * @property string $event_type
 * @property string $outcome
 * @property string|null $requested_status
 * @property string|null $applied_status
 * @property int|null $transaction_id
 * @property string $payload_hash
 * @property Carbon $received_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 *
 * EXCEPTION TENANT-SCOPE (#7999) : `company_id` est NULLABLE par conception —
 * NULL = evenement d'une instance GLOBALE (meme convention que `payment_webhook_endpoints`).
 * Le routage d'un webhook entrant se fait hors contexte tenant prealable (l'appelant
 * est un fournisseur, pas un utilisateur) : un scope fail-closed exclurait les
 * evenements des fournisseurs globaux.
 */
class PaymentWebhookEvent extends Model
{
    /** L'evenement a fait progresser la transaction. */
    public const OUTCOME_PROCESSED = 'processed';

    /** L'evenement avait deja ete traite : no-op, resultat existant retourne. */
    public const OUTCOME_REPLAYED = 'replayed';

    /** L'evenement contredit un etat terminal : journalise, non applique. */
    public const OUTCOME_OUT_OF_SEQUENCE = 'out_of_sequence';

    /** Aucune transaction ne correspond a la reference externe. */
    public const OUTCOME_NO_TRANSACTION = 'no_transaction';

    protected $table = 'payment_webhook_events';

    protected $fillable = [
        'company_id',
        'provider_instance_id',
        'webhook_endpoint_id',
        'event_id',
        'external_reference',
        'event_type',
        'outcome',
        'requested_status',
        'applied_status',
        'transaction_id',
        'payload_hash',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    /**
     * Empreinte deterministe d'un corps de webhook.
     *
     * Sert d'`event_id` de repli quand le fournisseur n'en fournit pas : deux
     * envois du meme corps sont alors reconnus comme un rejeu, sans dependre
     * d'un identifiant que l'emetteur pourrait omettre.
     *
     * @param  array<string, mixed>  $body
     */
    public static function fingerprint(array $body): string
    {
        return hash('sha256', (string) json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForInstance(Builder $query, int $providerInstanceId): Builder
    {
        return $query->where('provider_instance_id', $providerInstanceId);
    }
}
