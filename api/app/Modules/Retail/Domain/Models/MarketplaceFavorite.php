<?php

declare(strict_types=1);

namespace App\Modules\Retail\Domain\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Favori d'un acheteur marketplace (BC-17 RETAIL, #7814).
 *
 * Table centrale (schema public) : reference produit interne =
 * (company_id vendeur, product_id) — le company_id n'est jamais expose
 * dans les DTO publics, il borne seulement les lectures cross-tenant aux
 * vendeurs opt-in. Unique par (buyer, produit).
 *
 * @property int $id
 * @property int $buyer_id
 * @property string $company_id
 * @property int $product_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static Builder<static> query()
 */
class MarketplaceFavorite extends Model
{
    protected $table = 'marketplace_favorites';

    /** @var list<string> */
    protected $fillable = [
        'buyer_id',
        'company_id',
        'product_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'buyer_id' => 'integer',
            'product_id' => 'integer',
        ];
    }
}
