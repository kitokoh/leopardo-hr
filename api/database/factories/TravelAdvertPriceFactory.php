<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPrice;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelAdvertPrice>
 */
class TravelAdvertPriceFactory extends Factory
{
    protected $model = TravelAdvertPrice::class;

    public function definition(): array
    {
        return [
            'advert_type_id' => TravelAdvertType::factory(),
            'advert_position_id' => TravelAdvertPosition::factory(),
            'price_per_image_minor' => 5000,
            'price_per_character_minor' => 100,
            'currency' => 'XAF',
        ];
    }
}
