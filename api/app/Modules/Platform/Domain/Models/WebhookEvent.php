<?php

declare(strict_types=1);

namespace App\Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * #5444 — Registre d'idempotence des webhooks entrants (schéma public).
 *
 * Une ligne par événement traité, identifiée par (source, event_id) :
 * `response_code`/`response_body` mémorisent la réponse à rejouer pour les
 * redelivrances ; `payload_hash` permet le diagnostic (déduplication par
 * hash pour les webhooks sans identifiant d'événement).
 *
 * @property int $id
 * @property string $source
 * @property string $event_id
 * @property string $payload_hash
 * @property int $response_code
 * @property string|null $response_body
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WebhookEvent extends Model
{
    // Table PUBLIC (schéma `public`) : les webhooks sont publics par nature
    // (fournisseurs tiers) et l'unicité est globale à la plateforme. La
    // qualification évite que le search_path (shared_tenants,public) cache
    // la table aux gardes/requêtes non qualifiées (F-17, #1933).
    protected $table = 'public.webhook_events';

    protected $fillable = [
        'source',
        'event_id',
        'payload_hash',
        'response_code',
        'response_body',
    ];

    protected $casts = [
        'response_code' => 'integer',
        'response_body' => 'string',
    ];
}
