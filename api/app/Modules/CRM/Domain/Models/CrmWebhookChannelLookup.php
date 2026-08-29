<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Lookup public des webhooks CRM (issue #5725).
 *
 * Vit dans le schéma `public` (hors tenant) : les webhooks fournisseur sont
 * reçus sans session, la table mappe provider_key → tenant avant traitement.
 *
 * @property string $id
 * @property string $company_id
 * @property string $channel_id
 * @property string $provider
 * @property string $provider_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmWebhookChannelLookup extends Model
{
    use HasUuids;

    protected $table = 'public.crm_webhook_channel_lookup';

    protected $guarded = [];
}
