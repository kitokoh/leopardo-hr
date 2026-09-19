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
 * Echeance de relance materialisee (BC-29 COMMUNICATION, R4 #7689) — a la
 * fois FILE D'ATTENTE (statuts) et TABLE DE DEDUPLICATION (exigence issue :
 * « relance part une seule fois par echeance ») : UNIQUE (company, thread,
 * rule, step_position), une echeance deja `sent`/`skipped`/`cancelled`
 * n'est JAMAIS rejouee.
 *
 * Cycle de vie :
 *   pending  -> sent      (relance partie via le Gmail du proprietaire)
 *            -> skipped   (garde-fou terminal : opted_out, consent_blocked,
 *                          mailing_list, missing_send_scope, auto_reply…)
 *            -> cancelled (le destinataire a REPONDU — arret de la sequence
 *                          via la sync R2 — ou annulation manuelle)
 *            -> failed    (erreur d'envoi definitive, code machine)
 * Les garde-fous TEMPORELS (quiet hours, plafonds journaliers) ne terminent
 * pas l'echeance : elle reste `pending` et repart a la passe suivante.
 *
 * `company_id` hors `$fillable` (#7646).
 *
 * @property string $id
 * @property string $company_id
 * @property string $rule_id
 * @property string $integration_id
 * @property string $thread_id
 * @property string|null $message_id
 * @property int $step_position
 * @property string $contact_email
 * @property Carbon $scheduled_for
 * @property string $status
 * @property string|null $skip_reason
 * @property Carbon|null $sent_at
 * @property string|null $sent_gmail_message_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CommunicationFollowUp extends Model
{
    use BelongsToCompany;
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_SENT,
        self::STATUS_SKIPPED,
        self::STATUS_FAILED,
        self::STATUS_CANCELLED,
    ];

    protected $table = 'communication_follow_ups';

    /**
     * Allowlist explicite SANS `company_id` (#7646).
     *
     * @var list<string>
     */
    protected $fillable = [
        'rule_id',
        'integration_id',
        'thread_id',
        'message_id',
        'step_position',
        'contact_email',
        'scheduled_for',
        'status',
        'skip_reason',
        'sent_at',
        'sent_gmail_message_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_position' => 'integer',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CommunicationFollowUpRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommunicationFollowUpRule::class, 'rule_id');
    }

    /**
     * @return BelongsTo<CommunicationIntegration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(CommunicationIntegration::class, 'integration_id');
    }

    /**
     * @return BelongsTo<CommunicationThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(CommunicationThread::class, 'thread_id');
    }
}
