<?php

declare(strict_types=1);

namespace App\Modules\Trade\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TradeProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Produit du catalogue B2B Trade (BC-36 TRADE, TRADE-002/#8477).
 *
 * Le code douanier (`hs_code`) est NULLABLE par conception : il est saisi par
 * l'utilisateur ou absent. Le systeme ne le devine JAMAIS — une valeur inventee
 * ferait croire a une conformite douaniere qui n'existe pas.
 *
 * Archivage LOGIQUE (`status = archived`) : les RFQ et offres passees
 * continuent de referencer le produit sans le voir disparaitre.
 *
 * @property int $id
 * @property string $company_id
 * @property string $name
 * @property string|null $description
 * @property string|null $category
 * @property string|null $hs_code
 * @property string|null $origin_country
 * @property string $unit
 * @property string|null $moq_quantity
 * @property int|null $indicative_unit_price_minor
 * @property string|null $currency
 * @property array<string, mixed>|null $specs
 * @property string $status
 */
final class TradeProduct extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TradeProductFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'trade_products';

    protected $fillable = [
        'name',
        'description',
        'category',
        'hs_code',
        'origin_country',
        'unit',
        'moq_quantity',
        'indicative_unit_price_minor',
        'currency',
        'specs',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'indicative_unit_price_minor' => 'integer',
            'specs' => 'array',
        ];
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /** @return HasMany<TradeRfqLine, $this> */
    public function rfqLines(): HasMany
    {
        return $this->hasMany(TradeRfqLine::class, 'trade_product_id');
    }
}
