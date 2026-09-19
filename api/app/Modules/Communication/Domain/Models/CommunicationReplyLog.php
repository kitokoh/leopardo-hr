<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Journal d'audit des reponses assistees (BC-29 COMMUNICATION, R5 #7690) —
 * APPEND-ONLY (exigence issue : « audit complet ») : une ligne par decision
 * (proposition, brouillon depose, edition, approbation, rejet, envoi,
 * envoi auto, report, skip garde-fou, echec) avec le code machine et
 * l'employe a l'origine d'une decision HUMAINE — JAMAIS de contenu de mail
 * ni de payload Google (pattern CommunicationFollowUpLog R4).
 *
 * Pas de FK vers `communication_pending_replies` : l'audit survit aux
 * purges de fil ; il est purge PAR INTEGRATION a la revocation de la boite
 * (droit a l'effacement, purge R2 etendue).
 *
 * `company_id` hors `$fillable` (#7646).
 *
 * @property string $id
 * @property string $company_id
 * @property string $integration_id
 * @property string|null $pending_reply_id
 * @property string|null $thread_id
 * @property string|null $to_email
 * @property string $action
 * @property string|null $reason
 * @property int|null $actor_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CommunicationReplyLog extends Model
{
    use BelongsToCompany;
    use HasUuids;

    public const ACTION_PROPOSED = 'proposed';

    public const ACTION_DRAFT_CREATED = 'draft_created';

    public const ACTION_EDITED = 'edited';

    public const ACTION_APPROVED = 'approved';

    public const ACTION_REJECTED = 'rejected';

    public const ACTION_SENT = 'sent';

    public const ACTION_AUTO_SENT = 'auto_sent';

    public const ACTION_DEFERRED = 'deferred';

    public const ACTION_SKIPPED = 'skipped';

    public const ACTION_FAILED = 'failed';

    protected $table = 'communication_reply_logs';

    /**
     * Allowlist explicite SANS `company_id` (#7646).
     *
     * @var list<string>
     */
    protected $fillable = [
        'integration_id',
        'pending_reply_id',
        'thread_id',
        'to_email',
        'action',
        'reason',
        'actor_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
        ];
    }

    /**
     * Trace une decision sur une proposition (audit append-only).
     */
    public static function record(
        CommunicationPendingReply $reply,
        string $action,
        ?string $reason = null,
        ?int $actorId = null,
    ): self {
        $log = new self;
        $log->forceFill([
            'company_id' => $reply->company_id,
            'integration_id' => $reply->integration_id,
            'pending_reply_id' => $reply->id,
            'thread_id' => $reply->thread_id,
            'to_email' => $reply->to_email,
            'action' => $action,
            'reason' => $reason,
            'actor_id' => $actorId,
        ]);
        $log->save();

        return $log;
    }
}
