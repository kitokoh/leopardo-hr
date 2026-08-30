<?php

declare(strict_types=1);

namespace App\Modules\TravelAgency\Domain\Models;

use App\Shared\Traits\BelongsToCompany;
use Database\Factories\TravelArticleCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Catégorie d'article éditorial (TRAVEL-901, issue #6104). Unicité du code par tenant.
 */
class TravelArticleCategory extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<TravelArticleCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id', 'code', 'name', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
