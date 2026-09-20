<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Produit d'officine — PHARMA-002 (#7799).
 *
 * Référentiel des médicaments et produits de parapharmacie du tenant :
 * identité pharmaceutique (DCI, forme galénique, dosage, code-barres),
 * régulation (ordonnance obligatoire, produit contrôlé inscrit à
 * l'ordonnancier) et gestion (prix, taxe, seuil d'alerte de stock).
 *
 * Schéma partagé shared_tenants : isolation lecture/écriture par tenant via
 * le trait `BelongsToCompany` (même approche que Cabinet/EduManager).
 *
 * @property int $id
 * @property string|null $company_id
 * @property string $name
 * @property string|null $dci
 * @property string|null $form
 * @property string|null $dosage
 * @property string|null $barcode
 * @property string|null $internal_code
 * @property string $category
 * @property string $unit
 * @property bool $prescription_required
 * @property bool $is_controlled
 * @property string $purchase_price
 * @property string $sale_price
 * @property string $tax_rate
 * @property int $min_stock_level
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin \Illuminate\Database\Eloquent\Builder<static>
 */
class PharmacyProduct extends Model
{
    use BelongsToCompany;

    public const CATEGORIES = ['medicament', 'parapharmacie', 'dispositif', 'autre'];

    public const STATUSES = ['active', 'archived'];

    protected $table = 'pharmacy_products';

    protected $fillable = [
        'company_id',
        'name',
        'dci',
        'form',
        'dosage',
        'barcode',
        'internal_code',
        'category',
        'unit',
        'prescription_required',
        'is_controlled',
        'purchase_price',
        'sale_price',
        'tax_rate',
        'min_stock_level',
        'status',
    ];

    protected $casts = [
        'prescription_required' => 'boolean',
        'is_controlled' => 'boolean',
        'purchase_price' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'min_stock_level' => 'integer',
    ];
}
