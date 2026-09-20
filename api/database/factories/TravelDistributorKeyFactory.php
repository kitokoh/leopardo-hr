<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelDistributorKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TravelDistributorKey>
 */
class TravelDistributorKeyFactory extends Factory
{
    protected $model = TravelDistributorKey::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'api_key_hash' => hash('sha256', Str::random(32)),
            'scopes' => ['catalog.read'],
            'enabled' => true,
            'last_used_at' => null,
            'usage_count' => 0,
            'rotated_at' => null,
            'revoked_at' => null,
            'created_by_user_id' => null,
        ];
    }
}
