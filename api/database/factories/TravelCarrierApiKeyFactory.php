<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelCarrier;
use App\Modules\TravelAgency\Domain\Models\TravelCarrierApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TravelCarrierApiKey>
 */
class TravelCarrierApiKeyFactory extends Factory
{
    protected $model = TravelCarrierApiKey::class;

    public function definition(): array
    {
        return [
            'carrier_id' => TravelCarrier::factory(),
            'api_key_hash' => hash('sha256', Str::random(32)),
            'label' => $this->faker->words(2, true),
            'enabled' => true,
            'last_used_at' => null,
            'created_by_user_id' => null,
        ];
    }
}
