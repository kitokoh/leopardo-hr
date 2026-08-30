<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelArticle>
 */
class TravelArticleFactory extends Factory
{
    protected $model = TravelArticle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(6),
            'body_redacted' => $this->faker->paragraphs(2, true),
            'status' => 'draft',
            'author_user_id' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(['status' => 'published', 'published_at' => now()]);
    }
}
