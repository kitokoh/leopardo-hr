<?php

declare(strict_types=1);

namespace App\Modules\HealthManager\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Acte de soins facturable (catalogue) — Issue #7791 (BC-31).
 *
 * Code unique par tenant ; catégorie bornée (CHECK en base). Le prix
 * courant est FIGÉ à la ligne de facture au moment de la facturation.
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $label
 * @property string $category
 * @property string $price
 * @property string $currency
 * @property bool $active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @mixin Builder<static>
 */
class HealthCareAct extends Model
{
    use BelongsToCompany;

    public const CATEGORY_CONSULTATION = 'consultation';

    public const CATEGORY_EXAM = 'exam';

    public const CATEGORY_SURGERY = 'surgery';

    public const CATEGORY_HOSPITALIZATION = 'hospitalization';

    public const CATEGORY_OTHER = 'other';

    /** @var list<string> */
    public const CATEGORIES = [
        self::CATEGORY_CONSULTATION,
        self::CATEGORY_EXAM,
        self::CATEGORY_SURGERY,
        self::CATEGORY_HOSPITALIZATION,
        self::CATEGORY_OTHER,
    ];

    protected $table = 'health_care_acts';

    protected $fillable = [
        'company_id',
        'code',
        'label',
        'category',
        'price',
        'currency',
        'active',
    ];

    protected $casts = [
        'category' => 'string',
        'price' => 'decimal:2',
        'active' => 'boolean',
    ];

    /**
     * @return HasMany<HealthInvoiceItem, $this>
     */
    public function invoiceItems(): HasMany
    {
        return $this->hasMany(HealthInvoiceItem::class, 'care_act_id');
    }
}
