<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Journal d'idempotence/audit des webhooks paiement (verticale FUNDRAISING
 * — spec §3.4). `(provider, event_id)` unique : une double livraison est
 * acquittée (200) sans retraitement — aucune double comptabilisation.
 *
 * Non scopé tenant (le webhook arrive hors contexte tenant) : `company_id`
 * est une dénormalisation nullable pour l'audit.
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $provider
 * @property string $event_id
 * @property int|null $contribution_id
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class FundraisingPaymentEvent extends Model
{
    protected $table = 'fundraising_payment_events';

    protected $fillable = [
        'company_id',
        'provider',
        'event_id',
        'contribution_id',
        'payload',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
