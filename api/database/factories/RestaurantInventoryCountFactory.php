<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\InventoryCountStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantInventoryCount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantInventoryCount>
 */
class RestaurantInventoryCountFactory extends Factory
{
    protected $model = RestaurantInventoryCount::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'counted_at' => now(),
            'status' => InventoryCountStatus::DRAFT->value,
            'counted_by_user_id' => null,
            'approved_by' => null,
            'approved_at' => null,
        ];
    }
}
