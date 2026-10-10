<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Models;

use App\Modules\Fundraising\Domain\Enums\PayoutMethod;
use App\Modules\Fundraising\Domain\Enums\PayoutStatus;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reversement d'une cagnotte vers son bénéficiaire (verticale FUNDRAISING
 * — spec §3.3). Workflow `requested → processing → paid|failed`,
 * `requested → cancelled` ; règle de solde vérifiée en transaction.
 *
 * @property int $id
 * @property string $company_id
 * @property int $fundraiser_id
 * @property string $reference
 * @property string $amount
 * @property string $currency
 * @property PayoutMethod $method
 * @property string $recipient_name
 * @property string $recipient_account
 * @property PayoutStatus $status
 * @property string|null $provider_reference
 * @property string|null $failure_reason
 * @property int|null $requested_by
 * @property int|null $processed_by
 * @property Carbon|null $processed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class FundraisingPayout extends Model
{
    use BelongsToCompany;

    protected $table = 'fundraising_payouts';

    protected $fillable = [
        'company_id',
        'fundraiser_id',
        'reference',
        'amount',
        'currency',
        'method',
        'recipient_name',
        'recipient_account',
        'status',
        'provider_reference',
        'failure_reason',
        'requested_by',
        'processed_by',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'method' => PayoutMethod::class,
            'processed_at' => 'datetime',
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
