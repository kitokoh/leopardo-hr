<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\PurchaseOrderStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantPurchaseOrder;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantPurchaseOrder>
 */
class RestaurantPurchaseOrderFactory extends Factory
{
    protected $model = RestaurantPurchaseOrder::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'supplier_id' => RestaurantSupplier::factory(),
            'reference' => strtoupper($this->faker->unique()->bothify('PO-###')),
            'status' => PurchaseOrderStatus::DRAFT->value,
            'expected_at' => $this->faker->optional()->dateTimeBetween('now', '+1 month'),
            'received_at' => null,
            'total_minor' => $this->faker->optional()->numberBetween(1000, 100000),
            'currency' => 'DZD',
        ];
    }
}
