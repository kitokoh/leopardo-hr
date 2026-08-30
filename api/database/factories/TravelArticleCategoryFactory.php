<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelArticleCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelArticleCategory>
 */
class TravelArticleCategoryFactory extends Factory
{
    protected $model = TravelArticleCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('CAT-###')),
            'name' => $this->faker->words(2, true),
            'is_active' => true,
        ];
    }
}
