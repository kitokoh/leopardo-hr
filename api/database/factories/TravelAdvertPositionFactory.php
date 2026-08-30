<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelAdvertPosition>
 */
class TravelAdvertPositionFactory extends Factory
{
    protected $model = TravelAdvertPosition::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('pos_????'),
            'label' => $this->faker->words(3, true),
        ];
    }
}
