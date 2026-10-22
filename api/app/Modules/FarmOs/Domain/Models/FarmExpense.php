<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Modules\FarmOs\Domain\Enums\FarmDataQuality;
use App\Modules\FarmOs\Domain\Enums\FarmExpenseCategory;
use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T4.1 Depense agricole.
 *
 * SEULE table financiere de Farm OS, et elle est DECLARATIVE : aucun document
 * comptable n'est cree ici. `amount` est toujours un montant REEL declare (jamais
 * un zero de remplissage) ; `is_estimated` distingue une estimation d'une piece
 * reelle ; l'ABSENCE de ligne represente une donnee MANQUANTE.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int|null $farm_site_id
 * @property int|null $parcel_id
 * @property int|null $crop_cycle_id
 * @property int|null $farm_livestock_batch_id
 * @property int|null $farm_equipment_id
 * @property int|null $farm_campaign_id
 * @property string $category
 * @property string $label
 * @property string $amount
 * @property string $currency
 * @property bool $is_estimated
 * @property Carbon $spent_on
 * @property int|null $supplier_id
 * @property int|null $purchase_document_id
 * @property int|null $farm_input_id
 * @property string|null $quantity
 * @property string|null $unit
 * @property int|null $file_id
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property string|null $client_expense_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmExpense extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_expenses';

    protected $fillable = [
        'company_id',
        'farm_site_id',
        'parcel_id',
        'crop_cycle_id',
        'farm_livestock_batch_id',
        'farm_equipment_id',
        'farm_campaign_id',
        'category',
        'label',
        'amount',
        'currency',
        'is_estimated',
        'spent_on',
        'supplier_id',
        'purchase_document_id',
        'farm_input_id',
        'quantity',
        'unit',
        'file_id',
        'notes',
        'recorded_by',
        'client_expense_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'quantity' => 'decimal:3',
        'is_estimated' => 'boolean',
        'spent_on' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function categoryEnum(): ?FarmExpenseCategory
    {
        return FarmExpenseCategory::tryFrom($this->category);
    }

    /**
     * Qualite de la donnee : une estimation n'est PAS un montant reel.
     */
    public function dataQuality(): FarmDataQuality
    {
        return $this->is_estimated ? FarmDataQuality::Estimated : FarmDataQuality::Real;
    }

    /**
     * @return BelongsTo<FarmCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FarmCampaign::class, 'farm_campaign_id');
    }
}
