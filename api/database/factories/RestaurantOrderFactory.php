<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\OrderSource;
use App\Modules\RestaurantManager\Domain\Enums\OrderStatus;
use App\Modules\RestaurantManager\Domain\Enums\OrderType;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantOrder>
 */
class RestaurantOrderFactory extends Factory
{
    protected $model = RestaurantOrder::class;

    public function definition(): array
    {
        $subtotal = $this->faker->numberBetween(500, 50000);
        $tax = (int) round($subtotal * 0.19);
        $discount = 0;
        if ($this->faker->boolean(30)) {
            $discount = $this->faker->numberBetween(100, 2000);
        }

        return [
            'branch_id' => RestaurantBranch::factory(),
            'pos_session_id' => null,
            'reference' => 'RST-'.strtoupper($this->faker->unique()->bothify('????????')),
            'order_type' => OrderType::DINE_IN->value,
            'table_id' => null,
            'zone_id' => null,
            'covers' => $this->faker->optional()->numberBetween(1, 8),
            'customer_contact_id' => null,
            'rider_id' => null,
            'status' => OrderStatus::OPEN->value,
            'subtotal_minor' => $subtotal,
            'tax_minor' => $tax,
            'discount_minor' => $discount,
            'total_minor' => $subtotal + $tax - $discount,
            'currency' => 'DZD',
            'source' => OrderSource::POS->value,
            'note_redacted' => $this->faker->optional()->sentence(),
            'idempotency_key' => $this->faker->optional()->uuid(),
            'version' => 1,
        ];
    }
}
