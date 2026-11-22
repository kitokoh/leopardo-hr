<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use App\Modules\RestaurantManager\Domain\Models\RestaurantDeliveryRider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantDeliveryRider>
 */
class RestaurantDeliveryRiderFactory extends Factory
{
    protected $model = RestaurantDeliveryRider::class;

    public function definition(): array
    {
        return [
            'branch_id' => RestaurantBranch::factory(),
            'employee_id' => null,
            'name' => $this->faker->name(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'vehicle_code' => $this->faker->optional()->bothify('VH-####'),
            // #9820 — défaut DÉTERMINISTE. `boolean(85)` rendait 15 % des
            // livreurs inactifs au hasard : tout test qui ne fixait pas
            // `is_active` héritait d'un échec intermittent (le service refuse
            // d'assigner un livreur inactif → 422 au lieu de 200).
            // L'inactivité est un cas EXPLICITE, posé par les tests qui la
            // visent (`is_active => false`).
            'is_active' => true,
        ];
    }
}
