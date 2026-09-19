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
 * Proposition de reponse IA en file Pending (BC-29 COMMUNICATION, R5 #7690
 * — spec §3.5).
 *
 * Materialisation DURABLE du pattern `PendingActionStore` (§3.5 : action
 * IA avec confirmation utilisateur) : la validation humaine peut arriver
 * des jours apres la proposition, un TTL cache de 15 minutes ne convient
 * pas — la file vit donc en base, avec les MEMES invariants (portee
 * company+user via la boite, une action consommee ne se rejoue pas).
 *
 * Cycle de vie selon la politique snapshotee dans `mode` :
 * - draft   : pending -> drafted (brouillon depose chez Gmail) | skipped | failed ;
 * - confirm : pending -> sent (apres APPROVE humain) | rejected | failed ;
 * - auto    : pending -> sent (direct, garde-fous R4) | skipped | failed —
 *             un verdict TEMPOREL (quiet hours, plafond) laisse la ligne
 *             `pending` : elle bascule dans la file de confirmation humaine.
 *
 * MINIMISATION : `body` (texte genere par l'IA) est CHIFFRE au repos (cast
 * encrypted, pattern corps de mails R2). `company_id` hors `$fillable` (#7646).
 *
 * @property string $id
 * @property string $company_id
 * @property string $integration_id
 * @property string $thread_id
 * @property string $message_id
 * @property string $category_key
 * @property string $mode
 * @property string $to_email
 * @property string|null $subject
 * @property string|null $body
 * @property string|null $ai_language
 * @property int|null $ai_confidence
 * @property string $status
 * @property string|null $skip_reason
 * @property Carbon|null $edited_at
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $gmail_draft_id
 * @property string|null $sent_gmail_message_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CommunicationIntegration|null $integration
 * @property-read CommunicationThread|null $thread
 *
 * @mixin Builder<static>
 */
class CommunicationPendingReply extends Model
{
    use BelongsToCompany;
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_DRAFTED = 'drafted';

    public const STATUS_SENT = 'sent';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_DRAFTED,
        self::STATUS_SENT,
        self::STATUS_REJECTED,
        self::STATUS_SKIPPED,
        self::STATUS_FAILED,
    ];

    public const MODE_DRAFT = 'draft';

    public const MODE_CONFIRM = 'confirm';

    public const MODE_AUTO = 'auto';

    protected $table = 'communication_pending_replies';

    /**
     * Allowlist explicite SANS `company_id` (#7646).
     *
     * @var list<string>
     */
    protected $fillable = [
        'integration_id',
        'thread_id',
        'message_id',
        'category_key',
        'mode',
        'to_email',
        'subject',
        'body',
        'ai_language',
        'ai_confidence',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Contenu genere a partir d'un email : chiffre au repos (R2).
            'body' => 'encrypted',
            'ai_confidence' => 'integer',
            'decided_by' => 'integer',
            'edited_at' => 'datetime',
            'decided_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
