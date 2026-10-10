<?php

declare(strict_types=1);

namespace App\Modules\Fundraising\Domain\Models;

use App\Modules\Fundraising\Domain\Enums\FundraiserStatus;
use App\Modules\Fundraising\Domain\Enums\FundraisingCategory;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Cagnotte solidaire d'un tenant (verticale FUNDRAISING — spec
 * docs/specifications/SOLUTION_FUNDRAISING.md §3.1).
 *
 * Lien public par `slug` unique global ; compteurs `collected_amount` /
 * `contributions_count` dénormalisés, mis à jour en transaction par
 * ApplyPaymentUpdate (jamais recalculés en lecture) ; fenêtre de collecte
 * `starts_at..ends_at`. Tenant-scoped (`company_id` uuid), sans FK
 * (conventions migrations tenant §2.6).
 *
 * @property int $id
 * @property string $company_id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property string $beneficiary_name
 * @property string|null $beneficiary_contact
 * @property FundraisingCategory $category
 * @property string|null $goal_amount
 * @property string $collected_amount
 * @property int $contributions_count
 * @property string $currency
 * @property array<int, int|float>|null $suggested_amounts
 * @property string|null $min_amount
 * @property string|null $max_amount
 * @property string|null $cover_image_path
 * @property FundraiserStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $published_at
 * @property string|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 *
 * @mixin Builder<static>
 */
class Fundraiser extends Model
{
    use BelongsToCompany;

    protected $table = 'fundraisers';

    protected $fillable = [
        'company_id',
        'slug',
        'title',
        'description',
        'beneficiary_name',
        'beneficiary_contact',
        'category',
        'goal_amount',
        'collected_amount',
        'contributions_count',
        'currency',
        'suggested_amounts',
        'min_amount',
        'max_amount',
        'cover_image_path',
        'status',
        'starts_at',
        'ends_at',
        'published_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => FundraiserStatus::class,
            'category' => FundraisingCategory::class,
            'suggested_amounts' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<FundraisingContribution>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(FundraisingContribution::class, 'fundraiser_id');
    }

    /**
     * @return HasMany<FundraisingPayout>
     */
    public function payouts(): HasMany
    {
        return $this->hasMany(FundraisingPayout::class, 'fundraiser_id');
    }

    /**
     * La cagnotte est-elle dans sa fenêtre de collecte ?
     * (bornes nullables : null = pas de borne)
     */
    public function isWithinCollectionWindow(?Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->starts_at !== null && $at->lt($this->starts_at)) {
            return false;
        }

        return $this->ends_at === null || $at->lte($this->ends_at);
    }

    /**
     * Solde disponible pour reversement = collecté − payouts verrouillant
     * le solde (requested|processing|paid) — règle spec §3.3.
     */
    public function availableBalance(): float
    {
        $locked = (float) $this->payouts()
            ->whereIn('status', ['requested', 'processing', 'paid'])
            ->sum('amount');

        return max(0.0, (float) $this->collected_amount - $locked);
    }
}
