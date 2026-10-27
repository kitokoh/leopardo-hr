<?php

declare(strict_types=1);

namespace App\Modules\Pages\Domain\Models;

use App\Modules\Pages\Domain\Enums\PageVerificationStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dossier de vérification de propriété d'une Page (BC PAGES, #8725).
 *
 * ## Pourquoi un dossier séparé du statut de la Page
 *
 * `Page::$verification_status` porte l'état COURANT. Un dossier porte
 * l'HISTOIRE d'une demande : qui l'a ouverte, par quelle méthode, avec quelles
 * preuves, qui a décidé, pourquoi. Sans cette séparation, un refus suivi d'une
 * nouvelle demande écraserait la trace du premier refus — exactement ce qu'un
 * litige demande de consulter.
 *
 * Le dossier est **append-only dans son esprit** : on ne modifie jamais
 * `decided_at`/`decided_by` d'un dossier clos, on en ouvre un nouveau. C'est ce
 * qui rend l'audit fiable (un `UPDATE` sur une décision passée est
 * indiscernable d'une erreur de saisie).
 *
 * ## Ce qui n'est JAMAIS dans ce modèle
 *
 * Aucun contenu de pièce justificative, aucun code SMS. Les pièces vivent dans
 * `page_verification_evidence` (chemin de stockage privé + empreinte), les
 * codes dans `page_verification_codes` (empreinte seulement). Ce modèle ne
 * contient que des métadonnées : il peut être retourné à un écran d'admin sans
 * risque de fuite de document.
 *
 * @property int $id
 * @property string $company_id
 * @property int $page_id
 * @property string $method
 * @property string $status
 * @property string|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property string|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_reason
 * @property int $evidence_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Page|null $page
 *
 * @method static Builder<static> query()
 * @method static Builder<static> open()
 *
 * @mixin Builder<static>
 */
class PageVerificationRequest extends Model
{
    use BelongsToCompany;

    /** Étapes du dossier. Une seule est active à la fois. */
    public const STATUS_OPEN = 'open';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_DISPUTED = 'disputed';

    public const STATUS_REVOKED = 'revoked';

    /** Motifs de décision acceptés — allowlist (cf. `PagePolicy`, #8729). */
    public const DECISION_REASONS = [
        'proof_valid',
        'proof_insufficient',
        'proof_expired',
        'ownership_disputed',
        'fraud_suspected',
        'lease_terminated',
        'tenant_request',
    ];

    protected $table = 'page_verification_requests';

    protected $fillable = [
        'company_id',
        'page_id',
        'method',
        'status',
        'submitted_by',
        'submitted_at',
        'decided_by',
        'decided_at',
        'decision_reason',
        'evidence_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'evidence_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'page_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_PENDING_REVIEW, self::STATUS_DISPUTED]);
    }

    public function isDecided(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_REJECTED, self::STATUS_REVOKED], true);
    }

    /**
     * Statut de Page correspondant à la décision.
     *
     * Table unique : c'est le seul endroit où la correspondance
     * « décision de dossier » → « état de Page » est écrite. La dupliquer dans
     * les contrôleurs produirait deux vérités divergentes au premier ajout de
     * statut.
     */
    public function resultingPageStatus(): ?PageVerificationStatus
    {
        return match ($this->status) {
            self::STATUS_PENDING_REVIEW => PageVerificationStatus::Pending,
            self::STATUS_APPROVED => PageVerificationStatus::Verified,
            self::STATUS_REJECTED => PageVerificationStatus::Rejected,
            self::STATUS_DISPUTED => PageVerificationStatus::Disputed,
            self::STATUS_REVOKED => PageVerificationStatus::Revoked,
            default => null,
        };
    }
}
