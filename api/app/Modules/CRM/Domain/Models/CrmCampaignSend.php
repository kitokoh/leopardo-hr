<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Envoi unitaire d'une campagne CRM — Issue #5724.
 *
 * @property int $id
 * @property int $campaign_id
 * @property string $company_id
 * @property int $contact_id
 * @property string $channel
 * @property string $status
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmCampaignSend extends Model
{
    use BelongsToCompany;

    protected $table = 'crm_campaign_sends';

    protected $fillable = [
        'campaign_id',
        'company_id',
        'contact_id',
        'channel',
        'status',
        'provider_message_id',
        'error',
        'sent_at',
        'delivered_at',
    ];

    protected $casts = [
        'campaign_id' => 'integer',
        'contact_id' => 'integer',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];
}
