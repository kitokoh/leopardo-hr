<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\ReservationStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantReservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantReservation>
 */
class RestaurantReservationFactory extends Factory
{
    protected $model = RestaurantReservation::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'reference' => strtoupper($this->faker->unique()->bothify('RSV-###')),
            'customer_contact_id' => null,
            'contact_name' => $this->faker->name(),
            'contact_phone' => $this->faker->phoneNumber(),
            'reserved_at' => $this->faker->dateTimeBetween('now', '+2 weeks'),
            'covers' => $this->faker->numberBetween(1, 8),
            'table_id' => null,
            'zone_id' => null,
            'status' => ReservationStatus::PENDING->value,
            'deposit_minor' => $this->faker->optional()->numberBetween(500, 5000),
            'notes_redacted' => $this->faker->optional()->sentence(),
            'idempotency_key' => $this->faker->optional()->uuid(),
        ];
    }
}
