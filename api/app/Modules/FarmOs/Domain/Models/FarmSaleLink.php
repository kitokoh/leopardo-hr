<?php

declare(strict_types=1);

namespace App\Modules\FarmOs\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Farm OS — T4.1 Lien entre un objet agricole et un document de vente EXISTANT.
 *
 * Farm OS ne cree NI facture NI paiement NI ecriture comptable : il pointe vers
 * le document du service de ventes existant (`sale_document_id`) et stocke la
 * quantite et le montant IMPUTES a cet objet. C'est ce qui permet de rattacher
 * un chiffre d'affaires a une parcelle ou a un lot sans dupliquer la comptabilite
 * et sans compter deux fois un document multi-lignes.
 *
 * @property int $id
 * @property string|null $company_id
 * @property int $sale_document_id
 * @property int|null $sale_line_id
 * @property int|null $farm_harvest_lot_id
 * @property int|null $farm_livestock_batch_id
 * @property string|null $quantity
 * @property string|null $unit
 * @property string $amount
 * @property string $currency
 * @property int|null $customer_id
 * @property Carbon $sold_on
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property string|null $client_sale_link_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class FarmSaleLink extends Model
{
    use BelongsToCompany;

    protected $table = 'farm_sales_links';

    protected $fillable = [
        'company_id',
        'sale_document_id',
        'sale_line_id',
        'farm_harvest_lot_id',
        'farm_livestock_batch_id',
        'quantity',
        'unit',
        'amount',
        'currency',
        'customer_id',
        'sold_on',
        'notes',
        'recorded_by',
        'client_sale_link_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'amount' => 'decimal:2',
        'sold_on' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<FarmHarvestLot, $this>
     */
    public function harvestLot(): BelongsTo
    {
        return $this->belongsTo(FarmHarvestLot::class, 'farm_harvest_lot_id');
    }

    /**
     * @return BelongsTo<FarmLivestockBatch, $this>
     */
    public function livestockBatch(): BelongsTo
    {
        return $this->belongsTo(FarmLivestockBatch::class, 'farm_livestock_batch_id');
    }
}
