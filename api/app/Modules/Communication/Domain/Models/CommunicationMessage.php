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
 * Message Gmail synchronise (BC-29 COMMUNICATION, R2 #7687).
 *
 * MINIMISATION (exigence issue) :
 * - metadonnees utiles seulement : expediteur, destinataires, sujet, date,
 *   labels, snippet + references RFC 5322 (Message-ID / In-Reply-To) pour
 *   le threading et les relances R4 ;
 * - `body` : partie text/plain UNIQUEMENT, bornee a {@see BODY_MAX_BYTES},
 *   cast `encrypted` — CHIFFREE AU REPOS via APP_KEY (pattern tokens R1
 *   #7686 / CrmChannelMessage #5725), jamais en clair en base ;
 * - pieces jointes JAMAIS stockees : `attachment_refs` ne contient que les
 *   references Gmail (attachmentId, filename, mimeType, size).
 *
 * `company_id` hors `$fillable` (#7646).
 *
 * CLASSIFICATION IA (R3 #7688) : sortie structuree du tool `email_classify`
 * VALIDEE contre la taxonomie du tenant avant persistance (jamais de texte
 * libre du LLM) ; `crm_contact_id` pose par correspondance d'email via le
 * contrat partage `App\Shared\Contracts\Crm\EmailContactDirectory`.
 *
 * @property string $id
 * @property string $company_id
 * @property string $thread_id
 * @property string $integration_id
 * @property string $gmail_message_id
 * @property string|null $internet_message_id
 * @property string|null $in_reply_to
 * @property string|null $from_email
 * @property array<int, string>|null $to_emails
 * @property array<int, string>|null $cc_emails
 * @property string|null $subject
 * @property string|null $snippet
 * @property string|null $body
 * @property array<int, string>|null $labels
 * @property array<int, array<string, mixed>>|null $attachment_refs
 * @property Carbon|null $sent_at
 * @property string|null $ai_category
 * @property string|null $ai_language
 * @property string|null $ai_sentiment
 * @property string|null $ai_action
 * @property int|null $ai_confidence
 * @property string $classification_status
 * @property string|null $classification_error
 * @property Carbon|null $classified_at
 * @property int|null $crm_contact_id
 * @property string|null $contact_link_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class CommunicationMessage extends Model
{
    use BelongsToCompany;
    use HasUuids;

    /**
     * Borne haute du corps text/plain conserve (minimisation + cout du
     * chiffrement) : au-dela, le texte est tronque — le message complet
     * reste accessible chez Gmail via `gmail_message_id`.
     */
    public const BODY_MAX_BYTES = 65536;

    public const CLASSIFICATION_PENDING = 'pending';

    public const CLASSIFICATION_CLASSIFIED = 'classified';

    public const CLASSIFICATION_FAILED = 'failed';

    public const CONTACT_LINK_LINKED = 'linked';

    public const CONTACT_LINK_PROPOSED = 'proposed';

    public const CONTACT_LINK_NONE = 'none';

    protected $table = 'communication_messages';

    /**
     * Allowlist explicite SANS `company_id` (#7646).
     *
     * @var list<string>
     */
    protected $fillable = [
        'thread_id',
        'integration_id',
        'gmail_message_id',
        'internet_message_id',
        'in_reply_to',
        'from_email',
        'to_emails',
        'cc_emails',
        'subject',
        'snippet',
        'body',
        'labels',
        'attachment_refs',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'to_emails' => 'array',
            'cc_emails' => 'array',
            // Corps chiffre au repos — exigence « corps chiffre » de #7687.
            'body' => 'encrypted',
            'labels' => 'array',
            'attachment_refs' => 'array',
            'sent_at' => 'datetime',
            'ai_confidence' => 'integer',
            'classified_at' => 'datetime',
            'crm_contact_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CommunicationThread, $this>
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(CommunicationThread::class, 'thread_id');
    }

    /**
     * @return BelongsTo<CommunicationIntegration, $this>
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(CommunicationIntegration::class, 'integration_id');
    }
}
