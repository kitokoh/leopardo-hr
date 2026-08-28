<?php

declare(strict_types=1);

namespace App\Modules\CRM\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Conversation de canal CRM (inbox unique par fournisseur, issue #5725).
 *
 * @property string $id
 * @property string $company_id
 * @property string $channel_id
 * @property string|null $provider_conversation_id
 * @property string|null $contact_ref_type
 * @property string|null $contact_ref_id
 * @property Carbon|null $last_message_at
 * @property int $unread_count
 * @property string $status
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CrmChannelConversation extends Model
{
    use BelongsToCompany;
    use HasUuids;

    protected $table = 'crm_channel_conversations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CrmChannel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(CrmChannel::class, 'channel_id');
    }

    /** @return HasMany<CrmChannelMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(CrmChannelMessage::class, 'conversation_id');
    }
}
