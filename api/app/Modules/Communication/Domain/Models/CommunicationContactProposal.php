<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Proposition de création de contact CRM (BC-29 COMMUNICATION, R3 #7688).
 *
 * Un expéditeur inconnu du CRM n'est JAMAIS créé silencieusement (§3.3 de
 * la spec) : la classification enregistre une proposition, le PROPRIÉTAIRE
 * de la boîte l'accepte (création via le contrat partagé
 * `App\Shared\Contracts\Crm\EmailContactDirectory`) ou l'écarte.
 *
 * `company_id` hors `$fillable` (#7646).
 *
 * @property string $id
 * @property string $company_id
 * @property string $integration_id
 * @property string $email
 * @property string|null $suggested_name
 * @property string $status
 * @property int $message_count
 * @property int|null $crm_contact_id
 * @property Carbon|null $decided_at
 * @property int|null $decided_by
 *
 * @mixin Builder<static>
 */
class CommunicationContactProposal extends Model
{
    use BelongsToCompany;
    use HasUuids;

    public const STATUS_PROPOSED = 'proposed';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DISMISSED = 'dismissed';

    protected $table = 'communication_contact_proposals';

    /** @var list<string> */
    protected $fillable = [
        'integration_id',
        'email',
        'suggested_name',
        'status',
        'message_count',
        'crm_contact_id',
        'decided_at',
        'decided_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'message_count' => 'integer',
            'crm_contact_id' => 'integer',
            'decided_at' => 'datetime',
            'decided_by' => 'integer',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PROPOSED;
    }

    /**
     * @return BelongsTo<CommunicationIntegration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(CommunicationIntegration::class, 'integration_id');
    }
}
