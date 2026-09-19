<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Acte médical du catalogue tarifaire — HC-007 (#7791, BC-30).
 *
 * Consultations, examens, actes techniques, journées d'hospitalisation…
 * Le prix du catalogue est le prix COURANT : à la facturation il est FIGÉ
 * dans la ligne de facture (`health_invoice_items.unit_price`) — modifier
 * le catalogue ne change jamais une facture existante. Un acte déjà
 * facturé ne se supprime pas (FK restrictive) : il se DÉSACTIVE.
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string $category
 * @property string $price
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthCareAct extends Model
{
    use BelongsToCompany;

    public const CATEGORY_CONSULTATION = 'consultation';

    public const CATEGORY_EXAMINATION = 'examination';

    public const CATEGORY_PROCEDURE = 'procedure';

    public const CATEGORY_HOSPITALIZATION = 'hospitalization';

    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_CONSULTATION,
        self::CATEGORY_EXAMINATION,
        self::CATEGORY_PROCEDURE,
        self::CATEGORY_HOSPITALIZATION,
        self::CATEGORY_OTHER,
    ];

    protected $table = 'health_care_acts';

    /**
     * `company_id` posé par BelongsToCompany (pattern strict #7712).
     */
    protected $fillable = [
        'code',
        'name',
        'category',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
