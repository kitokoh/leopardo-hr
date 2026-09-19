<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Opt-out de relance d'un destinataire (BC-29 COMMUNICATION, R4 #7689).
 *
 * Exclusion LOCALE au module (UNIQUE company×email, adresses normalisees en
 * minuscules) : un destinataire opt-out n'est JAMAIS relance, quelle que
 * soit la regle — ce garde-fou s'ajoute au consentement/unsubscribe CRM
 * (contrat partage `EmailFollowUpConsentGate`).
 *
 * `company_id` hors `$fillable` (#7646).
 *
 * @property string $id
 * @property string $company_id
 * @property string $email
 * @property string $source
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CommunicationFollowUpOptOut extends Model
{
    use BelongsToCompany;
    use HasUuids;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_UNSUBSCRIBE = 'unsubscribe';

    protected $table = 'communication_follow_up_opt_outs';

    /**
     * Allowlist explicite SANS `company_id` (#7646).
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'source',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
        ];
    }
}
