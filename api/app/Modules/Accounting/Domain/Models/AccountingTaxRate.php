<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Référentiel des taux de TVA d'un tenant (issue #9376).
 *
 * Le module déclarait le multi-taux (`AccountingSettingsDefaults::tvaRates()`
 * renvoie DZ 19/9, TR 20/10/1, CA TPS+TVQ par province) mais ne savait
 * l'appliquer : `accounting_documents.tva_rate` est un taux unique au niveau du
 * document. Ce référentiel rend le multi-taux réellement saisissable — chaque
 * ligne de document référence un de ces taux.
 *
 * @property int $id
 * @property string $company_id
 * @property string $code
 * @property string $label
 * @property float $rate
 * @property string|null $country
 * @property string $kind
 * @property bool $is_default
 * @property bool $is_active
 */
class AccountingTaxRate extends Model
{
    use BelongsToCompany;
    use HasFactory;

    protected $table = 'accounting_tax_rates';

    public const KIND_STANDARD = 'standard';

    public const KIND_REDUCED = 'reduced';

    public const KIND_SUPER_REDUCED = 'super_reduced';

    public const KIND_ZERO = 'zero';

    public const KIND_EXEMPT = 'exempt';

    /** @var list<string> */
    protected $fillable = [
        'company_id',
        'code',
        'label',
        'rate',
        'country',
        'kind',
        'is_default',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'rate' => 'float',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
