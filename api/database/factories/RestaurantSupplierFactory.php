<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantSupplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantSupplier>
 */
class RestaurantSupplierFactory extends Factory
{
    protected $model = RestaurantSupplier::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'contact_phone' => $this->faker->optional()->phoneNumber(),
            'email' => $this->faker->optional()->companyEmail(),
            'address' => $this->faker->optional()->streetAddress(),
            'status' => RestaurantRecordStatus::ACTIVE->value,
        ];
    }
}
