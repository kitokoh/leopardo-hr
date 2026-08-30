<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\TravelAgency\Domain\Models\TravelQuiz;
use App\Modules\TravelAgency\Domain\Models\TravelQuizQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TravelQuizQuestion>
 */
class TravelQuizQuestionFactory extends Factory
{
    protected $model = TravelQuizQuestion::class;

    public function definition(): array
    {
        return [
            'quiz_id' => TravelQuiz::factory(),
            'question' => $this->faker->sentence(8),
            'options' => ['Douala', 'Yaoundé', 'Garoua', 'Bafoussam'],
            'correct_option_index' => 1,
            'points' => 1,
            'position' => 0,
        ];
    }
}
