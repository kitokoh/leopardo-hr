<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelComment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelComment>
 */
class TravelCommentFactory extends Factory
{
    protected $model = TravelComment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => 1,
            'author_type' => 'employee',
            'author_user_id' => null,
            'author_name' => $this->faker->name(),
            'body' => $this->faker->sentence(8),
            'status' => 'pending',
        ];
    }
}
