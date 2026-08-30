<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReceiving;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantReceiving>
 */
class RestaurantReceivingFactory extends Factory
{
    protected $model = RestaurantReceiving::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'purchase_order_id' => RestaurantPurchaseOrder::factory(),
            'supplier_id' => null,
            'reference' => strtoupper($this->faker->unique()->bothify('RCV-###')),
            'received_at' => now(),
            'note_redacted' => $this->faker->optional()->sentence(),
        ];
    }
}
