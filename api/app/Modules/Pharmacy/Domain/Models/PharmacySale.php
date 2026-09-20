<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Vente comptoir d'officine — PHARMA-005 (#7802).
 *
 * Numéro `VT-YYYY-XXXXX` séquencé par tenant. Une vente annulée passe en
 * `voided` (raison + horodatage), elle n'est JAMAIS supprimée : le stock est
 * ré-crédité par mouvements `return` (contre-passation).
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
 * @property string|null $void_reason
 * @property Carbon|null $voided_at
 * @property int|null $sold_by_employee_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PharmacySaleLine> $lines
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PharmacySale extends Model
{
    use BelongsToCompany;

    public const PAYMENT_METHODS = ['cash', 'card', 'mobile', 'insurance'];

    public const STATUSES = ['completed', 'voided'];

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
        'void_reason',
        'voided_at',
        'sold_by_employee_id',
    ];

    protected $casts = [
        'sold_at' => 'datetime',
        'prescription_id' => 'integer',
        'total_amount' => 'decimal:2',
        'voided_at' => 'datetime',
        'sold_by_employee_id' => 'integer',
    ];

    /** @return HasMany<PharmacySaleLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PharmacySaleLine::class, 'sale_id');
    }
}
