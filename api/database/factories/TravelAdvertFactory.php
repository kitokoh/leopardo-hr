<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\AdvertStatus;
use App\Modules\TravelAgency\Domain\Models\TravelAdvert;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertPosition;
use App\Modules\TravelAgency\Domain\Models\TravelAdvertType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelAdvert>
 */
class TravelAdvertFactory extends Factory
{
    protected $model = TravelAdvert::class;

    public function definition(): array
    {
        return [
            'advert_type_id' => TravelAdvertType::factory(),
            'advert_position_id' => TravelAdvertPosition::factory(),
            'title' => $this->faker->sentence(5),
            'content_redacted' => $this->faker->paragraph(2),
            'image_asset_id' => null,
            'price_minor' => 25000,
            'currency' => 'XAF',
            'status' => AdvertStatus::SUBMITTED->value,
            'payment_reference' => null,
            'paid_at' => null,
            'validated_by_user_id' => null,
            'validated_at' => null,
            'rejected_reason' => null,
            'validity_days' => 30,
            'starts_at' => null,
            'expires_at' => null,
            'created_by_user_id' => null,
        ];
    }
}
