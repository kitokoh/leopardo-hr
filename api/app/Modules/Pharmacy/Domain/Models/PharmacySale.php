<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Vente comptoir d'officine — PHARMA-005 (#7802).
 *
 * Numéro `VT-YYYY-XXXXX` séquencé PAR TENANT, totaux calculés SERVEUR
 * (jamais confiés au client), prix figés dans les lignes. `prescription_id`
 * référence l'ordonnance quand un produit `prescription_required` est
 * délivré (la table des ordonnances arrive avec PHARMA-006/#7803). Une
 * vente annulée passe `voided` (raison, auteur, date) et re-crédite les
 * lots d'origine — elle n'est JAMAIS supprimée (piste comptable).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $number
 * @property Carbon $sold_at
 * @property string|null $customer_name
 * @property int|null $prescription_id
 * @property string $payment_method
 * @property string $total_amount
 * @property string $status
 * @property int|null $sold_by_employee_id
 * @property string|null $void_reason
 * @property Carbon|null $voided_at
 * @property int|null $voided_by_employee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class PharmacySale extends Model
{
    use BelongsToCompany;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOIDED = 'voided';

    public const PAYMENT_METHODS = ['cash', 'card', 'mobile', 'insurance'];

    protected $table = 'pharmacy_sales';

    protected $fillable = [
        'company_id',
        'number',
        'sold_at',
        'customer_name',
        'prescription_id',
        'payment_method',
        'total_amount',
        'status',
        'sold_by_employee_id',
        'void_reason',
        'voided_at',
        'voided_by_employee_id',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
        'prescription_id' => 'integer',
        'total_amount' => 'decimal:2',
        'sold_by_employee_id' => 'integer',
        'voided_at' => 'datetime',
        'voided_by_employee_id' => 'integer',
    ];

    /**
     * @return HasMany<PharmacySaleLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PharmacySaleLine::class, 'sale_id');
    }
}
