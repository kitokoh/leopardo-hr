<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Modules\Trade\Domain\Enums\TradeQuoteDirection;
use App\Modules\Trade\Domain\Enums\TradeQuoteStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeQuoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Offre commerciale Trade — objet PARTAGE acheteur/vendeur (BC-36 TRADE,
 * TRADE-004/#8479).
 *
 * Le meme modele sert de REPONSE a une RFQ (`incoming`) et d'OFFRE PROACTIVE
 * (`outgoing`). C'est la traduction technique de l'exigence « noyau commun,
 * espaces differents » : aucune duplication de modele entre l'espace acheteur
 * et l'espace vendeur.
 *
 * VERSIONNEMENT : `version` + `supersedes_id`. Une revision cree une NOUVELLE
 * ligne qui remplace l'ancienne — l'historique de negociation reste
 * integralement consultable.
 *
 * `subtotal_minor` / `total_minor` sont FIGES cote SERVEUR (recalcules des
 * lignes) : jamais acceptes du client.
 *
 * @property int $id
 * @property string $company_id
 * @property string $reference
 * @property int $version
 * @property int|null $supersedes_id
 * @property TradeQuoteDirection $direction
 * @property int|null $trade_rfq_id
 * @property int|null $trade_rfq_invitation_id
 * @property int|null $trade_deal_id
 * @property int|null $trade_counterparty_id
 * @property TradeQuoteStatus $status
 * @property string $currency
 * @property string|null $incoterm
 * @property Carbon|null $validity_until
 * @property int|null $lead_time_days
 * @property string|null $payment_terms
 * @property int $subtotal_minor
 * @property int $total_minor
 * @property string|null $notes
 * @property int|null $created_by_employee_id
 */
final class TradeQuote extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeQuoteFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'trade_quotes';

    protected $fillable = [
        'reference',
        'version',
        'supersedes_id',
        'direction',
        'trade_rfq_id',
        'trade_rfq_invitation_id',
        'trade_deal_id',
        'trade_counterparty_id',
        'status',
        'currency',
        'incoterm',
        'validity_until',
        'lead_time_days',
        'payment_terms',
        'subtotal_minor',
        'total_minor',
        'notes',
        'created_by_employee_id',
        'submitted_at',
        'accepted_at',
        'rejected_at',
        'withdrawn_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'supersedes_id' => 'integer',
            'direction' => TradeQuoteDirection::class,
            'status' => TradeQuoteStatus::class,
            'trade_rfq_id' => 'integer',
            'trade_rfq_invitation_id' => 'integer',
            'trade_deal_id' => 'integer',
            'trade_counterparty_id' => 'integer',
            'validity_until' => 'date',
            'lead_time_days' => 'integer',
            'subtotal_minor' => 'integer',
            'total_minor' => 'integer',
            'created_by_employee_id' => 'integer',
            'submitted_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /** @return HasMany<TradeQuoteLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(TradeQuoteLine::class, 'trade_quote_id')->orderBy('position');
    }

    /** @return BelongsTo<TradeRfq, $this> */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(TradeRfq::class, 'trade_rfq_id');
    }

    /** @return BelongsTo<TradeCounterparty, $this> */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(TradeCounterparty::class, 'trade_counterparty_id');
    }

    /**
     * Version precedente (chaine de negociation).
     *
     * @return BelongsTo<TradeQuote, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /**
     * La validite de l'offre est-elle depassee a la date donnee ?
     * Utilise pour marquer `expired` — la comparaison se fait sur des dates,
     * jamais sur une supposition de fraicheur.
     */
    public function isExpiredAt(?Carbon $at = null): bool
    {
        if ($this->validity_until === null) {
            return false;
        }

        $at ??= now();

        return $this->validity_until->endOfDay()->lt($at);
    }
}
