<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Enums\RestaurantRecordStatus;
use App\Modules\RestaurantManager\Domain\Models\RestaurantBranch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantBranch>
 */
class RestaurantBranchFactory extends Factory
{
    protected $model = RestaurantBranch::class;

    public function definition(): array
    {
        return [
            // #7452 — company_id est NOT NULL : hors contexte tenant (factories
            // imbriquées type 'branch_id' => RestaurantBranch::factory()), le
            // trait BelongsToCompany ne peut pas l'injecter ; avec un tenant
            // actif, la valeur est forcée depuis le tenant courant (#7646).
            'company_id' => \App\Core\Tenant\Domain\Models\Company::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('BR-###')),
            'name' => $this->faker->company().' Restaurant',
            'address' => $this->faker->optional()->streetAddress(),
            'city' => $this->faker->optional()->city(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'timezone' => 'UTC',
            'currency' => 'DZD',
            'status' => RestaurantRecordStatus::ACTIVE->value,
        ];
    }
}
