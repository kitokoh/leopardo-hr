<?php

declare(strict_types=1);

namespace App\Modules\RestaurantManager\Domain\Models;

use App\Modules\RestaurantManager\Domain\Enums\TableSessionStatus;
use App\Shared\Traits\BelongsToCompany;
use Database\Factories\RestaurantTableSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Occupation d'une table (RESTO-209, issue #6174).
 *
 * Une table ouverte porte une session tant qu'elle n'est pas libérée ;
 * `order_id` rattache la commande en cours (optionnel tant que la commande
 * n'est pas créée). Une seule session ouverte par table.
 */
class RestaurantTableSession extends Model
{
    use BelongsToCompany;

    /** @use HasFactory<RestaurantTableSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'table_id',
        'order_id',
        'opened_at',
        'closed_at',
        'covers',
        'status',
    ];

    protected $attributes = [
        'status' => 'open',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'covers' => 'integer',
        'status' => TableSessionStatus::class,
    ];

    /**
     * @return BelongsTo<RestaurantBranch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(RestaurantBranch::class, 'branch_id');
    }

    /**
     * @return BelongsTo<RestaurantTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'table_id');
    }

    /**
     * @return BelongsTo<RestaurantOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(RestaurantOrder::class, 'order_id');
    }
}
