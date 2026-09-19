<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Journal d'audit des relances (BC-29 COMMUNICATION, R4 #7689) —
 * APPEND-ONLY (exigence issue : « tout envoi audite ») : une ligne par
 * decision terminale (sent / skipped / failed / cancelled) avec le code
 * machine du garde-fou, JAMAIS de contenu de mail ni de payload Google.
 *
 * Pas de FK vers `communication_follow_ups` : l'audit survit aux purges de
 * fils ; il est purge PAR INTEGRATION a la revocation de la boite (droit a
 * l'effacement, purge R2 etendue).
 *
 * `company_id` hors `$fillable` (#7646).
 *
 * @property string $id
 * @property string $company_id
 * @property string|null $follow_up_id
 * @property string $integration_id
 * @property string|null $thread_id
 * @property int|null $step_position
 * @property string|null $contact_email
 * @property string $action
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CommunicationFollowUpLog extends Model
{
    use BelongsToCompany;
    use HasUuids;

    public const ACTION_SENT = 'sent';

    public const ACTION_SKIPPED = 'skipped';

    public const ACTION_FAILED = 'failed';

    public const ACTION_CANCELLED = 'cancelled';

    protected $table = 'communication_follow_up_logs';

    /**
     * Allowlist explicite SANS `company_id` (#7646).
     *
     * @var list<string>
     */
    protected $fillable = [
        'follow_up_id',
        'integration_id',
        'thread_id',
        'step_position',
        'contact_email',
        'action',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_position' => 'integer',
        ];
    }

    /**
     * Trace une decision terminale d'une echeance (audit append-only).
     */
    public static function record(CommunicationFollowUp $followUp, string $action, ?string $reason = null): self
    {
        $log = new self;
        $log->forceFill([
            'company_id' => $followUp->company_id,
            'follow_up_id' => $followUp->id,
            'integration_id' => $followUp->integration_id,
            'thread_id' => $followUp->thread_id,
            'step_position' => $followUp->step_position,
            'contact_email' => $followUp->contact_email,
            'action' => $action,
            'reason' => $reason,
        ]);
        $log->save();

        return $log;
    }
}
