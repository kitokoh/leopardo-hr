<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\RestaurantManager\Domain\Models\RestaurantPublicShopToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RestaurantPublicShopToken>
 */
class RestaurantPublicShopTokenFactory extends Factory
{
    protected $model = RestaurantPublicShopToken::class;

    public function definition(): array
    {
        $plain = 'rst-'.Str::random(40);

        return [
            'token_hash' => RestaurantPublicShopToken::hash($plain),
            'name' => 'default',
            'active' => true,
            'last_used_at' => null,
        ];
    }
}
