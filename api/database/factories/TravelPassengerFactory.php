<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Enums\AgeCategory;
use App\Modules\TravelAgency\Domain\Enums\DocumentType;
use App\Modules\TravelAgency\Domain\Models\TravelBooking;
use App\Modules\TravelAgency\Domain\Models\TravelClass;
use App\Modules\TravelAgency\Domain\Models\TravelPassenger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelPassenger>
 */
class TravelPassengerFactory extends Factory
{
    protected $model = TravelPassenger::class;

    public function definition(): array
    {
        return [
            'booking_id' => TravelBooking::factory(),
            'full_name' => $this->faker->name(),
            'birth_date' => $this->faker->date(),
            'document_type' => DocumentType::NATIONAL_ID->value,
            'document_number_encrypted' => null,
            'document_number_hash' => null,
            'age_category' => AgeCategory::ADULT->value,
            'class_id' => TravelClass::factory(),
            'seat_number' => $this->faker->numberBetween(1, 60),
            'unit_price_minor' => $this->faker->numberBetween(50000, 500000),
        ];
    }

    public function withDocumentNumber(string $documentNumber): self
    {
        return $this->afterMaking(function (TravelPassenger $passenger) use ($documentNumber): void {
            $passenger->setDocumentNumber($documentNumber);
        });
    }
}
