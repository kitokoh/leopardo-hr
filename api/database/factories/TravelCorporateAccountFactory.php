<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelCorporateAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelCorporateAccount>
 */
class TravelCorporateAccountFactory extends Factory
{
    protected $model = TravelCorporateAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'contact_email' => $this->faker->safeEmail(),
            'credit_limit_minor' => 100000,
            'currency' => 'XAF',
            'is_active' => true,
        ];
    }
}
