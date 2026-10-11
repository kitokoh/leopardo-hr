<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Models;

use App\Modules\Fundraising\Domain\Enums\ContributionMethod;
use App\Modules\Fundraising\Domain\Enums\ContributionStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Contribution à une cagnotte (verticale FUNDRAISING — spec §3.2).
 *
 * `reference` publique unique (anti-énumération : jamais d'id séquentiel
 * exposé) ; seul le statut `completed` crédite le compteur de la cagnotte,
 * une seule fois, en transaction (ApplyPaymentUpdate). Coordonnées
 * contributeur jamais exposées publiquement (RGPD).
 *
 * @property int $id
 * @property string $company_id
 * @property int $fundraiser_id
 * @property string $reference
 * @property string $amount
 * @property string $currency
 * @property ContributionMethod $payment_method
 * @property string $provider
 * @property string|null $provider_reference
 * @property ContributionStatus $status
 * @property string|null $contributor_name
 * @property string|null $contributor_email
 * @property string|null $contributor_phone
 * @property bool $is_anonymous
 * @property string|null $message
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class FundraisingContribution extends Model
{
    use BelongsToCompany;

    protected $table = 'fundraising_contributions';

    protected $fillable = [
        'company_id',
        'fundraiser_id',
        'reference',
        'amount',
        'currency',
        'payment_method',
        'provider',
        'provider_reference',
        'status',
        'contributor_name',
        'contributor_email',
        'contributor_phone',
        'is_anonymous',
        'message',
        'metadata',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContributionStatus::class,
            'payment_method' => ContributionMethod::class,
            'is_anonymous' => 'boolean',
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Fundraiser, $this>
     */
    public function fundraiser(): BelongsTo
    {
        return $this->belongsTo(Fundraiser::class, 'fundraiser_id');
    }
}
