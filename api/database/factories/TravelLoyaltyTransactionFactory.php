<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyAccount;
use App\Modules\TravelAgency\Domain\Models\TravelLoyaltyTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelLoyaltyTransaction>
 */
class TravelLoyaltyTransactionFactory extends Factory
{
    protected $model = TravelLoyaltyTransaction::class;

    public function definition(): array
    {
        return [
            'account_id' => TravelLoyaltyAccount::factory(),
            'points' => 100,
            'type' => 'earn',
            'reason' => 'Trajet effectué',
            'ticket_id' => null,
            'booking_id' => null,
        ];
    }
}
