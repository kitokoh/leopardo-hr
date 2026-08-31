<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Modules\CRM\Domain\Enums\CrmMessageDirection;
use App\Modules\CRM\Domain\Enums\CrmMessageStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Message de canal CRM (issue #5725).
 *
 * PII (to_address, from_address, body) chiffrée au repos via les casts
 * `encrypted` — jamais exposée en clair dans les Resources sans autorisation
 * (convention CRM #5713). L'unicité (company_id, provider_message_id)
 * absorbe les rejeux de webhooks fournisseur.
 *
 * @property string $id
 * @property string $company_id
 * @property string $channel_id
 * @property string|null $conversation_id
 * @property string $provider
 * @property string|null $provider_message_id
 * @property string $direction
 * @property string|null $to_address
 * @property string|null $from_address
 * @property string|null $body
 * @property string|null $template_name
 * @property string $status
 * @property int $attempts
 * @property int $max_attempts
 * @property string|null $error_code
 * @property string|null $error_message
 * @property float|null $cost
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $read_at
 * @property Carbon|null $failed_at
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmChannelMessage extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'crm_channel_messages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'to_address' => 'encrypted',
            'from_address' => 'encrypted',
            'body' => 'encrypted',
            'cost' => 'float',
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CrmChannel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(CrmChannel::class, 'channel_id');
    }

    /** @return BelongsTo<CrmChannelConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CrmChannelConversation::class, 'conversation_id');
    }

    public static function isValidDirection(string $direction): bool
    {
        return in_array($direction, CrmMessageDirection::values(), true);
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, CrmMessageStatus::values(), true);
    }
}
