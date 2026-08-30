<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelAdvertType>
 */
class TravelAdvertTypeFactory extends Factory
{
    protected $model = TravelAdvertType::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('type_????'),
            'label' => $this->faker->words(3, true),
        ];
    }
}
